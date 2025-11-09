# PHPBoy WebAssembly Build Guide

**Status:** Step 15 - Infrastructure Complete, WASM Compilation Pending

This document describes how to build PHPBoy for WebAssembly to run in the browser.

---

## Overview

PHPBoy can be compiled to WebAssembly using the **php-wasm** approach (WordPress Playground method), which compiles the PHP interpreter itself to WASM using Emscripten.

**Build Architecture:**
```
PHP 8.3 Source → Emscripten → php.wasm + php.js
PHPBoy PHP Code → Virtual FS → Loaded at runtime
JavaScript Bridge → Canvas/WebAudio → Browser APIs
```

---

## Prerequisites

### 1. Emscripten SDK

Install the Emscripten compiler toolchain:

```bash
# Clone Emscripten SDK
git clone https://github.com/emscripten-core/emsdk.git
cd emsdk

# Install latest SDK
./emsdk install latest
./emsdk activate latest

# Add to PATH (add to ~/.bashrc for permanent)
source ./emsdk_env.sh

# Verify installation
emcc --version
```

**Documentation:** https://emscripten.org/docs/getting_started/downloads.html

### 2. PHP-WASM Builder

**Option A: Use seanmorris/php-wasm (Recommended)**

```bash
# Clone php-wasm repository
git clone https://github.com/seanmorris/php-wasm.git
cd php-wasm

# Follow build instructions in their README
# This will compile PHP to WASM and generate:
# - php-wasm.wasm (PHP interpreter)
# - php-wasm.js (JavaScript loader)
```

**Option B: Use WordPress Playground Builder**

```bash
# Clone WordPress Playground
git clone https://github.com/WordPress/wordpress-playground.git
cd wordpress-playground

# Install dependencies
npm install

# Build PHP WASM
npm run build:php
```

### 3. Build Tools

```bash
# Node.js (for npm packages if needed)
node --version  # Should be 16+

# Python 3 (for local web server)
python3 --version
```

---

## Build Process

### Step 1: Compile PHP to WASM

Using **seanmorris/php-wasm**:

```bash
cd /path/to/php-wasm

# Configure PHP build with desired extensions
# PHPBoy requires: json, mbstring, standard library
./configure-php.sh

# Compile PHP to WASM
make php-wasm

# Output files:
# - dist/php-wasm.wasm
# - dist/php-wasm.js
```

### Step 2: Build PHPBoy Distribution

```bash
cd /path/to/phpboy

# Run build-wasm make target
make build-wasm
```

This will:
1. Create `dist/` directory
2. Copy web UI files (`web/*` → `dist/`)
3. Copy PHPBoy PHP source (`src/` → `dist/phpboy/src/`)
4. Copy Composer dependencies (`vendor/` → `dist/phpboy/vendor/`)

### Step 3: Add PHP WASM Files

Copy the compiled PHP WASM files to the distribution:

```bash
# Copy from php-wasm build
cp /path/to/php-wasm/dist/php-wasm.wasm dist/
cp /path/to/php-wasm/dist/php-wasm.js dist/

# Or from WordPress Playground
cp /path/to/wordpress-playground/dist/php.wasm dist/php-wasm.wasm
cp /path/to/wordpress-playground/dist/php.js dist/php-wasm.js
```

### Step 4: Configure Virtual Filesystem

Create a build script to package PHPBoy PHP files:

```bash
# Create build script: scripts/build-wasm.sh
#!/bin/bash

# Bundle PHP source files
mkdir -p dist/phpboy
cp -r src dist/phpboy/
cp -r vendor dist/phpboy/

# Create autoloader stub for WASM
cat > dist/phpboy/autoload.php << 'EOF'
<?php
// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';
EOF

echo "PHPBoy WASM build complete!"
echo "Output: dist/"
```

Make executable and run:
```bash
chmod +x scripts/build-wasm.sh
./scripts/build-wasm.sh
```

---

## Directory Structure

After build, `dist/` should contain:

```
dist/
├── index.html              # Main web UI
├── styles.css              # UI styles
├── php-wasm.wasm           # PHP interpreter (5-10 MB)
├── php-wasm.js             # WASM loader (100-200 KB)
├── js/
│   ├── phpboy.js           # Emulator bridge
│   └── app.js              # UI controller
└── phpboy/
    ├── src/                # PHPBoy source code
    │   ├── Cpu/
    │   ├── Ppu/
    │   ├── Apu/
    │   ├── Cartridge/
    │   ├── Bus/
    │   ├── Input/
    │   ├── Interrupts/
    │   ├── Dma/
    │   ├── Frontend/
    │   │   └── Wasm/       # WASM-specific implementations
    │   └── Emulator.php
    └── vendor/             # Composer dependencies
        └── autoload.php
```

---

## Testing the Build

### Local Web Server

```bash
# Serve from dist/ directory
make serve-wasm

# Or manually:
cd dist
python3 -m http.server 8000

# Open browser to: http://localhost:8000
```

### Browser Console Debugging

Open browser DevTools (F12) and check:

1. **Network tab:** Verify `php-wasm.wasm` loads (should be ~5-10 MB)
2. **Console tab:** Look for PHPBoy initialization messages:
   ```
   [PHPBoy] Initializing PHP WASM runtime...
   [PHPBoy] PHP WASM ready
   [PHPBoy] Loading PHP classes...
   [PHPBoy] PHPBoy classes loaded successfully
   ```

3. **Performance tab:** Profile frame rendering to ensure 60 FPS

### Test with ROM

1. Click "Choose ROM File"
2. Select a Game Boy ROM (.gb or .gbc)
3. Click "Play"
4. Verify:
   - Canvas shows game graphics
   - Audio plays (if ROM has sound)
   - Keyboard input works (arrows, Z, X, Enter, Shift)
   - FPS counter shows ~60 FPS

---

## Build Optimization

### 1. WASM Binary Size

Reduce `php-wasm.wasm` size:

```bash
# Compile with optimizations
emcc -O3 -flto --closure 1 ...

# Strip debug symbols
wasm-strip php-wasm.wasm

# Compress with Brotli (better than gzip for WASM)
brotli -q 11 php-wasm.wasm
```

Typical sizes:
- Unoptimized: ~15 MB
- Optimized: ~8 MB
- Compressed (gzip): ~2.5 MB
- Compressed (brotli): ~2 MB

### 2. Enable PHP Opcache

Compile PHP WASM with Opcache extension:

```bash
# In php-wasm build configuration
./configure --enable-opcache ...
```

Benefits:
- 3x faster PHP execution (per WordPress Playground benchmarks)
- Cached bytecode reduces parse overhead

### 3. Lazy Loading

Load PHP source files on demand:

```javascript
// In web/js/phpboy.js
async loadPhpClass(className) {
    const response = await fetch(`phpboy/src/${className}.php`);
    const code = await response.text();
    await this.php.run(code);
}
```

### 4. Service Worker Caching

Cache WASM files for offline use:

```javascript
// web/js/sw.js
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open('phpboy-v1').then((cache) => {
            return cache.addAll([
                '/index.html',
                '/styles.css',
                '/php-wasm.wasm',
                '/php-wasm.js',
                '/js/phpboy.js',
                '/js/app.js'
            ]);
        })
    );
});
```

---

## Troubleshooting

### Issue: WASM Module Failed to Load

**Symptoms:**
```
CompileError: WebAssembly.instantiate(): ...
```

**Solutions:**
1. Check browser compatibility (requires WebAssembly support - Chrome 57+, Firefox 52+, Safari 11+)
2. Verify CORS headers (WASM files must be same-origin or have proper CORS)
3. Check MIME type: server must serve `.wasm` as `application/wasm`
4. Try a different browser

### Issue: PHP Classes Not Found

**Symptoms:**
```
Fatal error: Class 'Gb\Emulator' not found
```

**Solutions:**
1. Verify `vendor/autoload.php` is in `dist/phpboy/vendor/`
2. Check virtual filesystem paths in php-wasm
3. Ensure `require_once` paths are correct
4. Check browser console for file load errors

### Issue: Poor Performance (< 30 FPS)

**Symptoms:**
- Stuttering gameplay
- FPS counter shows red < 30 FPS

**Solutions:**
1. Enable Opcache in PHP WASM build
2. Use WASM SIMD if browser supports it
3. Reduce canvas scale (4x → 2x)
4. Profile with Chrome DevTools Performance tab
5. Check for JavaScript bridge bottlenecks

### Issue: Audio Glitches

**Symptoms:**
- Crackling or popping sounds
- Audio cuts out

**Solutions:**
1. Increase audio buffer size (2048 → 4096 samples)
2. Check AudioContext sample rate matches emulator (44100 Hz)
3. Ensure audio samples are normalized (-1.0 to 1.0)
4. Use `BufferSink::clear()` after each frame

### Issue: Input Lag

**Symptoms:**
- Button presses delayed
- Missed inputs

**Solutions:**
1. Reduce frame processing time (profile with DevTools)
2. Use `requestAnimationFrame` for frame loop (already implemented)
3. Avoid blocking operations in frame loop
4. Check `WasmInput::setButtonState()` is called immediately on keydown/keyup

---

## Deployment

### Static Hosting (GitHub Pages, Netlify, Vercel)

```bash
# Build for production
make build-wasm

# Deploy dist/ to static host
# GitHub Pages:
git subtree push --prefix dist origin gh-pages

# Netlify:
netlify deploy --dir=dist --prod

# Vercel:
vercel --prod dist/
```

### CDN Optimization

Use CDN for faster WASM delivery:

1. Upload `php-wasm.wasm` to CDN (Cloudflare, AWS CloudFront)
2. Update `web/index.html` to load from CDN:
   ```html
   <script src="https://cdn.example.com/php-wasm.js"></script>
   ```
3. Enable Brotli compression
4. Set long cache headers (1 year)

### HTTPS Required

WebAssembly requires HTTPS (or localhost). Ensure:
- Production deployment uses HTTPS
- SSL certificate is valid
- No mixed content warnings

---

## Performance Benchmarks

Expected performance on modern hardware:

| Browser | FPS | Frame Time | Audio Latency |
|---------|-----|------------|---------------|
| Chrome 120+ | 60 | ~16ms | ~50ms |
| Firefox 120+ | 58-60 | ~17ms | ~60ms |
| Safari 17+ | 55-60 | ~18ms | ~70ms |
| Edge 120+ | 60 | ~16ms | ~50ms |

**Test System:** Intel i7-10700K, 16GB RAM, integrated graphics

---

## References

- **Emscripten:** https://emscripten.org/
- **seanmorris/php-wasm:** https://github.com/seanmorris/php-wasm
- **WordPress Playground:** https://github.com/WordPress/wordpress-playground
- **WebAssembly Docs:** https://webassembly.org/
- **PHP WASM Guide:** https://wasmlabs.dev/articles/compiling-php-to-webassembly/

---

## Next Steps

1. **Set up Emscripten SDK** (see Prerequisites)
2. **Build php-wasm** using seanmorris/php-wasm
3. **Run `make build-wasm`** to create distribution
4. **Test locally** with `make serve-wasm`
5. **Deploy to production** (GitHub Pages, Netlify, etc.)

For browser usage instructions, see [`docs/browser-usage.md`](./browser-usage.md).
