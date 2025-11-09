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

test-roms: ## Run test ROM suite (Blargg, Mooneye, etc.)
	docker compose run --rm phpboy vendor/bin/phpunit --testsuite=Integration --no-coverage

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

profile: ## Run emulator with Xdebug profiling (usage: make profile ROM=path/to/rom.gb FRAMES=1000)
	@if [ -z "$(ROM)" ]; then \
		echo "Error: ROM parameter is required. Usage: make profile ROM=path/to/rom.gb FRAMES=1000"; \
		exit 1; \
	fi
	@mkdir -p var/profiling
	docker compose run --rm \
		-e XDEBUG_MODE=profile \
		-e XDEBUG_CONFIG="profiler_enable=1 profiler_output_dir=/app/var/profiling" \
		phpboy php bin/phpboy.php $(ROM) --headless --frames=$(or $(FRAMES),1000)
	@echo "Profile data saved to var/profiling/"
	@echo "Open with: kcachegrind var/profiling/cachegrind.out.*"

benchmark: ## Run performance benchmark (usage: make benchmark ROM=path/to/rom.gb FRAMES=3600)
	@if [ -z "$(ROM)" ]; then \
		echo "Error: ROM parameter is required. Usage: make benchmark ROM=path/to/rom.gb FRAMES=3600"; \
		exit 1; \
	fi
	@echo "Running benchmark with $(or $(FRAMES),3600) frames..."
	docker compose run --rm phpboy php bin/phpboy.php $(ROM) --headless --frames=$(or $(FRAMES),3600) --benchmark

benchmark-jit: ## Run benchmark with JIT enabled (usage: make benchmark-jit ROM=path/to/rom.gb FRAMES=3600)
	@if [ -z "$(ROM)" ]; then \
		echo "Error: ROM parameter is required. Usage: make benchmark-jit ROM=path/to/rom.gb FRAMES=3600"; \
		exit 1; \
	fi
	@echo "Running benchmark with JIT enabled ($(or $(FRAMES),3600) frames)..."
	docker compose run --rm \
		-e PHP_INI_SCAN_DIR=/usr/local/etc/php/conf.d:/app/docker/php-jit \
		phpboy php -d opcache.jit_buffer_size=100M -d opcache.jit=tracing \
		bin/phpboy.php $(ROM) --headless --frames=$(or $(FRAMES),3600) --benchmark

memory-profile: ## Run with memory profiling (usage: make memory-profile ROM=path/to/rom.gb FRAMES=1000)
	@if [ -z "$(ROM)" ]; then \
		echo "Error: ROM parameter is required. Usage: make memory-profile ROM=path/to/rom.gb FRAMES=1000"; \
		exit 1; \
	fi
	docker compose run --rm phpboy php -d memory_limit=512M bin/phpboy.php $(ROM) --headless --frames=$(or $(FRAMES),1000) --memory-profile
