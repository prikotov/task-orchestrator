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

.PHONY: psalm
psalm: ## Запустить статический анализ
	@echo
	@out=$$(vendor/bin/psalm --no-cache --no-progress --output-format=compact --monochrome 2>&1); ec=$$?; if [ "$$ec" -eq 0 ]; then echo "Psalm: OK"; else echo "$$out" | grep -vE '^(Running custom Psalm bootstrap|[[:space:]]*$$)'; fi; exit $$ec

.PHONY: phpmd
phpmd: ## Запустить PHP Mess Detector
	@echo
	@echo "PHPMD:"
	@vendor/bin/phpmd analyze src --format=text --ruleset=phpmd.xml --baseline-file=phpmd.baseline.xml && echo "No violations."

.PHONY: phpmd-full
phpmd-full: ## Запустить PHPMD без baseline
	@echo
	@echo "PHPMD (full):"
	@vendor/bin/phpmd analyze src --format=text --ruleset=phpmd.xml

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

.PHONY: check
check: ## Запустить все проверки (deptrac + psalm + phpmd + tests)
	@${MAKE} --no-print-directory deptrac psalm phpmd tests && \
		{ echo; echo "✅ Все проверки завершены успешно."; } || \
		{ echo; echo "❌ Проверки завершены с ошибками."; exit 1; }
