<?php

declare(strict_types=1);

namespace Gb\Ppu;

/**
 * Game Boy Color Palette System
 *
 * Manages color palettes for background and objects in CGB mode.
 *
 * Background palettes: 8 palettes × 4 colors × 2 bytes = 64 bytes
 * Object palettes: 8 palettes × 4 colors × 2 bytes = 64 bytes
 *
 * Color format: 15-bit RGB (5 bits per channel): 0bbbbbgggggrrrrr
 *
 * Registers:
 * - BCPS/BGPI (0xFF68): Background palette index/control
 *   - Bit 7: Auto-increment (1 = increment after write to BCPD)
 *   - Bit 6: Not used
 *   - Bit 5-0: Index (0-63)
 * - BCPD/BGPD (0xFF69): Background palette data
 * - OCPS/OBPI (0xFF6A): Object palette index/control
 * - OCPD/OBPD (0xFF6B): Object palette data
 *
 * Reference: Pan Docs - CGB Registers
 */
final class ColorPalette
{
    /** @var array<int, int> Background palette memory (64 bytes) */
    private array $bgPalette;

    /** @var array<int, int> Object palette memory (64 bytes) */
    private array $objPalette;

    /** @var int Background palette index (0-63) with auto-increment flag */
    private int $bgIndex = 0;

    /** @var int Object palette index (0-63) with auto-increment flag */
    private int $objIndex = 0;

    public function __construct()
    {
        // Initialize palettes with white (0x7FFF = all 1s in 15-bit RGB)
        // Each color is 2 bytes: low byte (0xFF), high byte (0x7F)
        $this->bgPalette = [];
        $this->objPalette = [];
        for ($i = 0; $i < 64; $i += 2) {
            $this->bgPalette[$i] = 0xFF;     // Low byte
            $this->bgPalette[$i + 1] = 0x7F; // High byte
            $this->objPalette[$i] = 0xFF;     // Low byte
            $this->objPalette[$i + 1] = 0x7F; // High byte
        }
    }

    /**
     * Read background palette index register (BCPS/BGPI).
     */
    public function readBgIndex(): int
    {
        return $this->bgIndex | 0x40; // Bit 6 always set
    }

    /**
     * Write background palette index register (BCPS/BGPI).
     */
    public function writeBgIndex(int $value): void
    {
        $this->bgIndex = $value & 0xBF; // Bit 6 is unused, mask it out
    }

    /**
     * Read background palette data (BCPD/BGPD).
     */
    public function readBgData(): int
    {
        $index = $this->bgIndex & 0x3F; // Lower 6 bits = index
        return $this->bgPalette[$index];
    }

    /**
     * Write background palette data (BCPD/BGPD).
     */
    public function writeBgData(int $value): void
    {
        $index = $this->bgIndex & 0x3F; // Lower 6 bits = index
        $this->bgPalette[$index] = $value & 0xFF;

        // Auto-increment if bit 7 is set
        if (($this->bgIndex & 0x80) !== 0) {
            $this->bgIndex = ($this->bgIndex & 0x80) | (($index + 1) & 0x3F);
        }
    }

    /**
     * Read object palette index register (OCPS/OBPI).
     */
    public function readObjIndex(): int
    {
        return $this->objIndex | 0x40; // Bit 6 always set
    }

    /**
     * Write object palette index register (OCPS/OBPI).
     */
    public function writeObjIndex(int $value): void
    {
        $this->objIndex = $value & 0xBF; // Bit 6 is unused, mask it out
    }

    /**
     * Read object palette data (OCPD/OBPD).
     */
    public function readObjData(): int
    {
        $index = $this->objIndex & 0x3F; // Lower 6 bits = index
        return $this->objPalette[$index];
    }

    /**
     * Write object palette data (OCPD/OBPD).
     */
    public function writeObjData(int $value): void
    {
        $index = $this->objIndex & 0x3F; // Lower 6 bits = index
        $this->objPalette[$index] = $value & 0xFF;

        // Auto-increment if bit 7 is set
        if (($this->objIndex & 0x80) !== 0) {
            $this->objIndex = ($this->objIndex & 0x80) | (($index + 1) & 0x3F);
        }
    }

    /**
     * Get a background color from palette.
     *
     * @param int $paletteNum Palette number (0-7)
     * @param int $colorNum Color number (0-3)
     * @return Color RGB color
     */
    public function getBgColor(int $paletteNum, int $colorNum): Color
    {
        $baseIndex = ($paletteNum * 8) + ($colorNum * 2);
        $low = $this->bgPalette[$baseIndex];
        $high = $this->bgPalette[$baseIndex + 1];
        $rgb15 = ($high << 8) | $low;
        return Color::fromGbc15bit($rgb15);
    }

    /**
     * Get an object color from palette.
     *
     * @param int $paletteNum Palette number (0-7)
     * @param int $colorNum Color number (0-3)
     * @return Color RGB color
     */
    public function getObjColor(int $paletteNum, int $colorNum): Color
    {
        $baseIndex = ($paletteNum * 8) + ($colorNum * 2);
        $low = $this->objPalette[$baseIndex];
        $high = $this->objPalette[$baseIndex + 1];
        $rgb15 = ($high << 8) | $low;
        return Color::fromGbc15bit($rgb15);
    }
}
