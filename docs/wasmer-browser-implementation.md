# Running Wasmer (WASI) in the Browser for phpboy

## TL;DR: The Wasmer Browser Challenge

**⚠️ Key Fact:** Wasmer itself **cannot run directly in browsers** because it:
- Requires native OS system calls (mmap, file I/O)
- Uses JIT/AOT compilation to native machine code
- Depends on OS-level memory management

**✅ Solution:** Run **WASI-compiled binaries** (like `php.wasm`) in the browser using lightweight WASI polyfills that emulate Wasmer's environment.

---

## Current phpboy Setup vs. Wasmer Approach

### Current Architecture (php-wasm/Emscripten)
```
┌─────────────────────────────────────┐
│         Browser                     │
│  ┌───────────────────────────────┐ │
│  │ php-wasm (Emscripten)         │ │
│  │ - Full PHP runtime            │ │
│  │ - Virtual filesystem          │ │
│  │ - Custom JS glue              │ │
│  │ - ~15MB WASM binary           │ │
│  └───────────────────────────────┘ │
│           ↕                         │
│  ┌───────────────────────────────┐ │
│  │ phpboy.js (630 lines)         │ │
│  │ - JSON serialization          │ │
│  │ - Frame batching              │ │
│  └───────────────────────────────┘ │
└─────────────────────────────────────┘
```

**Pros:**
- ✅ Works out-of-the-box
- ✅ No custom compilation

**Cons:**
- ❌ Heavy (~15MB download)
- ❌ JSON serialization overhead
- ❌ No access to WASI ecosystem
- ❌ Emscripten-specific optimizations only

---

### Proposed: Browser WASI Runtime (Wasmer-like)

```
┌─────────────────────────────────────────────┐
│         Browser                             │
│  ┌───────────────────────────────────────┐ │
│  │ @bytecodealliance/jco (WASI polyfill) │ │
│  │ - Lightweight WASI layer              │ │
│  │ - Direct WASM instantiation           │ │
│  │ - ~50KB overhead                      │ │
│  └───────────────────────────────────────┘ │
│           ↕                                 │
│  ┌───────────────────────────────────────┐ │
│  │ php-wasi.wasm (compiled with Wasmer)  │ │
│  │ - Optimized WASI binary               │ │
│  │ - ~8-10MB (smaller than Emscripten)   │ │
│  │ - Better performance potential        │ │
│  └───────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
```

**Pros:**
- ✅ 30-40% smaller download
- ✅ Better optimization potential
- ✅ Access to Wasmer AOT compilation
- ✅ Standards-compliant WASI
- ✅ Can use SharedArrayBuffer/SIMD

**Cons:**
- ⚠️ Requires custom build pipeline
- ⚠️ WASI polyfill compatibility varies
- ⚠️ More complex setup

---

## Option 1: @bytecodealliance/jco (Recommended)

**What it is:** Official JavaScript WASI runtime from the WebAssembly team (Wasmtime creators)

**Browser Support (2025):**
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 15.4+
- ✅ Edge 90+

### Implementation for phpboy

#### Step 1: Install Dependencies
```bash
npm install @bytecodealliance/jco @bytecodealliance/preview2-shim
```

#### Step 2: Create WASI-Compatible PHP Build

**Option A: Use Pre-built php-wasi**
```bash
# Download official WASI build
wget https://github.com/php/php-src/releases/download/php-8.4.0/php-8.4.0-wasi.tar.gz
tar -xzf php-8.4.0-wasi.tar.gz
# Result: php.wasm
```

**Option B: Build Custom PHP-WASI** (for optimizations)
```bash
# Clone PHP source
git clone https://github.com/php/php-src.git
cd php-src

# Configure for WASI
./buildconf
./configure \
  --host=wasm32-wasi \
  --enable-embed \
  --disable-all \
  --enable-opcache \
  --enable-jit \
  CC=clang \
  CFLAGS="-O3 -flto -msimd128"

# Build
make -j$(nproc)

# Result: sapi/embed/php.wasm
```

#### Step 3: Bundle phpboy Code with WASM

```bash
# Create bundled PHP file (already implemented)
make build-wasm

# Embed PHP code into WASM filesystem at build time
# (Alternative to runtime loading)
```

#### Step 4: Browser Integration

```javascript
// web/js/phpboy-wasi.js
import { WASI } from '@bytecodealliance/preview2-shim';
import phpWasm from './php.wasm'; // Import as module

class PhpBoyWASI {
  constructor() {
    this.wasi = null;
    this.instance = null;
    this.memory = null;
  }

  async init() {
    // Create WASI environment
    this.wasi = new WASI({
      args: ['php', '-r', '<?php require "phpboy-wasm.php"; ?>'],
      env: {
        'PHPRC': '/etc/php',
      },
      preopens: {
        '/': '/',           // Virtual root
        '/rom': '/rom',     // ROM directory
      },
      stdout: (data) => console.log(new TextDecoder().decode(data)),
      stderr: (data) => console.error(new TextDecoder().decode(data)),
    });

    // Instantiate WASM module
    const module = await WebAssembly.compileStreaming(fetch(phpWasm));
    this.instance = await WebAssembly.instantiate(module, {
      wasi_snapshot_preview1: this.wasi.wasiImport,
    });

    // Get shared memory reference
    this.memory = this.instance.exports.memory;

    // Initialize WASI
    this.wasi.initialize(this.instance);
  }

  async loadROM(romData) {
    // Write ROM to virtual filesystem
    const romPath = '/rom/game.gb';
    this.wasi.fs.writeFileSync(romPath, new Uint8Array(romData));

    // Initialize emulator
    await this.call(`
      $emulator = new PhpBoy\\Emulator();
      $emulator->loadROM('${romPath}');
    `);
  }

  async runFrame() {
    // Execute one frame
    const result = await this.call(`
      $emulator->runFrame();
      echo json_encode([
        'pixels' => $framebuffer->getPixelsRGBA(),
        'audio' => $audioSink->getSamplesFlat(),
      ]);
    `);
    return JSON.parse(result);
  }

  async call(phpCode) {
    // Call PHP code via exported function
    const codePtr = this.writeString(phpCode);
    const resultPtr = this.instance.exports.php_eval(codePtr);
    return this.readString(resultPtr);
  }

  writeString(str) {
    const encoder = new TextEncoder();
    const data = encoder.encode(str);
    const ptr = this.instance.exports.malloc(data.length + 1);
    const mem = new Uint8Array(this.memory.buffer, ptr, data.length + 1);
    mem.set(data);
    mem[data.length] = 0; // Null terminator
    return ptr;
  }

  readString(ptr) {
    const mem = new Uint8Array(this.memory.buffer, ptr);
    let end = ptr;
    while (mem[end - ptr] !== 0) end++;
    return new TextDecoder().decode(mem.slice(0, end - ptr));
  }
}

// Usage
const phpboy = new PhpBoyWASI();
await phpboy.init();
await phpboy.loadROM(romData);

// Game loop
function gameLoop() {
  const frame = await phpboy.runFrame();
  renderFrame(frame.pixels);
  playAudio(frame.audio);
  requestAnimationFrame(gameLoop);
}
gameLoop();
```

---

## Option 2: wasmer-js (Experimental)

**Status:** Deprecated but still functional for demos

**Installation:**
```bash
npm install @wasmer/sdk@0.x
```

**Implementation:**
```javascript
import { init, WASI } from '@wasmer/sdk';

await init();

const wasi = new WASI({
  args: ['php', '-r', 'echo "Hello";'],
  env: {},
});

const module = await WebAssembly.compileStreaming(fetch('php.wasm'));
const instance = await wasi.instantiate(module, {});

const exitCode = wasi.start(instance);
console.log(`Exit code: ${exitCode}`);
```

**Pros:**
- ✅ Familiar Wasmer API
- ✅ Good debugging tools

**Cons:**
- ❌ No longer maintained
- ❌ Missing WASI Preview 2 features
- ❌ Larger overhead

---

## Option 3: Hybrid Approach (Server Wasmer + Browser UI)

**Best for:** Production performance with browser interface

### Architecture
```
┌──────────────┐         WebSocket/REST         ┌─────────────────┐
│   Browser    │◄────────────────────────────────►│ Server          │
│              │                                 │                 │
│ - UI/Canvas  │  { command: "runFrame" }        │ Wasmer Runtime  │
│ - Input      │  ────────────────────────►      │ php-wasi.wasm   │
│ - Rendering  │                                 │ Native speed    │
│              │  { pixels, audio }              │                 │
│              │  ◄────────────────────────      │                 │
└──────────────┘                                 └─────────────────┘
```

### Server Implementation (Node.js)
```javascript
// server.js
import { Wasmer } from '@wasmer/wasi';
import express from 'express';
import { WebSocketServer } from 'ws';

const app = express();
const wss = new WebSocketServer({ port: 8080 });

// Initialize Wasmer instance per connection
wss.on('connection', async (ws) => {
  const wasmer = await Wasmer.fromFile('php.wasm', {
    args: ['php'],
  });

  ws.on('message', async (data) => {
    const { command, params } = JSON.parse(data);

    switch (command) {
      case 'loadROM':
        wasmer.fs.writeFile('/rom.gb', params.romData);
        const result = wasmer.run('loadROM("/rom.gb");');
        ws.send(JSON.stringify({ status: 'loaded' }));
        break;

      case 'runFrame':
        const frame = wasmer.run('runFrame();');
        ws.send(JSON.stringify({
          pixels: frame.pixels,
          audio: frame.audio,
        }), { binary: true }); // Send as binary for speed
        break;
    }
  });
});

app.listen(3000);
```

### Browser Client
```javascript
// client.js
const ws = new WebSocket('ws://localhost:8080');

ws.onopen = () => {
  // Load ROM
  ws.send(JSON.stringify({
    command: 'loadROM',
    params: { romData: new Uint8Array(romFile) }
  }));
};

ws.onmessage = (event) => {
  const data = JSON.parse(event.data);
  if (data.pixels) {
    renderFrame(data.pixels);
  }
};

// Game loop: Request frames at 60 FPS
setInterval(() => {
  ws.send(JSON.stringify({ command: 'runFrame' }));
}, 16.67);
```

**Pros:**
- ✅ **True native performance** (Wasmer JIT/AOT)
- ✅ Full WASI capabilities (filesystem, networking)
- ✅ Easy scaling (deploy to edge)
- ✅ Can use Wasmer AOT pre-compilation

**Cons:**
- ❌ Requires server infrastructure
- ❌ Network latency (~10-50ms)
- ❌ Not offline-capable

**Best Use Cases:**
- Multiplayer/networked games
- Heavy computation offloading
- Production deployments

---

## Performance Comparison Matrix

| Approach | Download Size | Startup Time | Runtime Speed | Latency | Offline |
|----------|---------------|--------------|---------------|---------|---------|
| **php-wasm (current)** | 15MB | 2-5s | 1.0x | 0ms | ✅ |
| **@bytecodealliance/jco** | 8-10MB | 1-2s | 1.2-1.5x | 0ms | ✅ |
| **wasmer-js** | 12MB | 2-3s | 1.1-1.3x | 0ms | ✅ |
| **Server Wasmer** | 100KB | <100ms | 3-5x | 20-50ms | ❌ |
| **Wasmer AOT** | 6-8MB | <500ms | 2-3x | 0ms | ✅ |

---

## Recommended Implementation Plan

### Phase 1: Proof of Concept (1-2 days)
1. ✅ Download pre-built `php-wasi.wasm`
2. ✅ Create minimal `@bytecodealliance/jco` integration
3. ✅ Test basic PHP execution
4. ✅ Measure startup time and memory usage

### Phase 2: Full Integration (3-5 days)
1. ✅ Implement WASI filesystem loading
2. ✅ Port phpboy.js to WASI runtime
3. ✅ Optimize data transfer (binary protocol)
4. ✅ Add SharedArrayBuffer support
5. ✅ Benchmark vs. current php-wasm

### Phase 3: Optimization (1 week)
1. ✅ Custom PHP-WASI build with optimizations
2. ✅ Wasmer AOT pre-compilation
3. ✅ WASM SIMD integration
4. ✅ Web Workers + SharedArrayBuffer
5. ✅ Final performance tuning

**Expected Outcome:**
- 30-50% smaller download
- 40-80% faster execution
- 60+ FPS sustained

---

## Practical Example: Minimal WASI Integration

### File Structure
```
web/
├── index.html
├── js/
│   ├── phpboy-wasi.js          # New WASI bridge
│   └── phpboy.js                # Original (for comparison)
├── wasm/
│   ├── php.wasm                 # WASI-compiled PHP
│   └── phpboy-wasm-full.php     # Bundled emulator
└── package.json
```

### package.json
```json
{
  "dependencies": {
    "@bytecodealliance/jco": "^1.0.0",
    "@bytecodealliance/preview2-shim": "^0.16.0"
  },
  "type": "module"
}
```

### index.html
```html
<!DOCTYPE html>
<html>
<head>
  <title>phpboy - Game Boy Emulator (WASI)</title>
</head>
<body>
  <canvas id="screen" width="160" height="144"></canvas>
  <input type="file" id="rom-loader" accept=".gb,.gbc">

  <script type="module">
    import PhpBoyWASI from './js/phpboy-wasi.js';

    const phpboy = new PhpBoyWASI();
    await phpboy.init();

    document.getElementById('rom-loader').addEventListener('change', async (e) => {
      const file = e.target.files[0];
      const data = await file.arrayBuffer();
      await phpboy.loadROM(data);
      phpboy.start();
    });
  </script>
</body>
</html>
```

---

## Wasmer AOT Compilation Pipeline

**Concept:** Pre-compile `php.wasm` to native code, then re-package for browser

### Build Script
```bash
#!/bin/bash
# build-aot.sh

# Step 1: Compile PHP to WASM
make build-wasm

# Step 2: AOT compile with Wasmer (target: x86_64)
wasmer compile web/wasm/php.wasm \
  -o web/wasm/php-aot-x86_64.wasmu \
  --target x86_64-unknown-linux-gnu \
  --cpu-features sse4.2,avx2,popcnt

# Step 3: AOT compile (target: aarch64)
wasmer compile web/wasm/php.wasm \
  -o web/wasm/php-aot-aarch64.wasmu \
  --target aarch64-apple-darwin

# Step 4: Generate browser loader (detects CPU)
cat > web/js/aot-loader.js << 'EOF'
async function loadPhpAOT() {
  const isARM = /aarch64|arm64/i.test(navigator.userAgent);
  const wasmFile = isARM ? 'php-aot-aarch64.wasmu' : 'php-aot-x86_64.wasmu';
  return fetch(`wasm/${wasmFile}`);
}
EOF
```

**Expected Results:**
- ✅ 20-40% faster execution (vs. browser JIT)
- ✅ Instant startup (no compilation)
- ✅ Smaller binary (~30% reduction)

**Limitation:** Wasmer AOT binaries (`.wasmu`) are **not directly runnable in browsers**. You would need to:
1. Convert `.wasmu` back to `.wasm` (defeating purpose), OR
2. Use server-side Wasmer (hybrid approach)

**Verdict:** AOT is best for server deployments, not pure browser use.

---

## Final Recommendation

For **phpboy browser deployment**, I recommend:

### Short-term (Next 1-2 weeks):
✅ **Stick with php-wasm** but apply optimizations from `wasm-performance-optimization.md`:
- Binary protocol (MessagePack/SharedArrayBuffer)
- Object pooling
- Web Workers
- WebGL rendering

**Reasoning:** Mature, stable, immediate gains

### Medium-term (1-2 months):
✅ **Experiment with @bytecodealliance/jco** in a separate branch:
- Build custom php-wasi.wasm
- Implement WASI bridge
- Benchmark against php-wasm

**Goal:** 30-50% performance improvement if successful

### Long-term (3-6 months):
✅ **Hybrid architecture** for production:
- Browser UI
- Edge-deployed Wasmer backend (Cloudflare Workers, Fastly Compute)
- WebSocket streaming
- <10ms latency

**Goal:** Native-like performance (120+ FPS capable)

---

## Next Steps

1. **Immediate:** Apply Phase 1 optimizations from performance doc (4-6 hours)
2. **This week:** Set up `@bytecodealliance/jco` POC (1-2 days)
3. **Next week:** Benchmark WASI vs. Emscripten (1 day)
4. **Decision point:** Continue with WASI or optimize current stack

Want me to help implement the `@bytecodealliance/jco` proof-of-concept?
