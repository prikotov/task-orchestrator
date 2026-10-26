"""Тесты детектора стековых PR на синтетических сценариях S1–S20.

Матрица сценариев — контракт
docs/agents/reports/system-architect-gandalf/2026-10-26_14-26_stacked-pr-detector-contract.md
(раздел 17): расширение допустимо, сужение — нет. Все тесты работают без
GitHub и секретов: слой Pure — фикстуры-снимки (fixtures/*.json), слой
Collect — программируемая заглушка API (ScriptedApi), CLI — снимок из файла
и запуск точки входа bin/stacked-pr-detector.

Реальные стековые PR не создаются (запрет правила AGENTS.md).
"""

from __future__ import annotations

import copy
import io
import json
import subprocess
import sys
import tempfile
import unittest
from contextlib import redirect_stdout
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts" / "stacked-pr-detector"))

import cli  # noqa: E402
import collect  # noqa: E402
import pure  # noqa: E402

FIXTURES = Path(__file__).resolve().parent / "fixtures"


def load_snapshot(name: str) -> dict:
    with open(FIXTURES / name, encoding="utf-8") as handle:
        return json.load(handle)


def evaluate_fixture(name: str) -> pure.Verdict:
    return pure.evaluate(pure.snapshot_from_dict(load_snapshot(name)))


def checked_from(snapshot: dict, number: int) -> dict:
    """Перестраивает снимок: проверяемым становится PR `number` (для S9)."""
    all_prs = [snapshot["checked_pr"], *snapshot["open_prs"]]
    rebuilt = copy.deepcopy(snapshot)
    rebuilt["checked_pr"] = copy.deepcopy(next(pr for pr in all_prs if pr["number"] == number))
    rebuilt["open_prs"] = [copy.deepcopy(pr) for pr in all_prs if pr["number"] != number]
    return rebuilt


class ScriptedApi:
    """Заглушка GitHub API для слоя Collect: ответы по маршрутам, без сети.

    Ответы выдаются из очереди маршрута; когда очередь исчерпана, маршрут
    возвращает последний ответ (sticky) — удобно для контрольных точек.
    Элемент-исключение поднимается, элемент-callable вызывается.
    """

    def __init__(self) -> None:
        self.routes: dict[str, list] = {}
        self.calls: list[str] = []

    def given(self, path: str, *responses: object) -> "ScriptedApi":
        self.routes.setdefault(path, []).extend(responses)
        return self

    def _respond(self, path: str) -> object:
        queue = self.routes.get(path)
        if not queue:
            raise AssertionError(f"ScriptedApi: неожиданный запрос {path!r}; известны {sorted(self.routes)}")
        if len(queue) > 1:
            response = queue.pop(0)
        else:
            response = queue[0]
        if isinstance(response, Exception):
            raise response
        if callable(response):
            return response()
        return response

    def get(self, path: str) -> object:
        self.calls.append(path)
        return self._respond(path)

    def request_json(self, path: str, method: str, payload: dict) -> object:
        self.calls.append(f"{method} {path}")
        return self._respond(f"{method} {path}")


class PureScenarioTests(unittest.TestCase):
    """Сценарии S1–S8, S10–S16, S19, S20: фикстура → вердикт."""

    def assert_common_consistency(self, verdict: pure.Verdict, snapshot: dict) -> None:
        """Матч согласован со списками коммитов снимка (самопроверка фикстур)."""
        checked_number = snapshot["checked_pr"]["number"]
        for match in verdict.matches:
            expected = (
                set(snapshot["commits"][str(checked_number)])
                & set(snapshot["commits"][str(match.other_number)])
            )
            self.assertEqual(set(match.common_shas), expected)

    def test_s01_stale_branch_after_foreign_merge_is_clean(self):
        verdict = evaluate_fixture("snapshot-s01.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_CLEAN)
        self.assertEqual(verdict.matches, ())

    def test_s02_commits_merged_into_target_subtracted(self):
        verdict = evaluate_fixture("snapshot-s02.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_CLEAN)

    def test_s03_squashed_foreign_pr_no_equivalence_promised(self):
        verdict = evaluate_fixture("snapshot-s03.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_CLEAN)

    def test_s04_classic_stack_violation_stacked_on(self):
        verdict = evaluate_fixture("snapshot-s04.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_VIOLATION)
        self.assertEqual(len(verdict.matches), 1)
        match = verdict.matches[0]
        self.assertEqual(match.other_number, 101)
        self.assertEqual(match.direction, pure.DIRECTION_STACKED_ON)
        self.assertEqual(set(match.common_shas), {"q1", "q2"})
        self.assertFalse(match.proven_on_truncated)
        self.assert_common_consistency(verdict, load_snapshot("snapshot-s04.json"))

    def test_s05_clean_hotfix_from_tag_allowed_by_base_fact(self):
        verdict = evaluate_fixture("snapshot-s05.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_CLEAN)

    def test_s06_hotfix_name_with_foreign_work_violates(self):
        verdict = evaluate_fixture("snapshot-s06.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_VIOLATION)
        match = verdict.matches[0]
        self.assertEqual(match.other_number, 101)
        self.assertEqual(match.direction, pure.DIRECTION_STACKED_ON)
        self.assertEqual(set(match.common_shas), {"q1", "q2"})

    def test_s07_equal_heads_no_direction_invented(self):
        verdict = evaluate_fixture("snapshot-s07.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_VIOLATION)
        self.assertEqual(verdict.matches[0].direction, pure.DIRECTION_EQUAL_HEADS)
        self.assertEqual(set(verdict.matches[0].common_shas), {"c1", "h"})

    def test_s08_partial_intersection_not_ignored(self):
        verdict = evaluate_fixture("snapshot-s08.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_VIOLATION)
        match = verdict.matches[0]
        self.assertEqual(match.other_number, 101)
        self.assertEqual(match.direction, pure.DIRECTION_PARTIAL)
        self.assertEqual(set(match.common_shas), {"q1", "q2", "q3"})

    def test_s09_symmetry_both_orders_same_pair(self):
        """Симметрия: одна пара, оба порядка — нарушение у обоих, без вины там,
        где направление не доказано (partial); у базы — base_for без инструкции."""
        base = load_snapshot("snapshot-s08.json")

        checked_child = pure.evaluate(pure.snapshot_from_dict(base))
        self.assertEqual(checked_child.outcome, pure.OUTCOME_VIOLATION)
        self.assertEqual(checked_child.matches[0].direction, pure.DIRECTION_PARTIAL)

        parent_view = pure.evaluate(pure.snapshot_from_dict(checked_from(base, 101)))
        self.assertEqual(parent_view.outcome, pure.OUTCOME_VIOLATION)
        self.assertEqual(parent_view.matches[0].other_number, 100)
        self.assertEqual(parent_view.matches[0].direction, pure.DIRECTION_PARTIAL)

        stack = load_snapshot("snapshot-s04.json")
        stack_base_view = pure.evaluate(pure.snapshot_from_dict(checked_from(stack, 101)))
        self.assertEqual(stack_base_view.outcome, pure.OUTCOME_VIOLATION)
        self.assertEqual(stack_base_view.matches[0].other_number, 100)
        self.assertEqual(stack_base_view.matches[0].direction, pure.DIRECTION_BASE_FOR)

    def test_s10_target_is_epic_branch_clean_with_note(self):
        verdict = evaluate_fixture("snapshot-s10.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_CLEAN)
        self.assertEqual(len(verdict.notes), 1)
        note = verdict.notes[0]
        self.assertEqual(note.kind, pure.NOTE_TARGETS_OTHER_OPEN_BRANCH)
        self.assertEqual(note.other_number, 101)

    def test_s11_cherry_pick_backport_clean(self):
        verdict = evaluate_fixture("snapshot-s11.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_CLEAN)

    def test_s12_incident_second_pr_stacked_on_first(self):
        """Обезличенный инцидент PR #409–#414, проверка второго (902).

        Замечание реализации: строка S12 матрицы ожидает у пятого PR `partial`
        относительно «первых», но при условиях той же строки (head(1) ∈ U(2) и
        вложенность U(k) ⊃ U(k−1)) транзитивность даёт head(1) ∈ U(5) — по
        формуле контракта (раздел 5) это stacked_on. Матрица и формула
        математически несовместимы; реализация следует формуле раздела 5.
        """
        snapshot = load_snapshot("snapshot-s12-second.json")
        verdict = pure.evaluate(pure.snapshot_from_dict(snapshot))
        self.assertEqual(verdict.outcome, pure.OUTCOME_VIOLATION)
        by_number = {match.other_number: match for match in verdict.matches}
        self.assertEqual(set(by_number), {901, 903, 904, 905})
        self.assertEqual(by_number[901].direction, pure.DIRECTION_STACKED_ON)
        self.assertEqual(set(by_number[901].common_shas), {"c1"})
        for number in (903, 904, 905):
            self.assertEqual(by_number[number].direction, pure.DIRECTION_BASE_FOR)
        self.assert_common_consistency(verdict, snapshot)

    def test_s12_incident_fifth_pr_directions(self):
        verdict = evaluate_fixture("snapshot-s12-fifth.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_VIOLATION)
        by_number = {match.other_number: match for match in verdict.matches}
        self.assertEqual(set(by_number), {901, 902, 903, 904})
        # Прямая база — доказанное stacked_on (требование матрицы S12).
        self.assertEqual(by_number[904].direction, pure.DIRECTION_STACKED_ON)
        # Дальние предки: по формуле раздела 5 их вершины входят в U(905)
        # (см. замечание в test_s12_incident_second_pr_stacked_on_first).
        for number in (901, 902, 903):
            self.assertEqual(by_number[number].direction, pure.DIRECTION_STACKED_ON)

    def test_s13_stack_across_targets_violation(self):
        verdict = evaluate_fixture("snapshot-s13.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_VIOLATION)
        self.assertEqual(verdict.matches[0].direction, pure.DIRECTION_STACKED_ON)

    def test_s14_same_sha_transfer_flags_deliberately(self):
        verdict = evaluate_fixture("snapshot-s14.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_VIOLATION)
        self.assertEqual(verdict.matches[0].direction, pure.DIRECTION_EQUAL_HEADS)

    def test_s15_truncation_monotonicity_violation_proven(self):
        verdict = evaluate_fixture("snapshot-s15.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_VIOLATION)
        match = verdict.matches[0]
        self.assertEqual(match.other_number, 101)
        self.assertEqual(match.direction, pure.DIRECTION_STACKED_ON)
        self.assertTrue(match.proven_on_truncated)

    def test_s16_truncation_without_intersection_inconclusive(self):
        verdict = evaluate_fixture("snapshot-s16.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_INCONCLUSIVE)
        self.assertEqual(verdict.matches, ())
        self.assertTrue(verdict.reason.startswith(pure.REASON_COMMITS_LIMIT))
        self.assertIn("100", verdict.reason)

    def test_s19_fork_pr_compared_by_sha_no_case_b(self):
        """source_branch форка случайно совпадает с целью — заметка не создаётся."""
        verdict = evaluate_fixture("snapshot-s19.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_CLEAN)
        self.assertEqual(verdict.notes, ())

    def test_s20_draft_pr_checked_equally(self):
        verdict = evaluate_fixture("snapshot-s20.json")
        self.assertEqual(verdict.outcome, pure.OUTCOME_VIOLATION)
        self.assertEqual(verdict.matches[0].direction, pure.DIRECTION_STACKED_ON)


class PureValidationTests(unittest.TestCase):
    """Схема снимка: строгая валидация ловит битые данные."""

    def test_commits_must_cover_all_prs(self):
        snapshot = load_snapshot("snapshot-s04.json")
        del snapshot["commits"]["101"]
        with self.assertRaises(pure.SnapshotError):
            pure.snapshot_from_dict(snapshot)

    def test_checked_pr_must_not_be_in_open_prs(self):
        snapshot = load_snapshot("snapshot-s04.json")
        snapshot["open_prs"].append(snapshot["checked_pr"])
        with self.assertRaises(pure.SnapshotError):
            pure.snapshot_from_dict(snapshot)

    def test_truncated_outside_snapshot_rejected(self):
        snapshot = load_snapshot("snapshot-s01.json")
        snapshot["truncated"] = [999]
        with self.assertRaises(pure.SnapshotError):
            pure.snapshot_from_dict(snapshot)


class CollectTests(unittest.TestCase):
    """Слой Collect на заглушке API: пагинация, усечение, повторы, гонки."""

    REPO = "example/repo"

    def _api_pr(self, number, head, source, target, draft=False, fork=False):
        head_repo = None if fork else {"full_name": self.REPO}
        return {
            "number": number,
            "state": "open",
            "draft": draft,
            "head": {"sha": head, "ref": source, "repo": head_repo},
            "base": {"ref": target},
        }

    def test_successful_collection_with_pagination(self):
        api = ScriptedApi()
        api.given(
            f"repos/{self.REPO}/pulls/7",
            self._api_pr(7, "p2", "task/feat", "main"),
        )
        api.given(f"repos/{self.REPO}/branches/main", {"name": "main", "commit": {"sha": "m9"}})
        api.given(
            f"repos/{self.REPO}/pulls?state=open&per_page=100&page=1",
            [self._api_pr(7, "p2", "task/feat", "main")] + [self._api_pr(101 + i, f"b{i}", f"task/b{i}", "main") for i in range(99)],
        )
        api.given(
            f"repos/{self.REPO}/pulls?state=open&per_page=100&page=2",
            [self._api_pr(201, "z1", "task/z", "main", fork=True)],
        )
        api.given(f"repos/{self.REPO}/pulls?state=open&per_page=100&page=3", [])
        api.given(
            f"repos/{self.REPO}/pulls/7/commits?per_page=100&page=1",
            [{"sha": "p1"}, {"sha": "p2"}],
        )
        api.given(
            f"repos/{self.REPO}/pulls/201/commits?per_page=100&page=1",
            [{"sha": "z1"}],
        )
        for i in range(99):
            number = 101 + i
            api.given(
                f"repos/{self.REPO}/pulls/{number}/commits?per_page=100&page=1",
                [{"sha": f"b{i}"}],
            )

        snapshot_dict, collected_at = collect.collect_snapshot(self.REPO, 7, api, sleep=lambda _: None)
        snapshot = pure.snapshot_from_dict(snapshot_dict)

        self.assertEqual(snapshot.checked_pr.number, 7)
        self.assertEqual(snapshot.target_tip, "m9")
        self.assertEqual(len(snapshot.open_prs), 100)
        self.assertEqual(snapshot.commits[7], ("p1", "p2"))
        fork_pr = next(pr for pr in snapshot.open_prs if pr.number == 201)
        self.assertFalse(fork_pr.same_repository)
        self.assertEqual(fork_pr.source_branch, "task/z")
        self.assertTrue(collected_at)

    def test_truncated_at_250_commits(self):
        api = ScriptedApi()
        api.given(f"repos/{self.REPO}/pulls/9", self._api_pr(9, "h", "task/big", "main"))
        api.given(f"repos/{self.REPO}/branches/main", {"commit": {"sha": "m1"}})
        api.given(f"repos/{self.REPO}/pulls?state=open&per_page=100&page=1", [])
        hundred = [{"sha": f"s{i:03d}"} for i in range(100)]
        fifty = [{"sha": f"s{i:03d}"} for i in range(100, 150)]
        api.given(f"repos/{self.REPO}/pulls/9/commits?per_page=100&page=1", hundred)
        api.given(f"repos/{self.REPO}/pulls/9/commits?per_page=100&page=2", hundred)
        api.given(f"repos/{self.REPO}/pulls/9/commits?per_page=100&page=3", fifty)

        snapshot_dict, _ = collect.collect_snapshot(self.REPO, 9, api, sleep=lambda _: None)
        snapshot = pure.snapshot_from_dict(snapshot_dict)

        self.assertEqual(len(snapshot.commits[9]), 250)
        self.assertEqual(snapshot.truncated, frozenset({9}))
        # Монотонность усечения: проверяемый усечён без пересечений → inconclusive.
        verdict = pure.evaluate(snapshot)
        self.assertEqual(verdict.outcome, pure.OUTCOME_INCONCLUSIVE)

    def test_s17_api_error_exhausts_retries_inconclusive(self):
        api = ScriptedApi()
        api.given(f"repos/{self.REPO}/pulls/17", collect.TransientApiError("503 Service Unavailable"))
        delays: list[float] = []

        with self.assertRaises(collect.PermanentCollectError) as ctx:
            collect.collect_snapshot(self.REPO, 17, api, sleep=delays.append)

        self.assertEqual(ctx.exception.reason, pure.REASON_API_ERROR)
        # Сетка контракта 2/10/30 с — нарастающие паузы МЕЖДУ попытками; попыток
        # три, значит пауз две (после последней попытки повторов нет).
        self.assertEqual(delays, list(collect.RETRY_DELAYS_SECONDS[: collect.MAX_ATTEMPTS - 1]))
        self.assertEqual(
            api.calls.count(f"repos/{self.REPO}/pulls/17"),
            collect.MAX_ATTEMPTS,
            "после трёх неудачных попыток запросы прекращаются",
        )

    def test_s18_state_changes_during_collection_inconclusive(self):
        """Вершина цели движется между сбором и контрольной точкой — 3 расхождения."""
        api = ScriptedApi()
        api.given(f"repos/{self.REPO}/pulls/18", self._api_pr(18, "p1", "task/feat", "main"))
        # Попытка 1: сбор m1 → контроль m2; попытка 2: m3 → m4; попытка 3: m5 → m6.
        api.given(
            f"repos/{self.REPO}/branches/main",
            *[{"commit": {"sha": tip}} for tip in ("m1", "m2", "m3", "m4", "m5", "m6")],
        )
        api.given(f"repos/{self.REPO}/pulls?state=open&per_page=100&page=1", [])
        api.given(f"repos/{self.REPO}/pulls/18/commits?per_page=100&page=1", [{"sha": "p1"}])

        with self.assertRaises(collect.PermanentCollectError) as ctx:
            collect.collect_snapshot(self.REPO, 18, api, sleep=lambda _: None)

        self.assertEqual(ctx.exception.reason, pure.REASON_STATE_CHANGED)
        self.assertEqual(api.calls.count(f"repos/{self.REPO}/branches/main"), 6)

    def test_closed_pr_is_permanent_error(self):
        api = ScriptedApi()
        closed = self._api_pr(5, "p1", "task/feat", "main")
        closed["state"] = "closed"
        api.given(f"repos/{self.REPO}/pulls/5", closed)

        with self.assertRaises(collect.PermanentCollectError) as ctx:
            collect.collect_snapshot(self.REPO, 5, api, sleep=lambda _: None)
        self.assertEqual(ctx.exception.reason, pure.REASON_PR_NOT_OPEN)

    def test_invalid_repo_rejected_without_requests(self):
        with self.assertRaises(collect.PermanentCollectError):
            collect.collect_snapshot("../bad repo", 1, ScriptedApi(), sleep=lambda _: None)


class CliTests(unittest.TestCase):
    """CLI: режимы inform/enforce, коды возврата, сообщения, точка входа bin."""

    def _report_file(self, fixture: str) -> str:
        with tempfile.NamedTemporaryFile(
            mode="w", suffix=".json", delete=False, encoding="utf-8"
        ) as handle:
            json.dump({"snapshot": load_snapshot(fixture)}, handle)
            return handle.name

    def _detect(self, fixture: str, mode: str) -> tuple[int, dict | None, str]:
        report_path = self._report_file(fixture)
        with tempfile.NamedTemporaryFile(suffix=".json", delete=False) as output:
            output_path = output.name
        buffer = io.StringIO()
        try:
            with redirect_stdout(buffer):
                code = cli.main(["detect", "--snapshot", report_path, "--mode", mode, "--output", output_path])
            with open(output_path, encoding="utf-8") as handle:
                report = json.load(handle)
        finally:
            Path(report_path).unlink(missing_ok=True)
            Path(output_path).unlink(missing_ok=True)
        return code, report, buffer.getvalue()

    def test_violation_inform_is_green_with_comment_payload(self):
        code, report, output = self._detect("snapshot-s04.json", "inform")
        self.assertEqual(code, cli.EXIT_OK)
        self.assertEqual(report["verdict"]["outcome"], pure.OUTCOME_VIOLATION)
        self.assertIn("violation", output)
        body = cli.build_pr_comment(report, rule_link="AGENTS.md")
        self.assertTrue(body.startswith(cli.COMMENT_MARKER))
        self.assertIn("PR #101", body)
        self.assertIn("AGENTS.md", body)
        self.assertIn("Переделайте ветку от целевой", body)
        self.assertIn("снимок", body)

    def test_violation_enforce_fails_job(self):
        code, report, _ = self._detect("snapshot-s04.json", "enforce")
        self.assertEqual(code, cli.EXIT_VIOLATION_ENFORCE)
        self.assertEqual(report["verdict"]["outcome"], pure.OUTCOME_VIOLATION)

    def test_clean_is_green(self):
        code, report, _ = self._detect("snapshot-s01.json", "inform")
        self.assertEqual(code, cli.EXIT_OK)
        self.assertEqual(report["verdict"]["outcome"], pure.OUTCOME_CLEAN)

    def test_inconclusive_is_not_green(self):
        code, report, _ = self._detect("snapshot-s16.json", "inform")
        self.assertEqual(code, cli.EXIT_INCONCLUSIVE)
        self.assertEqual(report["verdict"]["outcome"], pure.OUTCOME_INCONCLUSIVE)
        body = cli.build_pr_comment(report, rule_link="AGENTS.md")
        self.assertIn("проверка не выполнена", body)
        self.assertIn("250", body)

    def test_clean_with_note_carries_information(self):
        code, report, _ = self._detect("snapshot-s10.json", "inform")
        self.assertEqual(code, cli.EXIT_OK)
        self.assertEqual(report["verdict"]["outcome"], pure.OUTCOME_CLEAN)
        self.assertEqual(len(report["verdict"]["notes"]), 1)
        body = cli.build_pr_comment(report, rule_link="AGENTS.md")
        self.assertIn("заметка", body)
        self.assertIn("#101", body)
        self.assertIn("ветка эпика", body)

    def test_violation_without_proven_direction_has_no_rebase_instruction(self):
        code, report, _ = self._detect("snapshot-s08.json", "inform")
        self.assertEqual(code, cli.EXIT_OK)
        body = cli.build_pr_comment(report, rule_link="AGENTS.md")
        self.assertIn("виновная ветка не назначается", body)
        self.assertNotIn("Переделайте ветку", body)

    def _comment_report(self, fixture: str) -> str:
        verdict = pure.evaluate(pure.snapshot_from_dict(load_snapshot(fixture)))
        with tempfile.NamedTemporaryFile(mode="w", suffix=".json", delete=False, encoding="utf-8") as handle:
            json.dump({"verdict": pure.verdict_to_dict(verdict), "meta": {}}, handle)
            return handle.name

    def test_comment_edits_existing_marker_comment(self):
        """Раздел 13: параллельные запуски редактируют один комментарий по маркеру."""
        api = ScriptedApi()
        api.given(
            "repos/example/repo/issues/42/comments?per_page=100&page=1",
            [
                {"id": 11, "body": "произвольный чужой комментарий"},
                {"id": 12, "body": cli.COMMENT_MARKER + "\n\nстарый текст"},
            ],
        )
        api.given("PATCH repos/example/repo/issues/comments/12", {"id": 12})
        report_path = self._comment_report("snapshot-s04.json")
        try:
            with redirect_stdout(io.StringIO()) as buffer:
                code = cli.main(
                    ["comment", "--repo", "example/repo", "--pr", "42", "--report", report_path],
                    api=api,
                )
        finally:
            Path(report_path).unlink(missing_ok=True)
        self.assertEqual(code, cli.EXIT_OK)
        self.assertIn("обновлён", buffer.getvalue())
        self.assertIn("PATCH repos/example/repo/issues/comments/12", api.calls)
        self.assertNotIn("POST repos/example/repo/issues/42/comments", api.calls)

    def test_comment_creates_new_when_marker_absent(self):
        api = ScriptedApi()
        api.given("repos/example/repo/issues/42/comments?per_page=100&page=1", [])
        api.given("POST repos/example/repo/issues/42/comments", {"id": 55})
        report_path = self._comment_report("snapshot-s10.json")
        try:
            with redirect_stdout(io.StringIO()) as buffer:
                code = cli.main(
                    ["comment", "--repo", "example/repo", "--pr", "42", "--report", report_path],
                    api=api,
                )
        finally:
            Path(report_path).unlink(missing_ok=True)
        self.assertEqual(code, cli.EXIT_OK)
        self.assertIn("опубликован", buffer.getvalue())
        self.assertIn("POST repos/example/repo/issues/42/comments", api.calls)

    def test_mode_from_environment(self):
        with tempfile.NamedTemporaryFile(mode="w", suffix=".json", delete=False) as handle:
            json.dump({"snapshot": load_snapshot("snapshot-s04.json")}, handle)
            path = handle.name
        import os

        os.environ["DETECTOR_MODE"] = "enforce"
        try:
            with redirect_stdout(io.StringIO()):
                code = cli.main(["detect", "--snapshot", path])
        finally:
            del os.environ["DETECTOR_MODE"]
            Path(path).unlink(missing_ok=True)
        self.assertEqual(code, cli.EXIT_VIOLATION_ENFORCE)

    def test_bin_entrypoint_requires_repo_and_pr(self):
        proc = subprocess.run(
            [sys.executable, str(ROOT / "bin" / "stacked-pr-detector"), "detect"],
            capture_output=True,
            text=True,
            check=False,
        )
        self.assertEqual(proc.returncode, cli.EXIT_USAGE)
        self.assertIn("--repo", proc.stderr)

    def test_bin_entrypoint_runs_detection_from_snapshot(self):
        report_path = self._report_file("snapshot-s04.json")
        try:
            proc = subprocess.run(
                [
                    sys.executable,
                    str(ROOT / "bin" / "stacked-pr-detector"),
                    "detect",
                    "--snapshot",
                    report_path,
                ],
                capture_output=True,
                text=True,
                check=False,
            )
        finally:
            Path(report_path).unlink(missing_ok=True)
        self.assertEqual(proc.returncode, cli.EXIT_OK, proc.stderr)
        self.assertIn("violation", proc.stdout)


if __name__ == "__main__":
    unittest.main()
