# Savestate Format Specification

## Overview

PHPBoy savestates capture the complete state of the emulator at a specific point in time, allowing instant save and restore of gameplay.

**Format**: JSON (human-readable, debuggable)
**Version**: 1.0.0
**File Extension**: `.state` or `.json`

## Structure

```json
{
  "magic": "PHPBOY_SAVESTATE",
  "version": "1.0.0",
  "timestamp": 1699564800,
  "cpu": { ... },
  "ppu": { ... },
  "memory": { ... },
  "cartridge": { ... },
  "timer": { ... },
  "interrupts": { ... },
  "cgb": { ... },
  "apu": { ... },
  "clock": { ... }
}
```

## Fields

### Top-Level

- **magic** (string): Magic identifier `"PHPBOY_SAVESTATE"`
- **version** (string): Format version for compatibility checks
- **timestamp** (integer): Unix timestamp when savestate was created

### CPU State

```json
"cpu": {
  "af": 0x01B0,
  "bc": 0x0013,
  "de": 0x00D8,
  "hl": 0x014D,
  "sp": 0xFFFE,
  "pc": 0x0100,
  "ime": true,
  "halted": false
}
```

- **af, bc, de, hl, sp, pc** (integer): 16-bit register values
- **ime** (boolean): Interrupt Master Enable flag
- **halted** (boolean): CPU halted state

### PPU State

```json
"ppu": {
  "mode": 0,
  "modeClock": 80,
  "ly": 0,
  "lyc": 0,
  "scx": 0,
  "scy": 0,
  "wx": 7,
  "wy": 0,
  "lcdc": 0x91,
  "stat": 0x00,
  "bgp": 0xFC,
  "obp0": 0xFF,
  "obp1": 0xFF,
  "cgbPalette": {
    "bgPalette": "base64-encoded data (64 bytes)",
    "objPalette": "base64-encoded data (64 bytes)",
    "bgIndex": 0x00,
    "objIndex": 0x00
  }
}
```

- **mode** (integer): Current PPU mode (0=H-Blank, 1=V-Blank, 2=OAM Search, 3=Pixel Transfer)
- **modeClock** (integer): Dots elapsed in current mode
- **ly** (integer): Current scanline (0-153)
- **lyc** (integer): LY Compare register
- **scx, scy** (integer): Scroll X/Y registers
- **wx, wy** (integer): Window X/Y registers
- **lcdc** (integer): LCD Control register
- **stat** (integer): LCD Status register
- **bgp, obp0, obp1** (integer): DMG palette registers
- **cgbPalette** (object): CGB color palette state (optional, CGB only)
  - **bgPalette** (string): Base64-encoded background palette memory (8 palettes × 4 colors × 2 bytes = 64 bytes)
  - **objPalette** (string): Base64-encoded object palette memory (8 palettes × 4 colors × 2 bytes = 64 bytes)
  - **bgIndex** (integer): Background palette index register (BCPS/BGPI) with auto-increment flag
  - **objIndex** (integer): Object palette index register (OCPS/OBPI) with auto-increment flag

### Memory State

```json
"memory": {
  "vramBank0": "base64-encoded data (8KB)",
  "vramBank1": "base64-encoded data (8KB)",
  "vramCurrentBank": 0,
  "wram": "base64-encoded data (8KB)",
  "hram": "base64-encoded data (127 bytes)",
  "oam": "base64-encoded data (160 bytes)"
}
```

- **vramBank0** (string): Base64-encoded VRAM bank 0 (8KB)
- **vramBank1** (string): Base64-encoded VRAM bank 1 (8KB, CGB only)
- **vramCurrentBank** (integer): Currently selected VRAM bank (0 or 1, CGB only)
- **wram** (string): Base64-encoded work RAM (8KB)
- **hram** (string): Base64-encoded high RAM (127 bytes, 0xFF80-0xFFFE)
- **oam** (string): Base64-encoded OAM sprite attribute table (160 bytes)

All memory regions are base64-encoded for compact storage.

**Note:** For backward compatibility, old savestates with single `"vram"` field are still supported and will only restore bank 0.

### Cartridge State

```json
"cartridge": {
  "romBank": 1,
  "ramBank": 0,
  "ramEnabled": true,
  "ram": "base64-encoded cartridge RAM data"
}
```

- **romBank** (integer): Current ROM bank number
- **ramBank** (integer): Current RAM bank number
- **ramEnabled** (boolean): RAM enable state
- **ram** (string): Base64-encoded cartridge RAM (size varies by cartridge)

### Timer State

```json
"timer": {
  "div": 0xAB,
  "divCounter": 1234,
  "tima": 0x00,
  "tma": 0x00,
  "tac": 0x00,
  "timaCounter": 0
}
```

- **div** (integer): DIV register (0xFF04) - Divider register value (0x00-0xFF)
- **divCounter** (integer): Internal 16-bit divider counter
- **tima** (integer): TIMA register (0xFF05) - Timer counter (0x00-0xFF)
- **tma** (integer): TMA register (0xFF06) - Timer modulo (0x00-0xFF)
- **tac** (integer): TAC register (0xFF07) - Timer control (0x00-0x07)
- **timaCounter** (integer): Internal TIMA counter accumulator

**Optional:** This field is optional for backward compatibility. If missing, timer state will be initialized to default values.

### Interrupt State

```json
"interrupts": {
  "if": 0xE0,
  "ie": 0x00
}
```

- **if** (integer): IF register (0xFF0F) - Interrupt flags (bits 0-4: VBlank, LCD, Timer, Serial, Joypad)
- **ie** (integer): IE register (0xFFFF) - Interrupt enable mask (bits 0-4)

**Optional:** This field is optional for backward compatibility. If missing, interrupt state will be initialized to default values.

### CGB Controller State

```json
"cgb": {
  "key0": 0x80,
  "key1": 0x00,
  "opri": 0x00,
  "doubleSpeed": false,
  "key0Writable": false
}
```

- **key0** (integer): KEY0 register (0xFF4C) - CGB mode indicator (0x04=DMG compat, 0x80=CGB mode)
- **key1** (integer): KEY1 register (0xFF4D) - Speed switch control (bit 0: prepare switch)
- **opri** (integer): OPRI register (0xFF6C) - Object priority mode (bit 0)
- **doubleSpeed** (boolean): Current speed mode (false=normal 4MHz, true=double 8MHz)
- **key0Writable** (boolean): Whether KEY0 register is still writable (locked after boot ROM disable)

**Optional:** This field is optional for backward compatibility. If missing, CGB state will be initialized based on cartridge type.

### APU State

```json
"apu": {
  "registers": {
    "nr10": 0x80, "nr11": 0xBF, "nr12": 0xF3, "nr13": 0xFF, "nr14": 0xBF,
    "nr21": 0x3F, "nr22": 0x00, "nr23": 0xFF, "nr24": 0xBF,
    "nr30": 0x7F, "nr31": 0xFF, "nr32": 0x9F, "nr33": 0xFF, "nr34": 0xBF,
    "nr41": 0xFF, "nr42": 0x00, "nr43": 0x00, "nr44": 0xBF,
    "nr50": 0x77, "nr51": 0xF3, "nr52": 0xF1
  },
  "waveRam": "base64-encoded data (16 bytes)",
  "frameSequencerCycles": 0,
  "frameSequencerStep": 0,
  "sampleCycles": 0.0,
  "enabled": true
}
```

- **registers** (object): All APU control registers
  - **nr10-nr14** (integers): Channel 1 (square with sweep) registers
  - **nr21-nr24** (integers): Channel 2 (square) registers
  - **nr30-nr34** (integers): Channel 3 (wave) registers
  - **nr41-nr44** (integers): Channel 4 (noise) registers
  - **nr50** (integer): Master volume and VIN panning
  - **nr51** (integer): Sound panning for all channels
  - **nr52** (integer): Sound on/off and channel status
- **waveRam** (string): Base64-encoded Wave RAM (16 bytes, 0xFF30-0xFF3F) for Channel 3
- **frameSequencerCycles** (integer): Frame sequencer cycle accumulator
- **frameSequencerStep** (integer): Current frame sequencer step (0-7)
- **sampleCycles** (float): Sample generation cycle accumulator
- **enabled** (boolean): Master APU enable state

**Optional:** This field is optional for backward compatibility. If missing, APU will be initialized to default state.

**Note:** This saves APU register state and basic timing, but NOT full channel internal state (frequency timers, length counters, envelope timers, duty positions). Audio restoration is partial - basic configuration is preserved but channel timing may drift slightly.

### Clock State

```json
"clock": {
  "cycles": 70224
}
```

- **cycles** (integer): Total CPU cycles elapsed

## Compatibility

### Version Checking

Savestates include a version number. Loading a savestate with a different version will fail with an error message indicating the version mismatch.

### Backward Compatibility

The savestate format maintains backward compatibility with older versions:

- **Required fields:** `magic`, `version`, `cpu`, `ppu`, `memory`, `cartridge`, `clock` (always present)
- **Optional fields:** `timer`, `interrupts`, `cgb`, `apu` (gracefully handle missing data)
- **Old VRAM format:** Single `"vram"` field is still supported for compatibility with pre-1.0 savestates
- **Missing fields:** If optional fields are missing, they are initialized to sensible defaults

This allows newer emulator versions to load older savestates, though some state (timer, interrupts, etc.) will be reset to defaults.

### Future Compatibility

Future versions may add new fields but must maintain backward compatibility for core fields. Optional fields should have sensible defaults.

## Usage

### Saving

```php
$emulator->saveState('/path/to/savestate.state');
```

### Loading

```php
$emulator->loadState('/path/to/savestate.state');
```

### Manual Serialization

```php
$manager = new \Gb\Savestate\SavestateManager($emulator);
$stateArray = $manager->serialize();
// Modify state if needed
$manager->deserialize($stateArray);
```

## Notes

- Savestates are **not portable** across different ROM versions
- Always use the same ROM file when loading a savestate
- Savestates capture exact emulator state but not the ROM itself
- **File size:** ~15-30 KB for typical games
  - Base savestate: ~5 KB (registers, state, timers, etc.)
  - VRAM: ~22 KB (16 KB for CGB dual banks, base64-encoded)
  - Cartridge RAM: Varies by game (0-128 KB)
  - CGB color palettes: ~175 bytes (base64-encoded)
  - APU state: ~500 bytes

## State Completeness

### Fully Saved ✅
- CPU registers and flags
- PPU state and timing
- All memory (VRAM, WRAM, HRAM, OAM)
- Cartridge state and RAM
- Timer registers and internal counters
- Interrupt flags and enables
- CGB color palettes and hardware state
- APU registers and Wave RAM
- System clock cycles

### Partially Saved ⚠️
- **APU channels:** Register state saved, but NOT internal timers/counters
  - Wave channel (CH3) fully preserved via Wave RAM
  - Other channels may have minor timing drift after load

### Not Saved ❌
- OAM DMA transfer progress (mid-frame only)
- Serial port transfer state
- WRAM banking (CGB has 32KB, only 8KB currently saved)
- Full APU channel internal state (frequency timers, length counters, etc.)
