# Политика релизов task-orchestrator

## Источник релиза

Каждый релиз task-orchestrator готовится и публикуется **только из `main`**. Ветка `main` обязана содержать состав и историю всех предыдущих релизов: код, `CHANGELOG.md`, release plans (планы релизов) и завершённые задачи.

## Подготовка

1. От актуального `main` создаётся ветка `task/release-vX-Y-Z`.
2. В ней обновляются `CHANGELOG.md`, `docs/releases/vX.Y.Z/release-plan.md` и задача подготовки в `todo/`.
3. Pull Request (запрос на слияние) направляется в `main` и проходит обязательные проверки и review (ревью).
4. После merge (слияния) tag `vX.Y.Z` создаётся на вершине `main`.

## Hotfix

Hotfix (срочное исправление) начинается от текущего production tag, но после проверки всегда возвращается в `main` через Pull Request. Следующий patch tag публикуется с этой вершины `main`.

Эта проектная политика уточняет общий гайд `docs/git-workflow/release.md`, который поставляется зависимостью `prikotov/git-workflow`.
