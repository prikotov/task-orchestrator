"""CLI детектора стековых PR — слой оркестрации по контракту.

docs/agents/reports/system-architect-gandalf/2026-10-26_14-26_stacked-pr-detector-contract.md
(разделы 8, 11, 13, 15): подкоманды detect и comment; режимы inform/enforce;
сообщения из шаблонов с подстановкой только чисел и SHA; коды возврата по
таблице исходов; PR-комментарий с маркером (редактируется, не плодится).

Принципы сообщений (раздел 15): сообщается общая неслитая история, виновная
ветка не назначается без доказательства направления; инструкция «переделать
от целевой» — только подтверждённому потомку (stacked_on); ссылка на правило
AGENTS.md обязательна; статус маркируется как снимок на T0.
"""

from __future__ import annotations

import argparse
import json
import os
import sys
from typing import Sequence

import collect
import pure

EXIT_OK = 0
EXIT_USAGE = 1
EXIT_INCONCLUSIVE = 2
EXIT_VIOLATION_ENFORCE = 3

MODE_INFORM = "inform"
MODE_ENFORCE = "enforce"

COMMENT_MARKER = "<!-- stacked-pr-detector -->"
RULE_FILE = "AGENTS.md"
RULE_TEXT = "AGENTS.md, раздел «Pull Request» (запрет стековых PR)"
MAX_SHAS_IN_MESSAGE = 5
SHA_DISPLAY_LEN = 7

REASON_HUMAN = {
    pure.REASON_COMMITS_LIMIT: "превышен охват API (250 коммитов) у PR",
    pure.REASON_API_ERROR: "ошибка или недоступность GitHub API",
    pure.REASON_STATE_CHANGED: "состояние изменилось во время проверки",
    pure.REASON_PR_NOT_OPEN: "проверяемый PR не открыт",
    "invalid_repo": "некорректный репозиторий",
    "invalid_branch": "некорректное имя ветки",
    "invalid_pr_number": "некорректный номер PR",
    "invalid_api_response": "неожиданный ответ GitHub API",
}


def _short(sha: str) -> str:
    return sha[:SHA_DISPLAY_LEN]


def _reason_human(reason: str) -> str:
    """Машиночитаемая причина -> человекочитаемое пояснение."""
    code, _, tail = reason.partition(":")
    base = REASON_HUMAN.get(code, code)
    if code == pure.REASON_COMMITS_LIMIT and tail:
        numbers = ", ".join(f"#{n}" for n in tail.split(","))
        return f"{base} {numbers}; полная проверка невозможна"
    return base


def _build_rule_link(report: dict) -> str:
    """Ссылка на AGENTS.md вершины цели снимка; локально — относительный путь."""
    meta = report.get("meta", {})
    repo, tip = meta.get("repo"), meta.get("target_tip")
    if repo and tip:
        return f"https://github.com/{repo}/blob/{tip}/{RULE_FILE}"
    return RULE_FILE


def _shas_sample(shas: Sequence[str]) -> str:
    shown = ", ".join(f"`{_short(sha)}`" for sha in shas[:MAX_SHAS_IN_MESSAGE])
    rest = len(shas) - min(len(shas), MAX_SHAS_IN_MESSAGE)
    if rest > 0:
        shown += f" и ещё {rest}"
    return shown


def _snapshot_line(meta: dict) -> str:
    head = _short(str(meta.get("checked_head", ""))) or "?"
    branch = meta.get("target_branch", "?")
    tip = _short(str(meta.get("target_tip", ""))) or "?"
    when = meta.get("snapshot_time_utc", "неизвестно")
    return (
        f"Статус — снимок на {when} (UTC), head `{head}`, цель `{branch}@{tip}`; "
        "актуализируется при следующих событиях этого PR."
    )


def build_pr_comment(report: dict, rule_link: str) -> str:
    """Тело PR-комментария по шаблонам раздела 15 (чистая функция)."""
    verdict = report["verdict"]
    meta = report.get("meta", {})
    lines: list[str] = [COMMENT_MARKER, ""]

    if verdict["outcome"] == pure.OUTCOME_VIOLATION:
        lines.append("🔴 **Детектор стековых PR: нарушение.**")
        lines.append("")
        stacked_on_numbers: list[int] = []
        for match in verdict["matches"]:
            number = match["other_number"]
            shas = _shas_sample(match["common_shas"])
            count = len(match["common_shas"])
            target = match["other_target_branch"]
            if match["direction"] == pure.DIRECTION_STACKED_ON:
                stacked_on_numbers.append(number)
                lines.append(
                    f"- Ветка этого PR содержит вершину открытого PR #{number} (цель `{target}`) — "
                    f"она построена поверх несмерженной ветки. Общих неслитых коммитов: {count} ({shas})."
                )
                if match["proven_on_truncated"]:
                    lines.append(
                        f"  Нарушение доказано на усечённых данных (PR превышает лимит API 250 коммитов)."
                    )
            else:
                extras = {
                    pure.DIRECTION_BASE_FOR: " Ваша вершина входит в этот PR — над этой веткой построен другой PR.",
                    pure.DIRECTION_EQUAL_HEADS: " Вершины обоих PR совпадают.",
                    pure.DIRECTION_PARTIAL: "",
                }
                lines.append(
                    f"- Общая неслитая история с открытым PR #{number} (цель `{target}`): "
                    f"общих коммитов {count} ({shas}); направление зависимости автоматически "
                    f"не доказано, виновная ветка не назначается."
                    f"{extras[match['direction']]}"
                )
        lines.append("")
        lines.append(f"По правилу {RULE_TEXT} ({rule_link}) стековые PR запрещены: "
                     "зависимые правки — один PR или последовательное выполнение.")
        if stacked_on_numbers:
            joined = ", ".join(f"#{n}" for n in stacked_on_numbers)
            lines.append(
                f"Переделайте ветку от целевой (`{meta.get('target_branch', 'target')}`) "
                f"или объедините изменения с {joined} в один PR."
            )
        else:
            lines.append(
                "Проверьте состав коммитов обеих веток и устраните пересечение "
                "(переделка от целевой — для ветки, построенной поверх другой)."
            )
    elif verdict["outcome"] == pure.OUTCOME_CLEAN:
        if verdict["notes"]:
            lines.append("ℹ️ **Детектор стековых PR: заметка.**")
            lines.append("")
            for note in verdict["notes"]:
                lines.append(
                    f"- Цель этого PR — исходная ветка открытого PR #{note['other_number']}. "
                    "Пилот не квалифицирует это как нарушение (коммиты того PR в базе этого PR, "
                    "пересечения нет). Убедитесь, что это осознанная интеграционная ветка "
                    "(например, ветка эпика), а не случайный стек."
                )
        else:
            lines.append("✅ **Детектор стековых PR: нарушений не найдено** (снимок полный).")
    else:
        lines.append("⚠️ **Детектор стековых PR: проверка не выполнена.**")
        lines.append("")
        lines.append(f"Причина: {_reason_human(verdict['reason'])}.")
        lines.append("Статус не «зелёный», чтобы не создавать ложную уверенность. Повторите запуск проверки.")

    lines.append("")
    lines.append(_snapshot_line(meta))
    return "\n".join(lines)


def build_console_message(report: dict, rule_link: str) -> str:
    """Краткое сообщение в журнал задания (не markdown-тяжёлое)."""
    verdict = report["verdict"]
    meta = report.get("meta", {})
    head = [f"Стековые PR: {verdict['outcome']} (режим {meta.get('mode', MODE_INFORM)})"]
    for match in verdict["matches"]:
        head.append(
            f"  #{match['other_number']} ({match['other_target_branch']}): "
            f"{match['direction']}, общих неслитых коммитов {len(match['common_shas'])}"
        )
    for note in verdict["notes"]:
        head.append(f"  заметка: цель — исходная ветка открытого PR #{note['other_number']}")
    if verdict["reason"]:
        head.append(f"  причина: {_reason_human(verdict['reason'])}")
    head.append(f"  правило: {rule_link}")
    head.append(f"  {_snapshot_line(meta)}")
    return "\n".join(head)


def build_summary(report: dict, rule_link: str) -> str:
    """Markdown для GITHUB_STEP_SUMMARY (раздел 15): детальный технический отчёт."""
    verdict = report["verdict"]
    meta = report.get("meta", {})
    lines = ["## Детектор стековых PR", ""]
    outcome_icons = {
        pure.OUTCOME_VIOLATION: "🔴 нарушение",
        pure.OUTCOME_CLEAN: "✅ чисто",
        pure.OUTCOME_INCONCLUSIVE: "⚠️ невозможно проверить",
    }
    lines.append(f"**Результат:** {outcome_icons.get(verdict['outcome'], verdict['outcome'])} "
                 f"(режим пилота: {meta.get('mode', MODE_INFORM)})")
    lines.append("")
    lines.append("| Параметр | Значение |")
    lines.append("|---|---|")
    lines.append(f"| PR | #{meta.get('pr', '?')} (`{meta.get('checked_head', '?')}`) |")
    lines.append(f"| Цель | `{meta.get('target_branch', '?')}` @ `{meta.get('target_tip', '?')}` |")
    lines.append(f"| Снимок (UTC) | {meta.get('snapshot_time_utc', '?')} |")
    lines.append(f"| Открытых PR в сравнении | {meta.get('open_prs_count', '?')} |")
    lines.append(f"| Правило | {rule_link} |")
    lines.append("")
    if verdict["matches"]:
        lines.append("### Пересечения")
        lines.append("")
        lines.append("| PR | Цель | Направление | Общих коммитов | SHA (до 5) | Доказано на усечённых |")
        lines.append("|---|---|---|---|---|---|")
        for match in verdict["matches"]:
            shas = ", ".join(_short(sha) for sha in match["common_shas"][:MAX_SHAS_IN_MESSAGE])
            lines.append(
                f"| #{match['other_number']} | `{match['other_target_branch']}` | "
                f"{match['direction']} | {len(match['common_shas'])} | {shas} | "
                f"{'да' if match['proven_on_truncated'] else 'нет'} |"
            )
        lines.append("")
    if verdict["notes"]:
        lines.append("### Заметки")
        lines.append("")
        for note in verdict["notes"]:
            lines.append(f"- {note['text']}")
        lines.append("")
    if verdict["reason"]:
        lines.append(f"**Причина inconclusive:** `{verdict['reason']}` — {_reason_human(verdict['reason'])}")
        lines.append("")
    lines.append("<details><summary>JSON-вердикт</summary>")
    lines.append("")
    lines.append("```json")
    lines.append(json.dumps(report, ensure_ascii=False, indent=2))
    lines.append("```")
    lines.append("")
    lines.append("</details>")
    return "\n".join(lines)


def _exit_code(verdict: pure.Verdict, mode: str) -> int:
    if verdict.outcome == pure.OUTCOME_CLEAN:
        return EXIT_OK
    if verdict.outcome == pure.OUTCOME_INCONCLUSIVE:
        return EXIT_INCONCLUSIVE
    return EXIT_OK if mode == MODE_INFORM else EXIT_VIOLATION_ENFORCE


def _append_summary(report: dict, rule_link: str) -> None:
    path = os.environ.get("GITHUB_STEP_SUMMARY")
    if not path:
        return
    with open(path, "a", encoding="utf-8") as handle:
        handle.write(build_summary(report, rule_link))
        handle.write("\n")


def _load_snapshot_report(path: str) -> dict:
    with open(path, encoding="utf-8") as handle:
        data = json.load(handle)
    if not isinstance(data, dict) or "snapshot" not in data:
        raise ValueError(f"{path}: ожидается отчёт детектора с полем snapshot")
    return data


def cmd_detect(args: argparse.Namespace) -> int:
    mode = args.mode or os.environ.get("DETECTOR_MODE") or MODE_INFORM
    if mode not in (MODE_INFORM, MODE_ENFORCE):
        print(f"неизвестный режим: {mode!r} (допустимо inform|enforce)", file=sys.stderr)
        return EXIT_USAGE

    try:
        if args.snapshot:
            report = _load_snapshot_report(args.snapshot)
            snapshot = pure.snapshot_from_dict(report["snapshot"])
        else:
            snapshot_dict, collected_at = collect.collect_snapshot(args.repo, args.pr)
            snapshot = pure.snapshot_from_dict(snapshot_dict)
            report = {
                "snapshot": snapshot_dict,
                "meta": {"repo": args.repo, "pr": args.pr, "snapshot_time_utc": collected_at},
            }
    except collect.PermanentCollectError as error:
        snapshot = None
        report = {
            "verdict": {
                "outcome": pure.OUTCOME_INCONCLUSIVE,
                "reason": error.reason,
                "matches": [],
                "notes": [],
            },
            "meta": {
                "repo": getattr(args, "repo", None),
                "pr": getattr(args, "pr", None),
                "mode": mode,
            },
        }
    except pure.SnapshotError as error:
        print(f"ошибка снимка: {error}", file=sys.stderr)
        return EXIT_USAGE

    if snapshot is not None:
        verdict = pure.evaluate(snapshot)
        meta = dict(report.get("meta", {}))
        meta.update(
            {
                "mode": mode,
                "checked_head": snapshot.checked_pr.head_sha,
                "target_branch": snapshot.checked_pr.target_branch,
                "target_tip": snapshot.target_tip,
                "open_prs_count": len(snapshot.open_prs),
            }
        )
        report = {"verdict": pure.verdict_to_dict(verdict), "meta": meta}

    if args.output:
        with open(args.output, "w", encoding="utf-8") as handle:
            json.dump(report, handle, ensure_ascii=False, indent=2)

    rule_link = _build_rule_link(report)
    print(build_console_message(report, rule_link))
    _append_summary(report, rule_link)
    return _exit_code(_verdict_of(report), mode)


def _verdict_of(report: dict) -> pure.Verdict:
    """Восстанавливает Verdict из отчёта (для вычисления кода возврата)."""
    return pure.Verdict(
        outcome=report["verdict"]["outcome"],
        reason=report["verdict"]["reason"],
        matches=tuple(
            pure.Match(
                other_number=m["other_number"],
                other_target_branch=m["other_target_branch"],
                common_shas=tuple(m["common_shas"]),
                direction=m["direction"],
                proven_on_truncated=m["proven_on_truncated"],
            )
            for m in report["verdict"]["matches"]
        ),
        notes=tuple(
            pure.Note(kind=n["kind"], other_number=n["other_number"], text=n["text"])
            for n in report["verdict"]["notes"]
        ),
    )


def _find_comment_id(api: collect.ApiClient, repo: str, pr_number: int) -> int | None:
    page = 1
    while page <= 10:
        items = api.get(f"repos/{repo}/issues/{pr_number}/comments?per_page={collect.PER_PAGE}&page={page}")
        if not isinstance(items, list):
            return None
        for item in items:
            body = item.get("body") or ""
            if body.startswith(COMMENT_MARKER):
                return int(item["id"])
        if len(items) < collect.PER_PAGE:
            return None
        page += 1
    return None


def cmd_comment(args: argparse.Namespace, api: collect.ApiClient | None = None) -> int:
    with open(args.report, encoding="utf-8") as handle:
        report = json.load(handle)
    body = build_pr_comment(report, _build_rule_link(report))
    client = api if api is not None else collect.GhCliApi()
    comment_id = _find_comment_id(client, args.repo, args.pr)
    payload = {"body": body}
    if comment_id is not None:
        client.request_json(f"repos/{args.repo}/issues/comments/{comment_id}", "PATCH", payload)
        print(f"комментарий детектора #{comment_id} обновлён")
    else:
        client.request_json(f"repos/{args.repo}/issues/{args.pr}/comments", "POST", payload)
        print("комментарий детектора опубликован")
    return EXIT_OK


class _Parser(argparse.ArgumentParser):
    """argparse с кодом ошибки 1 (2 зарезервирован за inconclusive)."""

    def error(self, message: str):  # noqa: D102
        self.print_usage(sys.stderr)
        print(f"ошибка аргументов: {message}", file=sys.stderr)
        sys.exit(EXIT_USAGE)


def build_parser() -> _Parser:
    parser = _Parser(prog="stacked-pr-detector", description="Детектор стековых PR (контракт фазы DS)")
    commands = parser.add_subparsers(dest="command", required=True)

    detect = commands.add_parser("detect", help="собрать снимок и вынести вердикт")
    detect.add_argument("--repo", help="репозиторий owner/name")
    detect.add_argument("--pr", type=int, help="номер проверяемого PR")
    detect.add_argument("--mode", choices=[MODE_INFORM, MODE_ENFORCE],
                        help="режим (по умолчанию DETECTOR_MODE или inform)")
    detect.add_argument("--output", help="файл для JSON-отчёта (verdict + meta)")
    detect.add_argument("--snapshot", help="готовый отчёт со снимком: пропустить сбор (отладка/тесты)")
    detect.set_defaults(handler=cmd_detect)

    comment = commands.add_parser("comment", help="опубликовать/обновить PR-комментарий по отчёту")
    comment.add_argument("--repo", required=True, help="репозиторий owner/name")
    comment.add_argument("--pr", type=int, required=True, help="номер PR")
    comment.add_argument("--report", required=True, help="JSON-отчёт detect")
    comment.set_defaults(handler=cmd_comment)
    return parser


def main(argv: list[str], api: collect.ApiClient | None = None) -> int:
    parser = build_parser()
    args = parser.parse_args(argv)
    if args.command == "detect" and not args.snapshot:
        missing = [name for name in ("repo", "pr") if not getattr(args, name)]
        if missing:
            parser.error(f"detect: требуются --repo и --pr (или --snapshot); не задано: {', '.join(missing)}")
    try:
        return int(args.handler(args, api=api)) if args.command == "comment" else int(args.handler(args))
    except collect.PermanentCollectError as error:
        print(f"невозможно проверить: {_reason_human(error.reason)} ({error})", file=sys.stderr)
        return EXIT_INCONCLUSIVE
    except BrokenPipeError:
        return EXIT_OK


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
