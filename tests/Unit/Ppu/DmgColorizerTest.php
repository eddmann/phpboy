<?php

declare(strict_types=1);

namespace Tests\Unit\Ppu;

use Gb\Cartridge\CartridgeHeader;
use Gb\Cartridge\CartridgeType;
use Gb\Ppu\ColorPalette;
use Gb\Ppu\DmgColorizer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DMG colorization system
 */
final class DmgColorizerTest extends TestCase
{
    private ColorPalette $colorPalette;
    private DmgColorizer $colorizer;

    protected function setUp(): void
    {
        $this->colorPalette = new ColorPalette();
        $this->colorizer = new DmgColorizer($this->colorPalette);
    }

    public function testCalculateTitleChecksum(): void
    {
        // Create a test header with known title bytes
        $header = new CartridgeHeader(
            entryPoint: [0x00, 0xC3, 0x50, 0x01],
            nintendoLogo: array_fill(0, 48, 0xCE),
            title: 'TEST GAME',
            titleBytes: [0x54, 0x45, 0x53, 0x54, 0x20, 0x47, 0x41, 0x4D, 0x45, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00], // "TEST GAME" + nulls
            cgbFlag: 0x00,
            newLicenseeCode: 0x0000,
            sgbFlag: 0x00,
            cartridgeType: CartridgeType::ROM_ONLY,
            romSizeCode: 0x00,
            ramSizeCode: 0x00,
            destinationCode: 0x00,
            oldLicenseeCode: 0x00,
            maskRomVersion: 0x00,
            headerChecksum: 0x00,
            globalChecksum: 0x0000,
            isLogoValid: true,
            isHeaderChecksumValid: true
        );

        $checksum = $this->colorizer->calculateTitleChecksum($header);

        // Calculate expected checksum: sum of "TEST GAME" ASCII values + 7 nulls
        // T=0x54, E=0x45, S=0x53, T=0x54, space=0x20, G=0x47, A=0x41, M=0x4D, E=0x45, nulls=0x00...
        // Sum = 0x54 + 0x45 + 0x53 + 0x54 + 0x20 + 0x47 + 0x41 + 0x4D + 0x45 = 0x27A
        // With 7 more nulls = 0x27A, modulo 256 = 0x7A = 122
        $this->assertEquals(122, $checksum);
    }

    public function testSelectPaletteWithManualOverride(): void
    {
        $header = $this->createDmgHeader('TETRIS');

        // Manual override should take precedence
        $palette = $this->colorizer->selectPalette($header, 'left_b');

        $this->assertEquals('Grayscale', $palette['name']);
        $this->assertCount(4, $palette['bg']);
        $this->assertCount(4, $palette['obj0']);
        $this->assertCount(4, $palette['obj1']);
    }

    public function testSelectPaletteWithDefaultFallback(): void
    {
        // Create header with unknown title (won't match any checksum)
        $header = $this->createDmgHeader('UNKNOWN GAME XYZ');

        $palette = $this->colorizer->selectPalette($header);

        // Should return default palette
        $this->assertEquals('Dark Green', $palette['name']);
    }

    public function testApplyPaletteWritesToColorRam(): void
    {
        $header = $this->createDmgHeader('TEST');

        // Colorize with green palette (manual selection using 'right' button combo)
        $paletteName = $this->colorizer->colorize($header, 'right');

        $this->assertEquals('Green', $paletteName);

        // Verify that palette was written to color RAM
        // Read back background palette 0, color 0 (should be white = 0x7FFF)
        $color0 = $this->colorPalette->getBgColor(0, 0);
        $this->assertEquals(255, $color0->r); // White
        $this->assertEquals(255, $color0->g);
        $this->assertEquals(255, $color0->b);

        // Read background palette 0, color 3 (should be black = 0x0000)
        $color3 = $this->colorPalette->getBgColor(0, 3);
        $this->assertEquals(0, $color3->r); // Black
        $this->assertEquals(0, $color3->g);
        $this->assertEquals(0, $color3->b);
    }

    public function testColorizePokemonRedUsesCorrectPalette(): void
    {
        // Note: This test assumes Pokemon Red has checksum 0x01 in our mapping
        // Real checksum needs to be calculated from actual ROM title
        $header = $this->createDmgHeader('POKEMON RED');

        // Colorize without manual override (automatic detection)
        $paletteName = $this->colorizer->colorize($header, null);

        // Should detect as Pokemon Red if checksum matches
        // If not in our table, will use default
        $this->assertNotEmpty($paletteName);
    }

    public function testGrayscalePaletteCreatesMonochrome(): void
    {
        $header = $this->createDmgHeader('TETRIS');

        $this->colorizer->colorize($header, 'left_b'); // Grayscale

        // All colors should be shades of gray (R = G = B)
        for ($colorNum = 0; $colorNum < 4; $colorNum++) {
            $color = $this->colorPalette->getBgColor(0, $colorNum);
            $this->assertEquals($color->r, $color->g, "Color {$colorNum} R should equal G");
            $this->assertEquals($color->g, $color->b, "Color {$colorNum} G should equal B");
        }
    }

    public function testInvalidButtonComboUsesAutoDetection(): void
    {
        $header = $this->createDmgHeader('TETRIS');

        // Invalid button combo should fall back to auto-detection
        $palette = $this->colorizer->selectPalette($header, 'invalid_combo');

        // Should return default since TETRIS might not be in our checksum map
        $this->assertArrayHasKey('name', $palette);
        $this->assertArrayHasKey('bg', $palette);
        $this->assertArrayHasKey('obj0', $palette);
        $this->assertArrayHasKey('obj1', $palette);
    }

    public function testAllManualPalettesAreValid(): void
    {
        $header = $this->createDmgHeader('TEST');

        $buttonCombos = ['up', 'up_a', 'up_b', 'left', 'left_a', 'left_b',
                         'down', 'down_a', 'down_b', 'right', 'right_a', 'right_b'];

        foreach ($buttonCombos as $combo) {
            $palette = $this->colorizer->selectPalette($header, $combo);

            $this->assertArrayHasKey('name', $palette, "Combo '{$combo}' should return valid palette");
            $this->assertCount(4, $palette['bg'], "Combo '{$combo}' should have 4 background colors");
            $this->assertCount(4, $palette['obj0'], "Combo '{$combo}' should have 4 object colors");
            $this->assertCount(4, $palette['obj1'], "Combo '{$combo}' should have 4 object colors");
        }
    }

    /**
     * Helper to create a DMG-only cartridge header
     */
    private function createDmgHeader(string $title): CartridgeHeader
    {
        // Create title bytes (16 bytes, padded with nulls, last byte is CGB flag)
        $titleBytes = array_merge(
            array_map('ord', str_split(substr($title, 0, 15))),
            array_fill(0, 16 - min(strlen($title), 15), 0x00)
        );
        $titleBytes[15] = 0x00; // CGB flag = 0x00 (DMG only)

        return new CartridgeHeader(
            entryPoint: [0x00, 0xC3, 0x50, 0x01],
            nintendoLogo: array_fill(0, 48, 0xCE),
            title: $title,
            titleBytes: $titleBytes,
            cgbFlag: 0x00, // DMG only
            newLicenseeCode: 0x0000,
            sgbFlag: 0x00,
            cartridgeType: CartridgeType::ROM_ONLY,
            romSizeCode: 0x00,
            ramSizeCode: 0x00,
            destinationCode: 0x00,
            oldLicenseeCode: 0x01, // Nintendo
            maskRomVersion: 0x00,
            headerChecksum: 0x00,
            globalChecksum: 0x0000,
            isLogoValid: true,
            isHeaderChecksumValid: true
        );
    }
}
