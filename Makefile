.PHONY: help setup install test lint shell run clean rebuild build-wasm serve-wasm check-sdl install-sdl run-sdl run-sdl-host

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

build-wasm: ## Build WASM distribution for browser
	@echo "Building PHPBoy for WebAssembly..."
	@if [ ! -d "vendor" ]; then \
		echo "Error: vendor directory not found. Run 'make install' first."; \
		exit 1; \
	fi
	@mkdir -p dist/php
	@echo "Copying web files..."
	@cp -r web/* dist/
	@echo "Copying PHP source..."
	@cp -r src dist/php/
	@cp composer.json dist/php/
	@echo "Copying vendor directory..."
	@cp -r vendor dist/php/
	@echo "Build complete! Output in dist/"
	@echo ""
	@echo "To serve locally:"
	@echo "  cd dist && python3 -m http.server 8080"
	@echo "  or"
	@echo "  npm install && npm run serve"

serve-wasm: ## Serve WASM build locally (requires Python 3)
	@if [ ! -d "dist" ]; then \
		echo "Error: dist directory not found. Run 'make build-wasm' first."; \
		exit 1; \
	fi
	@echo "Starting HTTP server on http://localhost:8080"
	@cd dist && python3 -m http.server 8080

# SDL2 Native Frontend Targets

check-sdl: ## Check if SDL2 extension is installed
	@echo "Checking SDL2 extension..."
	@php -m | grep -q sdl && echo "✓ SDL2 extension is installed" || (echo "✗ SDL2 extension not found. See docs/sdl2-setup.md for installation." && exit 1)
	@echo "SDL2 version: $$(php -r 'echo SDL_GetVersion();')"

install-sdl: ## Install SDL2 PHP extension (requires sudo)
	@echo "Installing SDL2 PHP extension..."
	@echo "This requires SDL2 development libraries to be installed first."
	@echo ""
	@echo "Ubuntu/Debian:"
	@echo "  sudo apt-get install libsdl2-dev"
	@echo ""
	@echo "macOS:"
	@echo "  brew install sdl2"
	@echo ""
	@echo "After installing SDL2 libraries, run:"
	@echo "  sudo pecl install sdl-beta"
	@echo ""
	@echo "Then add 'extension=sdl.so' to your php.ini"
	@echo ""
	@echo "See docs/sdl2-setup.md for detailed instructions."

run-sdl: ## Run emulator with SDL2 native frontend in Docker (usage: make run-sdl ROM=path/to/rom.gb)
	@if [ -z "$(ROM)" ]; then \
		echo "Error: ROM parameter is required. Usage: make run-sdl ROM=path/to/rom.gb"; \
		exit 1; \
	fi
	@echo "Note: SDL2 GUI applications typically work better on host. Try: make run-sdl-host ROM=$(ROM)"
	docker compose run --rm -e DISPLAY=$$DISPLAY -v /tmp/.X11-unix:/tmp/.X11-unix phpboy php bin/phpboy.php $(ROM) --frontend=sdl

run-sdl-host: ## Run emulator with SDL2 on host (not in Docker) (usage: make run-sdl-host ROM=path/to/rom.gb)
	@if [ -z "$(ROM)" ]; then \
		echo "Error: ROM parameter is required. Usage: make run-sdl-host ROM=path/to/rom.gb"; \
		exit 1; \
	fi
	@echo "Running SDL2 frontend on host..."
	@php -m | grep -q sdl || (echo "Error: SDL2 extension not installed. Run 'make install-sdl' or see docs/sdl2-setup.md" && exit 1)
	php bin/phpboy.php $(ROM) --frontend=sdl

test-sdl: ## Test SDL2 installation with simple window
	@echo "Testing SDL2 installation..."
	@php -m | grep -q sdl || (echo "Error: SDL2 extension not installed." && exit 1)
	@php -r '\
		if (!extension_loaded("sdl")) { die("SDL not loaded\n"); } \
		SDL_Init(SDL_INIT_VIDEO); \
		$$w = SDL_CreateWindow("SDL Test", SDL_WINDOWPOS_CENTERED, SDL_WINDOWPOS_CENTERED, 640, 480, SDL_WINDOW_SHOWN); \
		if ($$w) { echo "✓ SDL2 working! Window created.\n"; sleep(2); SDL_DestroyWindow($$w); } \
		else { echo "✗ Failed: " . SDL_GetError() . "\n"; } \
		SDL_Quit(); \
	'
