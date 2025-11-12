<?php

declare(strict_types=1);

namespace Gb\Ppu;

use Gb\Cartridge\CartridgeHeader;

/**
 * DMG Game Colorization for Game Boy Color
 *
 * Implements the CGB boot ROM's automatic colorization system for original
 * Game Boy (DMG) games. Uses title checksum-based detection to apply
 * pre-programmed color palettes, mimicking real CGB hardware behavior.
 */
class DmgColorizer
{
    private ColorPalette $colorPalette;

    public function __construct(ColorPalette $colorPalette)
    {
        $this->colorPalette = $colorPalette;
    }

    /**
     * Calculate title checksum from cartridge header
     *
     * Mimics the CGB boot ROM algorithm: sum all bytes from 0x0134 to 0x0143.
     * This is the primary game detection mechanism.
     *
     * @param CartridgeHeader $header Cartridge header
     * @return int 8-bit checksum (0-255)
     */
    public function calculateTitleChecksum(CartridgeHeader $header): int
    {
        $checksum = 0;
        $titleBytes = $header->getTitleBytes();

        foreach ($titleBytes as $byte) {
            $checksum = ($checksum + $byte) & 0xFF;
        }

        return $checksum;
    }

    /**
     * Select palette based on game detection and user input
     *
     * Priority order:
     * 1. Manual override via button combination (if provided)
     * 2. Automatic detection via title checksum
     * 3. Default palette (dark green)
     *
     * @param CartridgeHeader $header Cartridge header
     * @param string|null $buttonCombo Button combination string (e.g., 'left_b')
     * @return array{name: string, bg: array<int>, obj0: array<int>, obj1: array<int>}
     */
    public function selectPalette(CartridgeHeader $header, ?string $buttonCombo = null): array
    {
        // Priority 1: Manual override
        if ($buttonCombo !== null) {
            $paletteName = DmgPalettes::getPaletteNameByButtons($buttonCombo);
            if ($paletteName !== null) {
                $palette = DmgPalettes::getPalette($paletteName);
                if ($palette !== null) {
                    return $palette;
                }
            }
        }

        // Priority 2: Automatic detection
        $checksum = $this->calculateTitleChecksum($header);
        $paletteName = DmgPalettes::getPaletteNameByChecksum($checksum);

        if ($paletteName !== null) {
            $palette = DmgPalettes::getPalette($paletteName);
            if ($palette !== null) {
                return $palette;
            }
        }

        // Priority 3: Default palette
        return DmgPalettes::getPalette('default');
    }

    /**
     * Apply palette to CGB color palette RAM
     *
     * Writes the selected palette to the ColorPalette object, which will
     * be used for rendering DMG games in color. Only palette 0 is used
     * in DMG compatibility mode.
     *
     * @param array{name: string, bg: array<int>, obj0: array<int>, obj1: array<int>} $palette
     */
    public function applyPalette(array $palette): void
    {
        // Apply background palette 0
        $this->writePalette(0, $palette['bg']);

        // Apply object palette 0
        $this->writePalette(8, $palette['obj0']);

        // Apply object palette 1
        $this->writePalette(9, $palette['obj1']);
    }

    /**
     * Write a 4-color palette to palette RAM
     *
     * Uses the BCPS/BCPD or OCPS/OCPD register interface with auto-increment.
     *
     * @param int $paletteNum Palette number (0-7 for BG, 8-15 for OBJ)
     * @param array<int> $colors Array of 4 RGB555 color values
     */
    private function writePalette(int $paletteNum, array $colors): void
    {
        if (count($colors) !== 4) {
            throw new \InvalidArgumentException('Palette must contain exactly 4 colors');
        }

        $isBackground = $paletteNum < 8;
        $localPaletteNum = $isBackground ? $paletteNum : ($paletteNum - 8);

        // Calculate starting index for this palette (each palette = 4 colors × 2 bytes = 8 bytes)
        $startIndex = $localPaletteNum * 8;

        // Set index register with auto-increment enabled (bit 7 = 1)
        $indexValue = 0x80 | $startIndex;

        if ($isBackground) {
            $this->colorPalette->writeBgIndex($indexValue);
        } else {
            $this->colorPalette->writeObjIndex($indexValue);
        }

        // Write all 4 colors (8 bytes total) using auto-increment
        foreach ($colors as $rgb555) {
            $lowByte = $rgb555 & 0xFF;
            $highByte = ($rgb555 >> 8) & 0xFF;

            if ($isBackground) {
                $this->colorPalette->writeBgData($lowByte);
                $this->colorPalette->writeBgData($highByte);
            } else {
                $this->colorPalette->writeObjData($lowByte);
                $this->colorPalette->writeObjData($highByte);
            }
        }
    }

    /**
     * Colorize a DMG game
     *
     * High-level method that performs the complete colorization process:
     * 1. Detect game via checksum
     * 2. Select appropriate palette
     * 3. Apply palette to color RAM
     *
     * @param CartridgeHeader $header Cartridge header
     * @param string|null $buttonCombo Optional button combination override
     * @return string Name of applied palette
     */
    public function colorize(CartridgeHeader $header, ?string $buttonCombo = null): string
    {
        $palette = $this->selectPalette($header, $buttonCombo);
        $this->applyPalette($palette);

        return $palette['name'];
    }
}
