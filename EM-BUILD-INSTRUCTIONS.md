# Building em for PHPBoy

This file contains instructions for building the em (PHP WebAssembly) binaries required for PHPBoy's browser frontend.

## Quick Start

The em build is currently running in the background. Once complete, copy the binaries:

```bash
# Check if build is complete
ls -lh /tmp/em/php-em.js /tmp/em/php-em.wasm

# If files exist, copy to PHPBoy
cp /tmp/em/php-em.js /tmp/em/php-em.wasm web/

# Then you can serve the WASM build
make serve-wasm
```

## Full Build Instructions (if build needs to be restarted)

### 1. Install Prerequisites

**Emscripten SDK:**
```bash
cd /tmp
git clone https://github.com/emscripten-core/emsdk.git
cd emsdk
./emsdk install 4.0.11
./emsdk activate 4.0.11
source ./emsdk_env.sh
```

**PHP Source:**
```bash
cd /tmp
git clone https://github.com/php/php-src.git -b PHP-8.4 --depth 1
```

**em:**
```bash
cd /tmp
git clone https://github.com/krakjoe/em.git
```

**Build Tools:**
```bash
# Ubuntu/Debian
sudo apt-get install re2c bison autoconf

# macOS
brew install re2c bison autoconf
```

### 2. Build em

```bash
cd /tmp/em
source /tmp/emsdk/emsdk_env.sh

# Build with minimal extensions (recommended for PHPBoy)
make -f em.mk EM_PHP_DIR=/tmp/php-src with="bcmath ctype mbstring tokenizer"

# This will take 20-30 minutes
```

### 3. Copy Binaries

```bash
# Copy to PHPBoy web directory
cp /tmp/em/php-em.js /tmp/em/php-em.wasm /path/to/phpboy/web/

# Verify
ls -lh web/php-em.*
```

### 4. Test

```bash
# Serve locally
make serve-wasm

# Open http://localhost:8080 in browser
# Load a ROM and verify it works
```

## Build Options

### Minimal Build (Fastest, Smallest)
```bash
make -f em.mk EM_PHP_DIR=/tmp/php-src with="ctype mbstring"
```

### Recommended for PHPBoy
```bash
make -f em.mk EM_PHP_DIR=/tmp/php-src with="bcmath ctype mbstring tokenizer"
```

### With Compression Support
```bash
make -f em.mk EM_PHP_DIR=/tmp/php-src with="bcmath ctype mbstring tokenizer zlib"
```

### Full Build (Slow, Large)
```bash
make -f em.mk EM_PHP_DIR=/tmp/php-src bake=all
```

## Expected Build Times

- **Configure:** 2-3 minutes
- **Compile:** 15-25 minutes  
- **Link:** 2-3 minutes
- **Total:** 20-30 minutes

## Expected File Sizes

- `php-em.js`: ~500-700 KB
- `php-em.wasm`: ~3-4 MB
- `phpboy-wasm-full.php`: ~600 KB (bundled PHP source)

## Troubleshooting

### "re2c not found" or "re2c version too old"
```bash
sudo apt-get install re2c  # Ubuntu/Debian
brew install re2c          # macOS
```

### "emcc not found"
```bash
source /tmp/emsdk/emsdk_env.sh
```

### Build hangs or fails

1. Check available memory (needs ~4GB)
2. Try with fewer parallel jobs: `make -f em.mk -j2 ...`
3. Check /tmp/em-build2.log for errors

### Testing the Build

```bash
# Verify binaries are valid
file /tmp/em/php-em.wasm
# Should output: WebAssembly (wasm) binary module

# Test in browser
cd /path/to/phpboy
make serve-wasm
# Open http://localhost:8080
# Check browser console for errors
```

## Pre-built Binaries

If you don't want to build from source, you may be able to find pre-built em binaries:

- Check [em releases](https://github.com/krakjoe/em/releases)
- Use em's GitHub Actions artifacts
- Contact PHPBoy maintainers for pre-built binaries

## Next Steps

Once em binaries are in place:

1. `make serve-wasm` - Start local server
2. Open http://localhost:8080
3. Load a GB/GBC ROM file
4. Enjoy 2-4x better performance vs php-wasm!

## See Also

- [em-migration.md](docs/em-migration.md) - Full migration details
- [wasm-build.md](docs/wasm-build.md) - Original WASM build guide
- [krakjoe/em](https://github.com/krakjoe/em) - em repository
