"""Сбор снимка открытых PR через gh api — слой Collect по контракту.

docs/agents/reports/system-architect-gandalf/2026-10-26_14-26_stacked-pr-detector-contract.md
(разделы 7–9, 13, 14): единственный слой с доступом к сети; пагинация,
ограниченные повторы, проверка стабильности снимка (контрольные точки),
монотонность усечения на лимите 250 коммитов.

Безопасность (раздел 14): данные PR не подставляются в команды оболочки —
`gh api` вызывается списком аргументов subprocess, ответы читаются из файлов;
в shell-окружение попадают только валидированные скаляры.
"""

from __future__ import annotations

import json
import os
import re
import subprocess
import tempfile
import time
from datetime import datetime, timezone
from typing import Callable, Protocol

import pure

# Константы контракта (раздел 8, 9).
GITHUB_COMMITS_LIMIT = 250
MAX_ATTEMPTS = 3
RETRY_DELAYS_SECONDS = (2.0, 10.0, 30.0)
PER_PAGE = 100
MAX_PAGES_SAFETY = 20

_REPO_RE = re.compile(r"^[A-Za-z0-9][A-Za-z0-9_.-]*/[A-Za-z0-9][A-Za-z0-9_.-]*$")
# Имя ветки: допустимые символы git-refname; '..', lock и префиксы '.'/'-' отклонены.
_BRANCH_RE = re.compile(r"^(?!\/)(?!.*\/\/)(?!.*\.\.)(?!.*\{)(?!.*@{)(?!.*\/\.)(?!.*\.lock$)[A-Za-z0-9._\-\/]+$")


class TransientApiError(Exception):
    """Транзиентная ошибка API (5xx, rate limit, обрыв ответа) — повторяема."""


class PermanentCollectError(Exception):
    """Сбор невозможен без надежды на повтор (PR закрыт, нет gh, битые данные)."""

    def __init__(self, reason: str, details: str = ""):
        super().__init__(details or reason)
        self.reason = reason


class SnapshotChangedError(Exception):
    """Контрольные точки разошлись во время сбора — снимок неатомарен."""


class ApiClient(Protocol):
    """Контрактная оболочка доступа к GitHub API (раздел 11)."""

    def get(self, path: str) -> object:
        """GET-запрос; ответ — разобранный JSON. Ошибки — исключения."""
        ...

    def request_json(self, path: str, method: str, payload: dict) -> object:
        """Запрос с методом и JSON-телом из файла (без подстановки в shell)."""
        ...


class GhCliApi:
    """Реализация доступа через предустановленный `gh api`.

    Ответ пишется во временный файл и разбирается json — паттерн
    secret-scan-pr-content.yml: контент PR не проходит через shell.
    """

    def _run(self, args: list[str], input_file: str | None = None) -> object:
        with tempfile.TemporaryFile(mode="w+", encoding="utf-8") as out:
            proc = subprocess.run(
                ["gh", "api", *args],
                stdout=out,
                stderr=subprocess.PIPE,
                text=True,
                check=False,
            )
            if proc.returncode != 0:
                raise TransientApiError(proc.stderr.strip()[-500:] or f"gh api exit {proc.returncode}")
            out.seek(0)
            try:
                return json.load(out)
            except json.JSONDecodeError as error:
                raise TransientApiError(f"невалидный JSON в ответе: {error}") from error

    def get(self, path: str) -> object:
        return self._run([path])

    def request_json(self, path: str, method: str, payload: dict) -> object:
        with tempfile.NamedTemporaryFile(
            mode="w", encoding="utf-8", suffix=".json", delete=False
        ) as payload_file:
            json.dump(payload, payload_file, ensure_ascii=False)
            payload_path = payload_file.name
        try:
            return self._run(["--method", method, path, "--input", payload_path])
        finally:
            try:
                os.unlink(payload_path)
            except OSError:
                pass


def validate_repo(repo: str) -> str:
    if not isinstance(repo, str) or not _REPO_RE.match(repo):
        raise PermanentCollectError("invalid_repo", f"некорректный репозиторий: {repo!r}")
    return repo


def _validate_branch(branch: str) -> str:
    if not isinstance(branch, str) or not _BRANCH_RE.match(branch):
        raise PermanentCollectError("invalid_branch", f"некорректное имя ветки: {branch!r}")
    return branch


def _require(value: object, key: str) -> object:
    if value is None:
        raise PermanentCollectError("invalid_api_response", f"в ответе API нет поля {key}")
    return value


def _fetch_pr(api: ApiClient, repo: str, number: int) -> dict:
    data = api.get(f"repos/{repo}/pulls/{number}")
    if not isinstance(data, dict):
        raise PermanentCollectError("invalid_api_response", f"PR {number}: ожидается объект")
    state = _require(data.get("state"), "state")
    if state != "open":
        raise PermanentCollectError(pure.REASON_PR_NOT_OPEN, f"PR {number} не открыт (state={state!r})")
    return data


def _pr_info(pr_json: dict, repo: str) -> pure.PrInfo:
    head = _require(pr_json.get("head"), "head")
    base = _require(pr_json.get("base"), "base")
    head_repo = head.get("repo")
    same_repository = bool(head_repo) and head_repo.get("full_name") == repo
    return pure.PrInfo(
        number=int(_require(pr_json.get("number"), "number")),
        head_sha=str(_require(head.get("sha"), "head.sha")),
        source_branch=head.get("ref"),
        target_branch=str(_require(base.get("ref"), "base.ref")),
        is_draft=bool(pr_json.get("draft")),
        same_repository=same_repository,
    )


def _fetch_target_tip(api: ApiClient, repo: str, branch: str) -> str:
    data = api.get(f"repos/{repo}/branches/{_validate_branch(branch)}")
    commit = _require(_require(data, "branches").get("commit"), "commit")
    return str(_require(commit.get("sha"), "sha"))


def _fetch_open_prs(api: ApiClient, repo: str) -> list[dict]:
    items: list[dict] = []
    page = 1
    while True:
        data = api.get(f"repos/{repo}/pulls?state=open&per_page={PER_PAGE}&page={page}")
        if not isinstance(data, list):
            raise PermanentCollectError("invalid_api_response", "список PR: ожидается массив")
        items.extend(data)
        if len(data) < PER_PAGE:
            return items
        if page >= MAX_PAGES_SAFETY:
            # Молчаливое усечение запрещено контрактом: неполный список открытых
            # PR дал бы ложно-чистый вердикт — завершаем сбор с ошибкой.
            raise PermanentCollectError(
                pure.REASON_API_ERROR,
                f"список открытых PR превысил предел {MAX_PAGES_SAFETY} страниц "
                f"(по {PER_PAGE} PR на страницу) — сбор остановлен",
            )
        page += 1


def _fetch_commits(api: ApiClient, repo: str, number: int) -> tuple[list[str], bool]:
    """Список неслитых коммитов PR; True — достигнут лимит 250 (усечение)."""
    shas: list[str] = []
    page = 1
    while True:
        data = api.get(f"repos/{repo}/pulls/{number}/commits?per_page={PER_PAGE}&page={page}")
        if not isinstance(data, list):
            raise PermanentCollectError("invalid_api_response", f"коммиты PR {number}: ожидается массив")
        shas.extend(str(_require(item.get("sha"), "sha")) for item in data)
        truncated = len(shas) >= GITHUB_COMMITS_LIMIT
        if truncated or len(data) < PER_PAGE or page >= MAX_PAGES_SAFETY:
            return shas[:GITHUB_COMMITS_LIMIT], truncated
        page += 1


def _checkpoints_tuple(open_prs: list[pure.PrInfo]) -> tuple:
    return tuple(sorted((pr.number, pr.head_sha) for pr in open_prs))


def _collect_attempt(api: ApiClient, repo: str, pr_number: int) -> dict:
    """Один проход сбора снимка с проверкой стабильности (контрольные точки)."""
    pr_json = _fetch_pr(api, repo, pr_number)
    checked = _pr_info(pr_json, repo)
    tip = _fetch_target_tip(api, repo, checked.target_branch)

    open_raw = [item for item in _fetch_open_prs(api, repo) if int(item.get("number", 0)) != pr_number]
    open_prs = [_pr_info(item, repo) for item in open_raw]

    commits: dict[int, list[str]] = {}
    truncated: set[int] = set()
    for number in [checked.number] + [pr.number for pr in open_prs]:
        shas, is_truncated = _fetch_commits(api, repo, number)
        commits[number] = shas
        if is_truncated:
            truncated.add(number)

    # Контрольные точки после сбора (раздел 8): расхождение — снимок неатомарен.
    pr_json_again = _fetch_pr(api, repo, pr_number)
    checked_again = _pr_info(pr_json_again, repo)
    tip_again = _fetch_target_tip(api, repo, checked.target_branch)
    open_again = [
        _pr_info(item, repo)
        for item in _fetch_open_prs(api, repo)
        if int(item.get("number", 0)) != pr_number
    ]
    if (
        checked_again.head_sha != checked.head_sha
        or checked_again.target_branch != checked.target_branch
        or tip_again != tip
        or _checkpoints_tuple(open_again) != _checkpoints_tuple(open_prs)
    ):
        raise SnapshotChangedError("состояние изменилось во время проверки")

    return {
        "checked_pr": {
            "number": checked.number,
            "head_sha": checked.head_sha,
            "source_branch": checked.source_branch,
            "target_branch": checked.target_branch,
            "is_draft": checked.is_draft,
            "same_repository": checked.same_repository,
        },
        "target_tip": tip,
        "open_prs": [
            {
                "number": pr.number,
                "head_sha": pr.head_sha,
                "source_branch": pr.source_branch,
                "target_branch": pr.target_branch,
                "is_draft": pr.is_draft,
                "same_repository": pr.same_repository,
            }
            for pr in open_prs
        ],
        "commits": {str(number): shas for number, shas in commits.items()},
        "truncated": sorted(truncated),
    }


def collect_snapshot(
    repo: str,
    pr_number: int,
    api: ApiClient | None = None,
    sleep: Callable[[float], None] = time.sleep,
    now: Callable[[], datetime] | None = None,
) -> tuple[dict, str]:
    """Собирает снимок: до 3 попыток, паузы 2/10/30 с, контрольные точки.

    Возвращает (снимок в формате схемы pure, время снимка ISO-8601 UTC).
    Поднимает PermanentCollectError с машиночитаемой причиной, когда сбор
    невозможен.
    """
    validate_repo(repo)
    if not isinstance(pr_number, int) or isinstance(pr_number, bool) or pr_number <= 0:
        raise PermanentCollectError("invalid_pr_number", f"некорректный номер PR: {pr_number!r}")
    client = api if api is not None else GhCliApi()

    reason = pure.REASON_API_ERROR
    details = ""
    for attempt in range(MAX_ATTEMPTS):
        try:
            snapshot = _collect_attempt(client, repo, pr_number)
            moment = (now or (lambda: datetime.now(timezone.utc)))()
            return snapshot, moment.strftime("%Y-%m-%dT%H:%M:%SZ")
        except PermanentCollectError:
            raise
        except SnapshotChangedError as error:
            reason, details = pure.REASON_STATE_CHANGED, str(error)
        except TransientApiError as error:
            reason, details = pure.REASON_API_ERROR, str(error)
        if attempt < MAX_ATTEMPTS - 1:
            sleep(RETRY_DELAYS_SECONDS[attempt])
    raise PermanentCollectError(reason, details)
