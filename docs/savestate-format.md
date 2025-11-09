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
  "obp1": 0xFF
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

### Memory State

```json
"memory": {
  "vram": "base64-encoded data (8KB)",
  "wram": "base64-encoded data (8KB)",
  "hram": "base64-encoded data (127 bytes)",
  "oam": "base64-encoded data (160 bytes)"
}
```

All memory regions are base64-encoded for compact storage.

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
- File size: ~10-20 KB for typical games (mostly cartridge RAM)
