.PHONY: help setup install test lint shell run clean rebuild build-wasm serve-wasm wasm-info

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

build-wasm: ## Build WebAssembly version for browser (Step 15)
	@echo "Building PHPBoy for WebAssembly..."
	@echo ""
	@echo "⚠️  WASM Build Prerequisites:"
	@echo "   1. Install Emscripten SDK: https://emscripten.org/docs/getting_started/downloads.html"
	@echo "   2. Install php-wasm builder: npm install -g php-wasm-builder (if available)"
	@echo "   3. Or use seanmorris/php-wasm: https://github.com/seanmorris/php-wasm"
	@echo ""
	@echo "📦 Build Steps (to be implemented):"
	@echo "   1. Compile PHP 8.3+ to WebAssembly using Emscripten"
	@echo "   2. Bundle PHPBoy PHP source files"
	@echo "   3. Generate php-wasm.js loader"
	@echo "   4. Copy web/ assets to dist/"
	@echo "   5. Create dist/ directory with all browser files"
	@echo ""
	@echo "📁 Expected output: dist/"
	@echo "   - dist/php-wasm.js       (PHP WASM runtime)"
	@echo "   - dist/php-wasm.wasm     (PHP interpreter binary)"
	@echo "   - dist/index.html        (Web UI)"
	@echo "   - dist/styles.css        (Styles)"
	@echo "   - dist/js/phpboy.js      (Emulator bridge)"
	@echo "   - dist/js/app.js         (UI controller)"
	@echo "   - dist/phpboy/           (PHP source files)"
	@echo ""
	@echo "📖 See docs/wasm-build.md for detailed build instructions"
	@echo ""
	@mkdir -p dist
	@cp -r web/* dist/
	@mkdir -p dist/phpboy
	@cp -r src dist/phpboy/
	@cp -r vendor dist/phpboy/ 2>/dev/null || echo "Note: Run 'make install' first to include vendor/"
	@echo ""
	@echo "✅ Static files copied to dist/"
	@echo "⚠️  PHP WASM compilation not yet implemented - see docs/wasm-build.md"

serve-wasm: ## Serve WebAssembly build locally (requires Python 3)
	@echo "Starting local web server for PHPBoy WASM..."
	@echo ""
	@echo "🌐 Open browser to: http://localhost:8000"
	@echo "Press Ctrl+C to stop"
	@echo ""
	@cd dist && python3 -m http.server 8000

wasm-info: ## Show information about WebAssembly build setup
	@echo "PHPBoy WebAssembly Build Information"
	@echo "======================================"
	@echo ""
	@echo "Current Status: Infrastructure complete, awaiting WASM compiler"
	@echo ""
	@echo "✅ Completed Components:"
	@echo "  - WasmFramebuffer implementation (src/Frontend/Wasm/WasmFramebuffer.php)"
	@echo "  - WasmInput implementation (src/Frontend/Wasm/WasmInput.php)"
	@echo "  - BufferSink for audio (src/Apu/Sink/BufferSink.php)"
	@echo "  - JavaScript bridge (web/js/phpboy.js)"
	@echo "  - Web UI (web/index.html, web/styles.css, web/js/app.js)"
	@echo "  - Build target (make build-wasm)"
	@echo ""
	@echo "⏳ Pending:"
	@echo "  - PHP to WASM compilation setup"
	@echo "  - WASM module loading and integration"
	@echo "  - Browser testing with actual ROM"
	@echo ""
	@echo "📖 Documentation:"
	@echo "  - docs/wasm-options.md     - Research on PHP-to-WASM options"
	@echo "  - docs/wasm-build.md       - Build instructions (to be created)"
	@echo "  - docs/browser-usage.md    - Browser usage guide (to be created)"
	@echo ""
	@echo "🎯 Recommended Approach:"
	@echo "  Use seanmorris/php-wasm (WordPress Playground approach)"
	@echo "  See: https://github.com/seanmorris/php-wasm"
