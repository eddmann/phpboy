.PHONY: help setup install test lint shell run clean rebuild

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

setup: ## Build Docker image with PHP 8.5 RC4
	docker compose build

rebuild: ## Rebuild Docker image from scratch (no cache)
	docker compose build --no-cache

install: ## Install PHP dependencies via Composer in Docker
	docker compose run --rm phpboy composer install

test: ## Run PHPUnit tests in Docker
	docker compose run --rm phpboy vendor/bin/phpunit

lint: ## Run PHPStan static analysis in Docker
	docker compose run --rm phpboy php -d memory_limit=512M vendor/bin/phpstan analyse

shell: ## Open bash shell in Docker container
	docker compose run --rm phpboy bash

run: ## Run emulator with ROM in Docker (usage: make run ROM=path/to/rom.gb)
	@if [ -z "$(ROM)" ]; then \
		echo "Error: ROM parameter is required. Usage: make run ROM=path/to/rom.gb"; \
		exit 1; \
	fi
	docker compose run --rm phpboy php bin/phpboy.php $(ROM)

debug: ## Run emulator in debug mode (usage: make debug ROM=path/to/rom.gb)
	@if [ -z "$(ROM)" ]; then \
		echo "Error: ROM parameter is required. Usage: make debug ROM=path/to/rom.gb"; \
		exit 1; \
	fi
	docker compose run --rm phpboy php bin/phpboy.php $(ROM) --debug

trace: ## Run emulator with CPU trace (usage: make trace ROM=path/to/rom.gb)
	@if [ -z "$(ROM)" ]; then \
		echo "Error: ROM parameter is required. Usage: make trace ROM=path/to/rom.gb"; \
		exit 1; \
	fi
	docker compose run --rm phpboy php bin/phpboy.php $(ROM) --trace --headless

clean: ## Remove vendor directory and composer.lock
	rm -rf vendor composer.lock

clean-docker: ## Remove Docker containers and images
	docker compose down --rmi all --volumes
