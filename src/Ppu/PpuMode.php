<?php

declare(strict_types=1);

namespace Gb\Ppu;

/**
 * PPU Mode States
 *
 * The PPU cycles through different modes during each scanline:
 * - Mode 2 (OAM Search): Searching OAM for sprites on current line (80 dots)
 * - Mode 3 (Pixel Transfer): Rendering pixels (168-291 dots depending on sprites/scrolling)
 * - Mode 0 (H-Blank): Horizontal blanking period (remaining dots to 456)
 * - Mode 1 (V-Blank): Vertical blanking period (10 scanlines, LY 144-153)
 *
 * One frame: 154 scanlines × 456 dots = 70224 dots ≈ 59.7 Hz
 *
 * Reference: Pan Docs - PPU Modes
 */
enum PpuMode: int
{
    case HBlank = 0;       // Mode 0: H-Blank
    case VBlank = 1;       // Mode 1: V-Blank
    case OamSearch = 2;    // Mode 2: OAM Search
    case PixelTransfer = 3; // Mode 3: Pixel Transfer

    /**
     * Get the bit value for the STAT register (bits 0-1).
     *
     * @return int Mode bits (0-3)
     */
    public function getStatBits(): int
    {
        return $this->value;
    }
}
