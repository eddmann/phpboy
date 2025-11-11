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

    /**
     * @var array<int, array<int, Color>> Cached converted background colors
     * [palette_num][color_num] => Color object
     */
    private array $bgColorCache = [];

    /**
     * @var array<int, array<int, Color>> Cached converted object colors
     * [palette_num][color_num] => Color object
     */
    private array $objColorCache = [];

    public function __construct()
    {
        // Initialize palettes with white (0x7FFF = all 1s in 15-bit RGB)
        $this->bgPalette = array_fill(0, 64, 0xFF);
        $this->objPalette = array_fill(0, 64, 0xFF);

        // Pre-cache all colors
        $this->rebuildBgCache();
        $this->rebuildObjCache();
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

        // Invalidate cache for the affected color
        // Each color uses 2 bytes, so index/2 gives color number, index/8 gives palette
        $paletteNum = $index >> 3; // Divide by 8
        unset($this->bgColorCache[$paletteNum]);

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

        // Invalidate cache for the affected color
        // Each color uses 2 bytes, so index/2 gives color number, index/8 gives palette
        $paletteNum = $index >> 3; // Divide by 8
        unset($this->objColorCache[$paletteNum]);

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
        // Check cache first
        if (!isset($this->bgColorCache[$paletteNum])) {
            $this->rebuildBgPaletteCache($paletteNum);
        }

        return $this->bgColorCache[$paletteNum][$colorNum];
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
        // Check cache first
        if (!isset($this->objColorCache[$paletteNum])) {
            $this->rebuildObjPaletteCache($paletteNum);
        }

        return $this->objColorCache[$paletteNum][$colorNum];
    }

    /**
     * Rebuild background palette cache for a specific palette.
     */
    private function rebuildBgPaletteCache(int $paletteNum): void
    {
        $this->bgColorCache[$paletteNum] = [];
        for ($colorNum = 0; $colorNum < 4; $colorNum++) {
            $baseIndex = ($paletteNum * 8) + ($colorNum * 2);
            $low = $this->bgPalette[$baseIndex];
            $high = $this->bgPalette[$baseIndex + 1];
            $rgb15 = ($high << 8) | $low;
            $this->bgColorCache[$paletteNum][$colorNum] = Color::fromGbc15bit($rgb15);
        }
    }

    /**
     * Rebuild object palette cache for a specific palette.
     */
    private function rebuildObjPaletteCache(int $paletteNum): void
    {
        $this->objColorCache[$paletteNum] = [];
        for ($colorNum = 0; $colorNum < 4; $colorNum++) {
            $baseIndex = ($paletteNum * 8) + ($colorNum * 2);
            $low = $this->objPalette[$baseIndex];
            $high = $this->objPalette[$baseIndex + 1];
            $rgb15 = ($high << 8) | $low;
            $this->objColorCache[$paletteNum][$colorNum] = Color::fromGbc15bit($rgb15);
        }
    }

    /**
     * Rebuild entire background color cache.
     */
    private function rebuildBgCache(): void
    {
        for ($paletteNum = 0; $paletteNum < 8; $paletteNum++) {
            $this->rebuildBgPaletteCache($paletteNum);
        }
    }

    /**
     * Rebuild entire object color cache.
     */
    private function rebuildObjCache(): void
    {
        for ($paletteNum = 0; $paletteNum < 8; $paletteNum++) {
            $this->rebuildObjPaletteCache($paletteNum);
        }
    }
}
