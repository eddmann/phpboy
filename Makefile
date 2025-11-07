.PHONY: help setup install test lint shell run clean

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

setup: ## Build Docker image (or verify environment in CI/containerized environments)
	@echo "Environment check:"
	@php --version
	@composer --version
	@echo "Setup complete!"

install: ## Install PHP dependencies via Composer
	composer install

test: ## Run PHPUnit tests
	vendor/bin/phpunit

lint: ## Run PHPStan static analysis
	vendor/bin/phpstan analyse

shell: ## Open bash shell (for Docker compatibility)
	@bash

run: ## Run emulator with ROM (usage: make run ROM=path/to/rom.gb)
	@if [ -z "$(ROM)" ]; then \
		echo "Error: ROM parameter is required. Usage: make run ROM=path/to/rom.gb"; \
		exit 1; \
	fi
	php bin/phpboy.php $(ROM)

clean: ## Remove vendor directory and composer.lock
	rm -rf vendor composer.lock
