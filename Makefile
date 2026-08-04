# --- Makefile for timurturdyev/simple-cart (Laravel package) ---
# Usage: make [target]

SHELL := bash
.ONESHELL:
.SHELLFLAGS := -eu -o pipefail -c
.DELETE_ON_ERROR:
MAKEFLAGS += --warn-undefined-variables
MAKEFLAGS += --no-builtin-rules

# --- Project ---
PROJECT  ?= simple-cart
PHP      ?= php
COMPOSER ?= composer
PHPUNIT  ?= ./vendor/bin/phpunit

# ============================================================================
.DEFAULT_GOAL := help

##@ Development

.PHONY: install
install: ## Install dependencies
	$(COMPOSER) install

.PHONY: update
update: ## Update dependencies
	$(COMPOSER) update

##@ Testing

.PHONY: test
test: ## Run tests
	$(PHPUNIT)

.PHONY: test-cover
test-cover: ## Run tests with coverage report (requires Xdebug or PCOV)
	$(PHPUNIT) --coverage-html coverage/ --coverage-text
	@echo "Coverage report: coverage/index.html"

.PHONY: test-filter
test-filter: ## Run filtered tests (usage: make test-filter FILTER="ClassName::testMethod")
	$(PHPUNIT) --filter="$(FILTER)"

##@ Code Quality

.PHONY: lint
lint: ## Syntax-check all PHP files in src/ and tests/
	@find src tests config -name '*.php' -print0 | xargs -0 -n1 -P4 $(PHP) -l > /dev/null
	@echo "Syntax OK"

.PHONY: validate
validate: ## Validate composer.json
	$(COMPOSER) validate --strict

.PHONY: check
check: lint validate test ## Run all quality checks

##@ CI

.PHONY: ci
ci: install check ## Run full CI pipeline locally

##@ Cleanup

.PHONY: clean
clean: ## Remove generated files and caches
	rm -rf vendor/ coverage/ .phpunit.result.cache .phpunit.cache

##@ Help

.PHONY: help
help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "Usage:\n  make \033[36m<target>\033[0m\n"} \
		/^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2} \
		/^##@/ {printf "\n\033[1m%s\033[0m\n", substr($$0, 5)}' $(MAKEFILE_LIST)
