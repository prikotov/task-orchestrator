"""Чистая функция детектора стековых PR: Snapshot -> Verdict.

Слой Pure по контракту
docs/agents/reports/system-architect-gandalf/2026-10-26_14-26_stacked-pr-detector-contract.md
(раздел 11): без сети, файловой системы, времени и окружения — полная
детерминированность: одинаковый снимок даёт одинаковый вердикт.

Схема снимка и вердикта — одновременно формат JSON-фикстур тестов
(tests/Detector/fixtures/*.json).

Идентификатор коммита (SHA) для функции непрозрачен: любая непустая строка.
Реальный формат GitHub (hex) детектору безразличен, тесты используют
короткие мнемоники.
"""

from __future__ import annotations

from dataclasses import dataclass

# --- Исходы проверки (контракт, раздел 8) ---
OUTCOME_VIOLATION = "violation"
OUTCOME_CLEAN = "clean"
OUTCOME_INCONCLUSIVE = "inconclusive"

# --- Направления общей истории (контракт, раздел 5) ---
DIRECTION_STACKED_ON = "stacked_on"
DIRECTION_BASE_FOR = "base_for"
DIRECTION_EQUAL_HEADS = "equal_heads"
DIRECTION_PARTIAL = "partial"

# --- Машиночитаемые причины inconclusive ---
REASON_COMMITS_LIMIT = "commits_limit_exceeded"
REASON_API_ERROR = "api_error"
REASON_STATE_CHANGED = "state_changed_during_collection"
REASON_PR_NOT_OPEN = "pr_not_open"

# --- Виды информационных заметок ---
NOTE_TARGETS_OTHER_OPEN_BRANCH = "targets_other_open_branch"

NOTE_TEXT_TARGETS_OTHER_OPEN_BRANCH = (
    "Цель этого PR — исходная ветка открытого PR #{number}; "
    "детектор пилота не квалифицирует это как нарушение."
)


class SnapshotError(ValueError):
    """Снимок не соответствует схеме контракта (раздел 3)."""


@dataclass(frozen=True)
class PrInfo:
    """Метаданные PR, входящие в модель: без имён авторов, заголовков и тел."""

    number: int
    head_sha: str
    source_branch: str | None
    target_branch: str
    is_draft: bool
    same_repository: bool


@dataclass(frozen=True)
class Snapshot:
    """Снимок состояния на момент T0 (контракт, раздел 3)."""

    checked_pr: PrInfo
    target_tip: str
    open_prs: tuple[PrInfo, ...]
    commits: dict[int, tuple[str, ...]]
    truncated: frozenset[int]


@dataclass(frozen=True)
class Match:
    """Случай A: непустое пересечение неслитых коммитов с одним PR."""

    other_number: int
    other_target_branch: str
    common_shas: tuple[str, ...]
    direction: str
    proven_on_truncated: bool


@dataclass(frozen=True)
class Note:
    """Информационная заметка (случай B и др.); на статус не влияет."""

    kind: str
    other_number: int
    text: str


@dataclass(frozen=True)
class Verdict:
    """Результат детектора — уровень 1 из трёх (контракт, раздел 8)."""

    outcome: str
    reason: str
    matches: tuple[Match, ...]
    notes: tuple[Note, ...]


def _require_str(value: object, field_name: str) -> str:
    if not isinstance(value, str) or not value:
        raise SnapshotError(f"{field_name}: ожидается непустая строка, получено {value!r}")
    return value


def _int_key(value: object, field_name: str) -> int:
    """Номер PR из ключа/элемента снимка; нечисловое значение — SnapshotError."""
    try:
        return int(value)  # type: ignore[arg-type]
    except (TypeError, ValueError) as error:
        raise SnapshotError(
            f"{field_name}: ожидается целочисленный номер PR, получено {value!r}"
        ) from error


def _pr_info_from_dict(data: object) -> PrInfo:
    if not isinstance(data, dict):
        raise SnapshotError(f"PR: ожидается объект, получено {data!r}")
    number = data.get("number")
    if not isinstance(number, int) or isinstance(number, bool) or number <= 0:
        raise SnapshotError(f"PR.number: ожидается положительное целое, получено {number!r}")
    source_branch = data.get("source_branch")
    if source_branch is not None and not isinstance(source_branch, str):
        raise SnapshotError(f"PR {number}: source_branch должен быть строкой или null")
    is_draft = data.get("is_draft")
    if not isinstance(is_draft, bool):
        raise SnapshotError(f"PR {number}: is_draft должен быть boolean")
    same_repository = data.get("same_repository")
    if not isinstance(same_repository, bool):
        raise SnapshotError(f"PR {number}: same_repository должен быть boolean")
    return PrInfo(
        number=number,
        head_sha=_require_str(data.get("head_sha"), f"PR {number}: head_sha"),
        source_branch=source_branch,
        target_branch=_require_str(data.get("target_branch"), f"PR {number}: target_branch"),
        is_draft=is_draft,
        same_repository=same_repository,
    )


def snapshot_from_dict(data: object) -> Snapshot:
    """Валидирует и разбирает снимок из dict/JSON-структуры.

    Поднимает SnapshotError при любом отклонении от схемы раздела 3.
    """
    if not isinstance(data, dict):
        raise SnapshotError(f"снимок: ожидается объект, получено {data!r}")

    checked_pr = _pr_info_from_dict(data.get("checked_pr"))
    target_tip = _require_str(data.get("target_tip"), "target_tip")

    open_prs_raw = data.get("open_prs")
    if not isinstance(open_prs_raw, list):
        raise SnapshotError("open_prs: ожидается список")
    open_prs = tuple(_pr_info_from_dict(item) for item in open_prs_raw)

    known_numbers = [checked_pr.number] + [pr.number for pr in open_prs]
    if len(set(known_numbers)) != len(known_numbers):
        raise SnapshotError("номера PR в снимке должны быть уникальны (проверяемый не входит в open_prs)")

    commits_raw = data.get("commits")
    if not isinstance(commits_raw, dict):
        raise SnapshotError("commits: ожидается объект {номер PR: [sha, ...]}")
    commits: dict[int, tuple[str, ...]] = {}
    for key, shas in commits_raw.items():
        number = _int_key(key, f"commits[{key!r}]")
        if not isinstance(shas, list):
            raise SnapshotError(f"commits[{key}]: ожидается список")
        commits[number] = tuple(_require_str(sha, f"commits[{key}]") for sha in shas)
    if set(commits) != set(known_numbers):
        raise SnapshotError(
            "commits должен содержать списки ровно для проверяемого PR и всех открытых PR: "
            f"ожидались {sorted(set(known_numbers))}, получены {sorted(commits)}"
        )

    truncated_raw = data.get("truncated", [])
    if not isinstance(truncated_raw, list):
        raise SnapshotError("truncated: ожидается список номеров PR")
    truncated = frozenset(_int_key(number, "truncated") for number in truncated_raw)
    if not truncated <= set(commits):
        raise SnapshotError(f"truncated содержит номера вне снимка: {sorted(truncated - set(commits))}")

    return Snapshot(
        checked_pr=checked_pr,
        target_tip=target_tip,
        open_prs=open_prs,
        commits=commits,
        truncated=truncated,
    )


def verdict_to_dict(verdict: Verdict) -> dict:
    """Сериализует вердикт в JSON-совместимую структуру (схема раздела 11)."""
    return {
        "outcome": verdict.outcome,
        "reason": verdict.reason,
        "matches": [
            {
                "other_number": match.other_number,
                "other_target_branch": match.other_target_branch,
                "common_shas": list(match.common_shas),
                "direction": match.direction,
                "proven_on_truncated": match.proven_on_truncated,
            }
            for match in verdict.matches
        ],
        "notes": [
            {
                "kind": note.kind,
                "other_number": note.other_number,
                "text": note.text,
            }
            for note in verdict.notes
        ],
    }


def _direction(checked: PrInfo, other: PrInfo, u_checked: set[str], u_other: set[str]) -> str:
    """Направление общей истории — только по доказательству вхождения вершины.

    Приоритет случаев: equal_heads, затем stacked_on, затем base_for, затем
    partial. Аномалия данных (обе вершины входят в чужие U при разных head)
    в реальном графе Git невозможна; при необходимости выбирается stacked_on —
    первый приоритет контракта.
    """
    if checked.head_sha == other.head_sha:
        return DIRECTION_EQUAL_HEADS
    if other.head_sha in u_checked:
        return DIRECTION_STACKED_ON
    if checked.head_sha in u_other:
        return DIRECTION_BASE_FOR
    return DIRECTION_PARTIAL


def evaluate(snapshot: Snapshot) -> Verdict:
    """Вычисляет вердикт по снимку (контракт, разделы 4–9).

    - Нарушение (случай A): непустое пересечение U с любым открытым PR
      (любая цель, включая форки). Пересечение, найденное на усечённых
      данных, доказывает нарушение (монотонность усечения, раздел 9).
    - Inconclusive: пересечений нет, но хотя бы один участник сравнения
      усечён (лимит 250 коммитов) — молчаливое усечение запрещено.
    - Clean: пересечений нет и снимок полон. Случай B даёт только заметку.
    """
    checked = snapshot.checked_pr
    u_checked = set(snapshot.commits[checked.number])

    matches: list[Match] = []
    notes: list[Note] = []
    for other in snapshot.open_prs:
        u_other = set(snapshot.commits[other.number])
        common = u_checked & u_other
        if common:
            matches.append(
                Match(
                    other_number=other.number,
                    other_target_branch=other.target_branch,
                    common_shas=tuple(sorted(common)),
                    direction=_direction(checked, other, u_checked, u_other),
                    proven_on_truncated=(
                        checked.number in snapshot.truncated or other.number in snapshot.truncated
                    ),
                )
            )
        # Случай B: цель проверяемого — исходная ветка другого открытого PR
        # из того же репозитория (в форке совпадение имён случайно).
        if other.same_repository and checked.target_branch == other.source_branch:
            notes.append(
                Note(
                    kind=NOTE_TARGETS_OTHER_OPEN_BRANCH,
                    other_number=other.number,
                    text=NOTE_TEXT_TARGETS_OTHER_OPEN_BRANCH.format(number=other.number),
                )
            )

    if matches:
        return Verdict(
            outcome=OUTCOME_VIOLATION,
            reason="",
            matches=tuple(sorted(matches, key=lambda match: match.other_number)),
            notes=tuple(sorted(notes, key=lambda note: note.other_number)),
        )

    truncated_numbers = sorted(
        number
        for number in [checked.number] + [pr.number for pr in snapshot.open_prs]
        if number in snapshot.truncated
    )
    if truncated_numbers:
        return Verdict(
            outcome=OUTCOME_INCONCLUSIVE,
            reason=f"{REASON_COMMITS_LIMIT}:{','.join(str(number) for number in truncated_numbers)}",
            matches=(),
            notes=tuple(sorted(notes, key=lambda note: note.other_number)),
        )

    return Verdict(
        outcome=OUTCOME_CLEAN,
        reason="",
        matches=(),
        notes=tuple(sorted(notes, key=lambda note: note.other_number)),
    )
