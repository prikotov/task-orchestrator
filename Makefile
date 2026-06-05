.DEFAULT_GOAL := help

.PHONY: help
help: ## Показать помощь
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z0-9_-]+:.*?## / {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

####################
# Статический анализ #
####################

.PHONY: deptrac
deptrac: ## Запустить анализ зависимостей
	@echo
	@echo "Deptrac:"
	@out=$$(vendor/bin/deptrac analyse --config-file=depfile.yaml --no-progress --no-ansi 2>&1); ec=$$?; echo "$$out" | grep -vE '^[[:space:]]*$$'; exit $$ec

.PHONY: phpstan
phpstan: ## Запустить PHPStan
	@echo
	@echo "PHPStan:"
	@vendor/bin/phpstan analyse --no-progress 2>&1; ec=$$?; if [ "$$ec" -eq 0 ]; then echo "PHPStan: OK"; fi; exit $$ec

.PHONY: psalm
psalm: ## Запустить статический анализ Psalm
	@echo
	@out=$$(vendor/bin/psalm --no-cache --no-progress --output-format=compact --monochrome 2>&1); ec=$$?; if [ "$$ec" -eq 0 ]; then echo "Psalm: OK"; else echo "$$out" | grep -vE '^(Running custom Psalm bootstrap|[[:space:]]*$$)'; fi; exit $$ec

.PHONY: phpmd
phpmd: ## Запустить PHP Mess Detector
	@echo
	@echo "PHPMD:"
	@vendor/bin/phpmd analyze src --format=text --ruleset=phpmd.xml --baseline-file=phpmd.baseline.xml && echo "No violations."

.PHONY: tests-unit
tests-unit: ## Запустить unit-тесты
	@echo
	@echo "PHPUnit (unit):"
	@out=$$(vendor/bin/phpunit --no-progress --no-coverage --colors=never tests/Unit/ 2>&1); ec=$$?; echo "$$out" | grep -vE '^(PHPUnit |Runtime:|Configuration:|Time:|[[:space:]]*$$)'; exit $$ec

.PHONY: tests-integration
tests-integration: ## Запустить integration-тесты
	@echo
	@echo "PHPUnit (integration):"
	@out=$$(vendor/bin/phpunit --no-progress --no-coverage --colors=never tests/Integration/ 2>&1); ec=$$?; echo "$$out" | grep -vE '^(PHPUnit |Runtime:|Configuration:|Time:|[[:space:]]*$$)'; exit $$ec

.PHONY: tests
tests: ## Запустить все тесты
	@echo
	@echo "PHPUnit (all):"
	@out=$$(vendor/bin/phpunit --no-progress --no-coverage --colors=never 2>&1); ec=$$?; echo "$$out" | grep -vE '^(PHPUnit |Runtime:|Configuration:|Time:|[[:space:]]*$$)'; exit $$ec

############
# Проверки  #
############

.PHONY: phpcs
phpcs: ## Запустить PHP_CodeSniffer
	@echo
	@echo "PHPCS:"
	@vendor/bin/phpcs --standard=phpcs.xml.dist --no-colors -n src/ 2>&1; ec=$$?; if [ "$$ec" -eq 0 ]; then echo "PHPCS: OK"; fi; exit $$ec

.PHONY: md-links
md-links: ## Валидация внутренних ссылок в Markdown
	@echo
	@echo "MD-Links:"
	@php vendor/prikotov/coding-standard/bin/validate-md-links

.PHONY: validate-todo
validate-todo: ## Валидация задач todo-md (только активные)
	@echo
	@echo "Validate-Todo:"
	@files=$$(find todo/ -maxdepth 1 -name '*.todo.md' -o -name 'EPIC-*.md' 2>/dev/null); \
	if [ -z "$$files" ]; then \
		echo "  No active task files found in todo/. Skipping."; \
	else \
		for f in $$files; do \
			php vendor/prikotov/todo-md/bin/todo-md-validate "$$f"; \
		done; \
	fi

.PHONY: validate-roles
validate-roles: ## Валидация файлов ролей AI-агентов
	@echo
	@echo "Validate-Roles:"
	@php bin/validate-roles

.PHONY: phar-smoke
phar-smoke: ## Собрать Phar через Box и проверить запуск --version
	@echo
	@echo "Phar smoke:"
	@bin/phar-smoke

.PHONY: check
check: ## Запустить все проверки (phpstan + deptrac + psalm + phpmd + phpcs + md-links + validate-todo + validate-roles + tests)
	@${MAKE} --no-print-directory phpstan deptrac psalm phpmd phpcs md-links validate-todo validate-roles tests && \
		{ echo; echo "✅ Все проверки завершены успешно."; } || \
		{ echo; echo "❌ Проверки завершены с ошибками."; exit 1; }
