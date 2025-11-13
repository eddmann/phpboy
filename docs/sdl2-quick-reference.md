# PHPBoy Frontend Comparison - Quick Reference

## Key Files by Component

### INPUT HANDLING

| Frontend | File | Lines | Status |
|----------|------|-------|--------|
| CLI | `/home/user/phpboy/src/Frontend/Cli/CliInput.php` | 233 | ✅ Complete |
| SDL2 | `/home/user/phpboy/src/Frontend/Sdl/SdlInput.php` | 237 | ⚠️ Partial |
| Tests (CLI) | `/home/user/phpboy/tests/Unit/Frontend/Cli/CliInputTest.php` | - | ✅ Complete |
| Tests (SDL2) | `/home/user/phpboy/tests/Unit/Frontend/Sdl/SdlInputTest.php` | - | ✅ Complete |

**Key Differences:**
- CLI: Uses `stream_select()` for non-blocking terminal input + ANSI escape sequences
- SDL2: Uses `SDL_GetKeyboardState()` for native keyboard polling + event handling

### DISPLAY/RENDERING

| Frontend | File | Lines | Status |
|----------|------|-------|--------|
| CLI | `/home/user/phpboy/src/Frontend/Cli/CliRenderer.php` | 403 | ✅ Complete |
| SDL2 | `/home/user/phpboy/src/Frontend/Sdl/SdlRenderer.php` | 369 | ⚠️ Partial |

**Key Differences:**
- CLI: ASCII/ANSI color modes, unicode half-blocks (▀), 160×72 chars
- SDL2: GPU-accelerated, native window, 160×144 pixels, 1-8x scaling

### AUDIO

| Component | File | Lines | Status |
|-----------|------|-------|--------|
| APU | `/home/user/phpboy/src/Apu/Apu.php` | - | ✅ Complete |
| SoX Sink (CLI) | `/home/user/phpboy/src/Apu/Sink/SoxAudioSink.php` | 151 | ✅ Complete |
| WAV Sink | `/home/user/phpboy/src/Apu/Sink/WavSink.php` | - | ✅ Complete |
| Interface | `/home/user/phpboy/src/Apu/AudioSinkInterface.php` | - | ✅ Complete |
| SDL2 Sink | `/home/user/phpboy/src/Frontend/Sdl/SdlAudioSink.php` | - | ❌ **MISSING** |

**Status:** CLI has full audio support. SDL2 missing.

### SAVE STATES & OTHER SHARED FEATURES

| Feature | File | Status |
|---------|------|--------|
| Save States | `/home/user/phpboy/src/Savestate/SavestateManager.php` | ✅ Complete (shared) |
| Speed Control | `/home/user/phpboy/src/Emulator.php` | ✅ Complete (shared) |
| Rewind Buffer | `/home/user/phpboy/src/Rewind/RewindBuffer.php` | ✅ Complete (shared) |
| TAS Recording | `/home/user/phpboy/src/Tas/InputRecorder.php` | ✅ Complete (shared) |
| Palettes | `/home/user/phpboy/src/Ppu/DmgPalettes.php` | ✅ Complete (shared) |

All these are shared by both frontends (same implementation for CLI and SDL2).

### ENTRY POINTS

| Type | File | Status |
|------|------|--------|
| CLI Main | `/home/user/phpboy/bin/phpboy.php` | ✅ Complete |
| Hardcoded to CLI frontend | **Lines 312-326** | ⚠️ **Need update** |

### WASM FRONTEND (Reference)

| Component | File | Status |
|-----------|------|--------|
| Input | `/home/user/phpboy/src/Frontend/Wasm/WasmInput.php` | ⚠️ Stub only |
| Framebuffer | `/home/user/phpboy/src/Frontend/Wasm/WasmFramebuffer.php` | ⚠️ Stub only |
| Audio Sink | `/home/user/phpboy/src/Frontend/Wasm/WasmAudioSink.php` | ⚠️ Stub only |

---

## Feature Implementation Status Matrix

```
CATEGORY          CLI    SDL2   SHARED   NOTES
──────────────────────────────────────────────────────
Input             ✅     ✅     -        Both complete
Display           ✅     ✅     -        Different approaches
Audio             ✅     ❌     -        SDL2 missing [BLOCKER]
Save States       ✅     ✅     ✅       Identical
Speed Control     ✅     ✅     ✅       Identical
Rewind            ✅     ✅     ✅       Identical
TAS Recording     ✅     ✅     ✅       Identical
Palettes          ✅     ✅     ✅       Identical
Debug Mode        ✅     ✅     ✅       Identical
Frontend Select   ✅     ❌     -        Need --frontend arg
Window Title FPS  ✅     ❌     -        SDL2 missing [NICE]
Hotkeys           ⚠️     ❌     -        Neither implemented
Joystick          ❌     ⚠️     -        SDL2 has infrastructure
Fullscreen        ❌     ⚠️     -        SDL2 has infrastructure
Scanlines         ❌     ❌     -        Not implemented
```

---

## Code Statistics

### Total Lines of Code by Frontend

| Component | CLI | SDL2 | Shared |
|-----------|-----|------|--------|
| Input Handler | 233 | 237 | - |
| Renderer | 403 | 369 | - |
| Subtotal | 636 | 606 | - |

### Audio Implementation

| Component | Lines |
|-----------|-------|
| APU (core) | ~600 |
| SoX Audio Sink | 151 |
| WAV Audio Sink | ~100 |
| Total CLI Audio | ~850 |
| SDL2 Audio | 0 |

---

## Critical Differences

### 1. Input Model
```
CLI:
- Non-blocking stream_select() polling
- Terminal raw mode (stty)
- Button hold frame counter (4 frames minimum)
- ANSI escape sequences for arrows

SDL2:
- SDL_GetKeyboardState() polling
- Event-based handleKeyEvent() also available
- Direct scancode mapping
- Customizable key mappings
```

### 2. Display Model
```
CLI:
- Text-based terminal rendering
- Unicode half-blocks for 2x vertical resolution
- ANSI 24-bit color support
- CPU-intensive text generation

SDL2:
- GPU-accelerated rendering
- SDL2 Renderer with streaming texture
- Hardware rendering (SDL_RENDERER_ACCELERATED)
- Texture: 160×144 → scaled 1-8x
```

### 3. Audio Model
```
CLI:
- APU outputs samples via AudioSinkInterface
- SoxAudioSink: Real-time via SoX
- WavSink: File-based recording
- Both implement push sample + flush

SDL2:
- APU ready (same as CLI)
- No audio sink implementation
- Would need SDL audio device setup
- Would need sample queueing
```

---

## Missing Feature Checklist for SDL2

### Critical (MVP-blocking)
- [ ] **SdlAudioSink.php** - Audio output (file: NEW)
- [ ] **Frontend selection** - `--frontend=sdl|cli` (file: bin/phpboy.php)

### Important (UX)
- [ ] **Window title FPS** - Display current FPS (file: SdlRenderer.php)
- [ ] **Hotkey system** - F11, F12, ESC, P (file: SdlInput.php + SdlRenderer.php)
- [ ] **Display config** - Scale, VSync, fullscreen (file: bin/phpboy.php + SdlRenderer.php)

### Nice to Have
- [ ] **Joystick support** - Gamepad input (file: SdlInput.php)
- [ ] **Advanced rendering** - Scanlines, filters (file: SdlRenderer.php)

---

## Command-Line Interface Comparison

### CLI Features (Fully Working)
```bash
php bin/phpboy.php rom.gb [options]

# Display options
--display-mode=ansi-color|ascii|none

# Audio options
--audio                    # Real-time via SoX
--audio-out=file.wav      # Record to WAV

# Save state options
--savestate-load=file
--savestate-save=file

# Speed and features
--speed=1.5
--enable-rewind
--rewind-buffer=60
--record=tas.json
--playback=tas.json

# Hardware options
--hardware-mode=dmg|cgb
--palette=grayscale|pokemon_red|etc

# Debug options
--debug
--trace
--headless
--benchmark
--memory-profile
```

### SDL2 Options (Would Need to Add)
```bash
# MISSING - Should add:
--frontend=sdl              # NEW
--sdl-scale=4               # NEW
--sdl-no-vsync             # NEW
--fullscreen               # NEW

# These would work (shared):
--audio                    # Need SdlAudioSink
--audio-out=file.wav      # Need SdlAudioSink
--savestate-load/save     # Already works
--speed, --enable-rewind, etc.
```

---

## Performance Comparison

### Baseline Performance
```
CLI:    25-30 FPS (CPU-bound, no JIT)
CLI+JIT: 60+ FPS (PHP 8.4)
SDL2:   60+ FPS (GPU-accelerated)
```

### Bottlenecks
```
CLI:  CPU emulation + text rendering → stdout
SDL2: CPU emulation (rendering is GPU)
```

---

## Architecture Quality Assessment

### Strengths
- Clean interface-based design (InputInterface, FramebufferInterface, AudioSinkInterface)
- Shared core emulation (CPU, PPU, APU) independent of frontend
- Easy to add new frontends without modifying core
- Good separation of concerns

### Weaknesses
- Hardcoded frontend selection in bin/phpboy.php
- No frontend factory or abstract selection
- No configuration file support for key bindings
- No plugin/extension system for filters

---

## Estimated Completion Time

### By Priority
1. **Critical Features** (Audio + Frontend Select): 6-8 hours
2. **Important Features** (FPS, Hotkeys, Config): 4-6 hours
3. **Nice to Have** (Joystick, Effects): 6-8 hours

**Total to full parity: ~15-20 hours**

---

## Files to Create/Modify Summary

### Create (New Files)
- `src/Frontend/Sdl/SdlAudioSink.php` (NEW, 60-80 lines)

### Modify (Existing Files)
- `bin/phpboy.php` (Add frontend selection, ~40 lines new code)
- `src/Frontend/Sdl/SdlRenderer.php` (Add FPS, hotkeys, config ~50 lines new code)
- `src/Frontend/Sdl/SdlInput.php` (Add hotkey support ~30 lines new code)

### Total New Code: ~200-250 lines for MVP
### Total New Code: ~400-500 lines for full parity

---

## Testing Checklist

After implementation, verify:

- [ ] Audio plays in SDL2 mode
- [ ] Both `--frontend=cli` and `--frontend=sdl` work
- [ ] FPS displays in SDL2 window title
- [ ] F11 toggles fullscreen, F12 takes screenshot
- [ ] `--sdl-scale` option changes window size
- [ ] `--sdl-no-vsync` works
- [ ] `--savestate-load` works with SDL2
- [ ] `--speed=2.0` works with SDL2
- [ ] Keyboard input is responsive in SDL2

---

## References

- Full analysis: `/home/user/phpboy/docs/php-sdl2-compatibility-analysis.md`
- Implementation guide: `/home/user/phpboy/docs/sdl2-implementation-guide.md`
- SDL2 setup: `/home/user/phpboy/docs/sdl2-setup.md`
- SDL2 usage: `/home/user/phpboy/docs/sdl2-usage.md`
