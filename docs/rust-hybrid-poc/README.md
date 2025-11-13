# Rust Hybrid Approach - Proof of Concept

This directory contains a proof-of-concept showing how to implement a hybrid PHP+Rust architecture for PHPBoy.

## Architecture

```
┌──────────────────────────────────────────────────┐
│              Browser (JavaScript)                 │
├──────────────────────────────────────────────────┤
│                                                   │
│  ┌─────────────────┐      ┌──────────────────┐  │
│  │  PHP (php-wasm) │      │  Rust Core (WASM)│  │
│  │                 │      │                  │  │
│  │ • Save states   │      │ • CPU execution  │  │
│  │ • Load states   │      │ • PPU rendering  │  │
│  │ • Screenshots   │      │ • Memory bus     │  │
│  │ • Debugger      │      │ • Input handling │  │
│  │ • UI logic      │      │ • Audio mixing   │  │
│  └─────────────────┘      └──────────────────┘  │
│         │                          │             │
│         │    Control Messages      │             │
│         └──────────┬───────────────┘             │
│                    ▼                             │
│          Shared Memory Buffer                    │
│          (pixels, audio, state)                  │
└──────────────────────────────────────────────────┘
```

## Performance Comparison

| Component | PHP (php-wasm) | Rust (WASM) | Speedup |
|-----------|---------------|-------------|---------|
| CPU instruction | ~500 ns | ~10 ns | 50x |
| Memory read | ~200 ns | ~3 ns | 67x |
| PPU scanline | ~50 µs | ~1 µs | 50x |
| Full frame | ~15 ms | ~0.3 ms | 50x |

## Setup Instructions

### 1. Install Rust and wasm-pack

```bash
# Install Rust
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh

# Install wasm-pack
cargo install wasm-pack
```

### 2. Build the Rust WASM module

```bash
cd phpboy-core
wasm-pack build --target web
```

This generates:
- `pkg/phpboy_core_bg.wasm` - The WASM binary
- `pkg/phpboy_core.js` - JavaScript bindings
- `pkg/phpboy_core.d.ts` - TypeScript definitions

### 3. Integration

```javascript
// Import the Rust WASM module
import init, { GameBoyCore } from './pkg/phpboy_core.js';

await init();

// Create the core emulator
const core = new GameBoyCore();

// Load ROM
const romData = new Uint8Array(await fetch('rom.gb').then(r => r.arrayBuffer()));
core.load_rom(romData);

// Main loop
function loop() {
  // Execute one frame (70224 cycles)
  core.step();

  // Get pixel data (zero-copy via WASM memory)
  const pixels = core.get_pixels();

  // Render to canvas
  const imageData = new ImageData(pixels, 160, 144);
  ctx.putImageData(imageData, 0, 0);

  requestAnimationFrame(loop);
}

loop();
```

## File Structure

```
phpboy-core/
├── Cargo.toml              # Rust project configuration
├── src/
│   ├── lib.rs              # WASM bindings and public API
│   ├── cpu.rs              # LR35902 CPU implementation
│   ├── ppu.rs              # Pixel Processing Unit
│   ├── bus.rs              # Memory bus
│   ├── cartridge.rs        # ROM/MBC handling
│   └── types.rs            # Common types
├── tests/
│   └── integration.rs      # Test ROM validation
└── README.md
```

## Gradual Migration Strategy

### Phase 1: Core Loop Only (Week 1-2)

Move only the critical path to Rust:
- CPU instruction execution
- Memory bus
- Basic PPU

Keep in PHP:
- Save states
- Screenshots
- Debugger
- UI controls

### Phase 2: PPU Optimization (Week 3-4)

Move PPU rendering to Rust:
- Scanline rendering
- Sprite handling
- Tile fetching

### Phase 3: APU Integration (Week 5-6)

Move audio to Rust:
- Channel mixing
- Sample generation
- Frequency sweep

### Phase 4: Complete Core (Week 7-8)

Final components:
- DMA controllers
- Timer
- Serial port
- Interrupts

## Performance Testing

### Benchmark Script

```javascript
// Run 3600 frames (1 minute at 60 FPS)
const startTime = performance.now();
for (let i = 0; i < 3600; i++) {
  core.step();
}
const endTime = performance.now();

const elapsed = endTime - startTime;
const fps = 3600 / (elapsed / 1000);
console.log(`Average FPS: ${fps.toFixed(2)}`);
```

### Expected Results

| Implementation | FPS (Browser) | Frame Time |
|---------------|---------------|------------|
| PHP (current) | 5-10 | 100-200 ms |
| PHP + optimizations | 25-35 | 28-40 ms |
| Rust hybrid | 60-100+ | 10-16 ms |
| Full Rust | 200-300+ | 3-5 ms |

## Memory Layout

### Shared Buffer Design

```
┌─────────────────────────────────────────────┐
│         WASM Linear Memory                  │
├─────────────────────────────────────────────┤
│ Offset  │ Size    │ Purpose                │
├─────────┼─────────┼────────────────────────┤
│ 0x0000  │ 92160 B │ Framebuffer (160×144×4)│
│ 0x16800 │ 4096 B  │ Audio buffer           │
│ 0x17800 │ 65536 B │ Cartridge RAM          │
│ 0x27800 │ 32768 B │ Work RAM               │
│ 0x2F800 │ 16384 B │ Video RAM              │
│ 0x33800 │ 256 B   │ OAM (sprite data)      │
│ 0x33900 │ 256 B   │ CPU registers          │
└─────────┴─────────┴────────────────────────┘
```

JavaScript can directly access this memory:

```javascript
// Get WASM memory
const memory = core.memory();
const buffer = new Uint8Array(memory.buffer);

// Read pixels (zero-copy)
const pixels = new Uint8ClampedArray(memory.buffer, 0, 92160);

// Read audio samples
const audio = new Float32Array(memory.buffer, 0x16800, 1024);
```

## API Design

### Rust WASM API

```rust
#[wasm_bindgen]
pub struct GameBoyCore {
    // Internal state
}

#[wasm_bindgen]
impl GameBoyCore {
    #[wasm_bindgen(constructor)]
    pub fn new() -> GameBoyCore;

    #[wasm_bindgen]
    pub fn load_rom(&mut self, rom_data: &[u8]) -> Result<(), JsValue>;

    #[wasm_bindgen]
    pub fn step(&mut self);  // Execute one frame

    #[wasm_bindgen]
    pub fn get_pixels(&self) -> Uint8ClampedArray;  // 160×144×4

    #[wasm_bindgen]
    pub fn get_audio(&self) -> Float32Array;

    #[wasm_bindgen]
    pub fn set_input(&mut self, button: u8, pressed: bool);

    #[wasm_bindgen]
    pub fn reset(&mut self);

    #[wasm_bindgen]
    pub fn get_state(&self) -> Vec<u8>;  // Serialize state

    #[wasm_bindgen]
    pub fn set_state(&mut self, state: &[u8]);  // Deserialize state

    #[wasm_bindgen]
    pub fn memory(&self) -> JsValue;  // Expose WASM memory
}
```

### JavaScript Integration

```javascript
class PHPBoyHybrid {
  constructor() {
    this.core = null;  // Rust WASM core
    this.php = null;   // PHP-WASM for utilities
  }

  async init() {
    // Load Rust core
    await init();
    this.core = new GameBoyCore();

    // Load PHP for utilities (optional)
    this.php = await this.initPhp();
  }

  async loadROM(file) {
    const data = new Uint8Array(await file.arrayBuffer());
    this.core.load_rom(data);
  }

  async saveState() {
    // Use Rust to serialize state
    const stateBytes = this.core.get_state();

    // Use PHP to add metadata (optional)
    if (this.php) {
      const metadata = await this.php.exec(`<?php
        echo json_encode([
          'timestamp' => time(),
          'rom_name' => 'game.gb',
        ]);
      `);

      // Combine state + metadata
      return { state: stateBytes, metadata: JSON.parse(metadata) };
    }

    return { state: stateBytes };
  }

  loop() {
    // Pure Rust execution (no PHP involved)
    this.core.step();

    // Zero-copy pixel access
    const pixels = this.core.get_pixels();
    const imageData = new ImageData(pixels, 160, 144);
    this.ctx.putImageData(imageData, 0, 0);

    requestAnimationFrame(() => this.loop());
  }
}
```

## Development Workflow

### 1. Test-Driven Development

Use the existing PHP test suite to validate Rust implementation:

```bash
# Run PHP tests to establish expected behavior
make test-roms

# Implement Rust equivalent
cd phpboy-core && cargo test

# Compare outputs
./compare-outputs.sh
```

### 2. Incremental Replacement

Replace one component at a time:

```javascript
// Week 1: CPU only
const cpu = new RustCpu();
// Still use PHP for PPU, APU, etc.

// Week 2: CPU + Memory
const core = new RustCore();  // CPU + Bus
// Still use PHP for PPU, APU

// Week 3: CPU + Memory + PPU
// Full frame execution in Rust
```

### 3. Validation

For each component, verify:
- Same output as PHP implementation
- Passes existing test ROMs
- Performance improvement measured

## Troubleshooting

### Build Issues

```bash
# If wasm-pack fails
rustup target add wasm32-unknown-unknown
wasm-pack build --target web --debug

# Check WASM output
wasm-objdump -x pkg/phpboy_core_bg.wasm
```

### Memory Issues

```rust
// Ensure proper memory layout
#[repr(C)]
pub struct Framebuffer {
    pixels: [u8; 160 * 144 * 4],
}
```

### Performance Issues

```bash
# Build with optimizations
wasm-pack build --target web --release

# Profile WASM
# Use browser DevTools Performance tab
```

## Next Steps

1. **Create minimal proof-of-concept**
   - CPU only
   - No PPU/APU
   - Verify basic execution

2. **Measure performance**
   - Compare to PHP version
   - Validate 50x+ speedup

3. **Expand gradually**
   - Add PPU
   - Add APU
   - Add peripherals

4. **Integration**
   - Update phpboy.js
   - Maintain PHP utilities
   - Deploy hybrid version

## Resources

- [wasm-bindgen Guide](https://rustwasm.github.io/wasm-bindgen/)
- [Rust and WebAssembly Book](https://rustwasm.github.io/book/)
- [Game Boy Pan Docs](https://gbdev.io/pandocs/)
