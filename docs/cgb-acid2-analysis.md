# CGB-ACID2 Test Analysis

## Summary

The cgb-acid2 test currently achieves **84.96% pixel similarity** with the reference image. Colors are rendering correctly, but there are PPU rendering issues affecting primarily the face region.

## Fixed Issues

### ✅ Color Conversion (Commit: 88fd7e0)

**Problem**: 5-bit to 8-bit color conversion used multiplication/division which caused rounding errors.

**Solution**: Implemented the correct bit-perfect formula from cgb-acid2 documentation:
```php
($r << 3) | ($r >> 2)
```

**Result**:
- Before: RGB555(13,23,31) → RGB888(106,189,255) ❌
- After: RGB555(13,23,31) → RGB888(107,189,255) ✅
- Improved accuracy from 83.36% to 84.96%

## Remaining Issues

### ⚠️ PPU Rendering Problems (84.96% vs 100%)

**Total Differences**: 3,466 pixels (15.04% of screen)

#### Color Substitution Errors

| Expected Color | Actual Color | Pixel Count | Description |
|----------------|--------------|-------------|-------------|
| Yellow (255,255,0) | White (255,255,255) | 2,864 | Face rendering as white |
| Black (0,0,0) | White (255,255,255) | 512 | Outlines rendering as white |
| Black (0,0,0) | Green (0,156,0) | 38 | Minor green artifacts |
| Yellow (255,255,0) | Green (0,156,0) | 34 | Minor green artifacts |

#### Affected Regions

| Region | Pixel Differences | Likely Cause |
|--------|------------------|--------------|
| **Mouth** | 1,306 pixels | Sprite rendering or palette selection |
| **Nose** | 238 pixels | VRAM bank 1 tile reading or flipping |
| **Eyes** | 90 pixels | Background/Window/Sprite priority |

## Root Cause Analysis

The main issue is **yellow and black pixels rendering as white** instead of their correct colors. This suggests:

### Possible Causes

1. **Palette Selection Issue**
   - Wrong color palette being selected for certain tiles/sprites
   - Background or sprite palette index incorrect
   - Color 0 transparency handling

2. **Priority Handling**
   - Background-to-OAM priority (bit 7) incorrect
   - Object-to-Background priority (bit 7) incorrect
   - Master Priority (LCDC bit 0) not working correctly

3. **VRAM Banking**
   - Tiles not reading from correct VRAM bank
   - Attribute map not being read correctly
   - Bank selection for sprites incorrect

4. **Tile Flipping**
   - Horizontal flip (bit 5) incorrect
   - Vertical flip (bit 6) incorrect
   - Affecting mouth/nose rendering

## Test ROM Details

From the cgb-acid2 README, the affected regions test specific features:

### Nose (238 pixel differences)
- **Tests**:
  - Object vertical/horizontal flipping
  - VRAM bank 1 tile data reading
  - Background-to-OAM priority with Master Priority disabled
- **Expected**: 4 flipped sprites forming diamond shape
- **Actual**: Partially white instead of black diamond

### Mouth (1,306 pixel differences)
- **Tests**:
  - 8×16 sprite mode
  - Vertical flipping
  - Bit 0 of tile index should be ignored for 8×16 objects
- **Expected**: Black horizontal line formed by 8×16 sprites
- **Actual**: Mostly white instead of black

### Eyes (90 pixel differences)
- **Tests**:
  - Background and Window tile flipping
  - Background-to-OAM priority
  - Object-to-Background priority
  - Color 0 transparency
- **Expected**: Green circles in eye centers
- **Actual**: Minor differences, mostly correct

## Recommended Investigation Steps

1. **Add Debug Logging**
   - Log palette selections for pixels in affected regions
   - Log VRAM bank usage for tile reads
   - Log priority decisions for sprites vs background

2. **Unit Tests**
   - Test palette color selection logic
   - Test priority bit handling
   - Test 8×16 sprite rendering
   - Test VRAM bank 1 tile reading

3. **Reference Implementation Comparison**
   - Compare PPU rendering logic with SameBoy or other accurate emulators
   - Verify priority handling matches Pan Docs specifications

4. **Targeted ROM Tests**
   - Run other CGB test ROMs focusing on:
     - Sprite priority
     - VRAM banking
     - Tile flipping
     - Palette selection

## Files to Investigate

- `src/Ppu/Ppu.php` - Main rendering logic
- `src/Ppu/TileRendering.php` - Tile/sprite rendering
- `src/Ppu/ColorPalette.php` - Palette selection
- `src/Memory/Vram.php` - VRAM banking

## Success Criteria

✅ **Current**: 84.96% pixel similarity, correct color conversion
🎯 **Target**: 100% pixel similarity with cgb-acid2 reference image

## References

- [cgb-acid2 Repository](https://github.com/mattcurrie/cgb-acid2)
- [Pan Docs - PPU](https://gbdev.io/pandocs/PPU.html)
- [Pan Docs - CGB Palettes](https://gbdev.io/pandocs/Palettes.html)
