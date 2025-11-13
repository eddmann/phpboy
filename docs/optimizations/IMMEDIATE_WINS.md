# Immediate WASM Build Optimizations (Strategy A)

Quick wins that can be implemented in 1-3 weeks for 3-5x performance improvement.

## Optimization 1: Replace JSON with Binary Packing

**Current (Slow):**
```php
// phpboy.js line 241-244
echo json_encode([
    'pixels' => $pixels,    // 92,160 integers
    'audio' => $audioSamples
]);
```

**Optimized:**
```php
// Use binary packing instead of JSON
$packed = pack('C*', ...$pixels);  // Binary pack
echo $packed;
```

```javascript
// JavaScript side
const response = await this.php.run(`...`);
const binaryData = new Uint8Array(response.buffer);

// First 92,160 bytes = pixels
const pixels = new Uint8ClampedArray(binaryData.buffer, 0, 92160);

// Remaining bytes = audio
const audioStart = 92160;
const audioData = new Float32Array(
  binaryData.buffer,
  audioStart,
  (binaryData.length - audioStart) / 4
);
```

**Expected Improvement:** 30-40% faster (JSON parsing eliminated)

---

## Optimization 2: Use SharedArrayBuffer for Zero-Copy Transfer

**Concept:**
Instead of passing data between PHP and JavaScript, use shared memory that both can access.

**Implementation:**

```javascript
// Create shared buffer (once at init)
const sharedBuffer = new SharedArrayBuffer(96 * 1024); // 96 KB
const pixelView = new Uint8ClampedArray(sharedBuffer, 0, 92160);
const audioView = new Float32Array(sharedBuffer, 92160, 1024);

// Get WASM memory pointer
const phpInstance = await this.php.binary;
const wasmMemory = phpInstance.asm.memory;

// PHP writes directly to WASM memory at known offset
// JavaScript reads from same location (zero-copy!)
```

**PHP Side:**
```php
// Modified WasmFramebuffer.php
class WasmFramebuffer implements FramebufferInterface
{
    private const WASM_PIXEL_OFFSET = 0x100000; // 1 MB into WASM heap

    public function present(): void
    {
        // Copy pixels directly to WASM memory
        // JavaScript will read from this location
        $ptr = self::WASM_PIXEL_OFFSET;

        foreach ($this->buffer as $y => $row) {
            foreach ($row as $x => $color) {
                $offset = ($y * 160 + $x) * 4;
                // Write directly to WASM linear memory
                // (requires php-wasm memory access API)
            }
        }
    }
}
```

**Expected Improvement:** 50-60% faster (no serialization/deserialization)

---

## Optimization 3: Batch Input Events

**Current (Inefficient):**
```javascript
// phpboy.js lines 335-342
async handleKeyDown(e) {
    // SEPARATE php.run() call for EACH key event
    await this.php.run(`<?php
        $input->setButtonState(${buttonCode}, true);
    `);
}
```

**Optimized:**
```javascript
class PHPBoy {
    constructor() {
        this.inputQueue = [];
    }

    handleKeyDown(e) {
        // Queue inputs instead of immediate php.run()
        this.inputQueue.push({
            button: buttonCode,
            pressed: true
        });
    }

    async loop() {
        // Process ALL inputs in ONE php.run() call
        const inputs = JSON.stringify(this.inputQueue);
        this.inputQueue = [];

        await this.php.run(`<?php
            global $emulator;

            // Process queued inputs
            $inputs = json_decode('${inputs}', true);
            foreach ($inputs as $input) {
                $emulator->getInput()->setButtonState(
                    $input['button'],
                    $input['pressed']
                );
            }

            // Execute frames
            for ($i = 0; $i < 4; $i++) {
                $emulator->step();
            }

            // Return frame data
            echo $binaryData;
        `);
    }
}
```

**Expected Improvement:** 15-20% faster (fewer boundary crossings)

---

## Optimization 4: WebWorker Background Execution

**Concept:**
Move PHP execution off the main thread so UI stays responsive.

**Structure:**
```
Main Thread (UI)          Worker Thread (Emulation)
     │                            │
     ├──► Input events ──────────►│
     │                            │ php.run()
     │                            │ step() × 4
     │                            │ get pixels
     │                            │
     │◄──── Pixel data ───────────┤
     │                            │
     └──► Render to canvas
```

**Implementation:**

**worker.js:**
```javascript
// Web Worker for PHP execution
importScripts('https://cdn.jsdelivr.net/npm/php-wasm/PhpWeb.mjs');

let php = null;
let initialized = false;

self.onmessage = async (e) => {
    const { type, data } = e.data;

    if (type === 'init') {
        // Initialize PHP
        php = new PhpWeb({ /* config */ });
        await php.binary;

        // Load ROM and emulator
        // ...

        initialized = true;
        self.postMessage({ type: 'ready' });
    }

    if (type === 'frame') {
        // Execute frame
        const result = await php.run(`<?php
            // Process inputs from data.inputs
            // Execute frames
            // Return pixels
        `);

        // Send pixels back to main thread
        self.postMessage({
            type: 'frame_data',
            pixels: result.pixels,
            audio: result.audio
        }, [result.pixels.buffer]); // Transferable!
    }
};
```

**Main thread:**
```javascript
class PHPBoy {
    constructor() {
        this.worker = new Worker('worker.js');
        this.worker.onmessage = (e) => this.handleWorkerMessage(e);
    }

    async init() {
        this.worker.postMessage({ type: 'init' });
        // Wait for ready message
    }

    loop() {
        // Request frame from worker
        this.worker.postMessage({
            type: 'frame',
            inputs: this.inputQueue
        });
    }

    handleWorkerMessage(e) {
        if (e.data.type === 'frame_data') {
            // Render pixels (on main thread)
            const imageData = new ImageData(e.data.pixels, 160, 144);
            this.ctx.putImageData(imageData, 0, 0);

            // Request next frame
            requestAnimationFrame(() => this.loop());
        }
    }
}
```

**Expected Improvement:**
- 10-15% FPS boost
- Much smoother UI (no frame drops during heavy emulation)
- Better responsiveness to input

---

## Optimization 5: Optimize PHP Bundle Size

**Current bundle includes unnecessary code:**
- CLI frontend (not needed in browser)
- SDL frontend (not needed in browser)
- Debug tools (not needed in production)
- TAS recorder (niche feature)

**Modified bin/bundle-wasm.php:**
```php
// Exclude patterns
$excludePaths = [
    'Frontend/Cli',
    'Frontend/Sdl',
    'Debug',
    'Tas',
];

foreach ($files as $file) {
    $relativePath = str_replace($baseDir, '', $file);

    // Skip excluded paths
    $shouldExclude = false;
    foreach ($excludePaths as $excludePath) {
        if (str_contains($relativePath, $excludePath)) {
            $shouldExclude = true;
            break;
        }
    }

    if ($shouldExclude) {
        continue;
    }

    // ... bundle file
}
```

**Expected Improvement:**
- 150-200 KB smaller bundle (25% reduction)
- Faster initial load time
- Less memory usage

---

## Optimization 6: Reduce Frames Per Render

**Current:**
```javascript
const framesPerRender = 4; // Execute 4 frames, then render
```

**Why this is slow:**
- Still serializes all 4 frames of data
- PHP has to accumulate state

**Better approach:**
```javascript
const framesPerRender = 1; // Execute 1 frame per render

// But use binary transfer + SharedArrayBuffer
// This reduces latency and overhead
```

With zero-copy transfer (Optimization 2), rendering every frame becomes faster than batching.

---

## Optimization 7: Optimize PHP Code Hot Paths

**Critical: getPixelsRGBA() method**

**Current (WasmFramebuffer.php:96-111):**
```php
public function getPixelsRGBA(): array
{
    $pixels = [];

    for ($y = 0; $y < 144; $y++) {
        for ($x = 0; $x < 160; $x++) {
            $color = $this->buffer[$y][$x];
            $pixels[] = $color->r;  // 4 array appends per pixel
            $pixels[] = $color->g;  // = 92,160 operations
            $pixels[] = $color->b;
            $pixels[] = 255;
        }
    }

    return $pixels;
}
```

**Optimized:**
```php
public function getPixelsRGBA(): array
{
    // Pre-allocate array (faster than repeated appends)
    $pixels = array_fill(0, 92160, 0);
    $i = 0;

    for ($y = 0; $y < 144; $y++) {
        for ($x = 0; $x < 160; $x++) {
            $color = $this->buffer[$y][$x];
            $pixels[$i++] = $color->r;
            $pixels[$i++] = $color->g;
            $pixels[$i++] = $color->b;
            $pixels[$i++] = 255;
        }
    }

    return $pixels;
}
```

**Even better - pack directly to binary string:**
```php
public function getPixelsBinary(): string
{
    $pixels = '';

    for ($y = 0; $y < 144; $y++) {
        for ($x = 0; $x < 160; $x++) {
            $color = $this->buffer[$y][$x];
            $pixels .= chr($color->r) .
                       chr($color->g) .
                       chr($color->b) .
                       chr(255);
        }
    }

    return $pixels;
}
```

**Expected Improvement:** 20-30% faster pixel access

---

## Combined Impact

Implementing all 7 optimizations:

| Optimization | Individual Gain | Cumulative |
|--------------|----------------|------------|
| 1. Binary packing | +35% | 6.8 FPS |
| 2. SharedArrayBuffer | +50% | 10.2 FPS |
| 3. Batch inputs | +18% | 12.0 FPS |
| 4. WebWorker | +12% | 13.4 FPS |
| 5. Bundle optimization | +0% (load time) | 13.4 FPS |
| 6. Reduce batch size | +15% | 15.4 FPS |
| 7. Optimize hot paths | +25% | 19.2 FPS |

**Final result: ~20 FPS (4x improvement from 5 FPS)**

**With aggressive optimization: 25-35 FPS (5-7x improvement)**

---

## Implementation Priority

### Week 1: Low-Hanging Fruit
1. **Binary packing** (Optimization 1) - 4 hours
2. **Bundle optimization** (Optimization 5) - 2 hours
3. **Optimize hot paths** (Optimization 7) - 4 hours

**Expected: 10-15 FPS**

### Week 2: Input Batching
4. **Batch inputs** (Optimization 3) - 6 hours

**Expected: 12-18 FPS**

### Week 3: Advanced Techniques
5. **WebWorker** (Optimization 4) - 12 hours
6. **SharedArrayBuffer** (Optimization 2) - 16 hours

**Expected: 20-35 FPS**

---

## Testing & Validation

After each optimization:

```javascript
// Benchmark script
async function benchmark() {
    const startTime = performance.now();
    let frames = 0;

    for (let i = 0; i < 60 * 10; i++) {  // 10 seconds at 60 FPS
        await phpboy.loop();
        frames++;
    }

    const endTime = performance.now();
    const elapsed = (endTime - startTime) / 1000;
    const fps = frames / elapsed;

    console.log(`FPS: ${fps.toFixed(2)}`);
    console.log(`Frame time: ${(1000 / fps).toFixed(2)} ms`);
}
```

Compare before/after for each optimization.

---

## Conclusion

These optimizations can be implemented quickly and provide significant performance improvements without requiring a complete rewrite. They reduce the overhead of the php-wasm architecture while maintaining the PHP codebase.

**Timeline: 3 weeks**
**Expected result: 20-35 FPS (4-7x improvement)**
**Effort: Low-Medium**

After implementing these, evaluate whether to proceed with Strategy B (Rust hybrid) for 60+ FPS.
