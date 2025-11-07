<?php

declare(strict_types=1);

namespace Tests\Unit\Ppu;

use Gb\Interrupts\InterruptController;
use Gb\Memory\Vram;
use Gb\Ppu\ArrayFramebuffer;
use Gb\Ppu\Color;
use Gb\Ppu\Oam;
use Gb\Ppu\Ppu;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for tile rendering.
 *
 * These tests verify that the PPU correctly renders tiles from VRAM to the framebuffer.
 */
final class TileRenderingTest extends TestCase
{
    private Ppu $ppu;
    private Vram $vram;
    private Oam $oam;
    private ArrayFramebuffer $framebuffer;
    private InterruptController $interruptController;

    protected function setUp(): void
    {
        $this->vram = new Vram();
        $this->oam = new Oam();
        $this->framebuffer = new ArrayFramebuffer();
        $this->interruptController = new InterruptController();
        $this->ppu = new Ppu($this->vram, $this->oam, $this->framebuffer, $this->interruptController);
    }

    public function testRenderSolidColorTile(): void
    {
        // Create a tile with all pixels set to color 3 (black)
        // Each tile is 8x8 pixels, 2 bytes per row (16 bytes total)
        // Color 3 = bits 11 (both bits set)
        for ($y = 0; $y < 8; $y++) {
            $this->vram->writeByte($y * 2, 0xFF);     // Low bits all set
            $this->vram->writeByte($y * 2 + 1, 0xFF); // High bits all set
        }

        // Set tile map to use tile 0
        $this->vram->writeByte(0x1800, 0x00);

        // Enable LCD and background
        $this->ppu->writeByte(0xFF40, 0x81); // LCD on, BG on

        // Use default palette: 0xFC = 11 11 10 01 00
        // Color 3 maps to shade 3 (black)
        $this->ppu->writeByte(0xFF47, 0xE4); // 11 10 01 00

        // Render first scanline
        $this->ppu->step(456);

        $buffer = $this->framebuffer->getFramebuffer();

        // First 8 pixels of first scanline should be black (shade 3)
        $expectedColor = Color::fromDmgShade(3);
        for ($x = 0; $x < 8; $x++) {
            $this->assertEquals(
                $expectedColor,
                $buffer[0][$x],
                "Pixel at x=$x should be black"
            );
        }
    }

    public function testRenderCheckerboardPattern(): void
    {
        // Create a checkerboard tile pattern
        // Alternating colors 0 and 3
        // Pattern: 10101010 for each row
        for ($y = 0; $y < 8; $y++) {
            $this->vram->writeByte($y * 2, 0xAA);     // 10101010 (low bits)
            $this->vram->writeByte($y * 2 + 1, 0xAA); // 10101010 (high bits)
        }

        $this->vram->writeByte(0x1800, 0x00);

        $this->ppu->writeByte(0xFF40, 0x81);
        $this->ppu->writeByte(0xFF47, 0xE4); // Map color 3 to black

        $this->ppu->step(456);

        $buffer = $this->framebuffer->getFramebuffer();

        // Check alternating pattern
        $white = Color::fromDmgShade(0);
        $black = Color::fromDmgShade(3);

        for ($x = 0; $x < 8; $x++) {
            $expectedColor = ($x % 2 === 0) ? $black : $white;
            $this->assertEquals(
                $expectedColor,
                $buffer[0][$x],
                "Pixel at x=$x should match checkerboard pattern"
            );
        }
    }

    public function testBackgroundScrolling(): void
    {
        // Create two different tiles
        // Tile 0: all color 0 (white)
        for ($y = 0; $y < 8; $y++) {
            $this->vram->writeByte($y * 2, 0x00);
            $this->vram->writeByte($y * 2 + 1, 0x00);
        }

        // Tile 1: all color 3 (black)
        for ($y = 0; $y < 8; $y++) {
            $this->vram->writeByte(16 + $y * 2, 0xFF);
            $this->vram->writeByte(16 + $y * 2 + 1, 0xFF);
        }

        // Set tile map: tile 0, then tile 1
        $this->vram->writeByte(0x1800, 0x00); // First tile
        $this->vram->writeByte(0x1801, 0x01); // Second tile

        $this->ppu->writeByte(0xFF40, 0x81);
        $this->ppu->writeByte(0xFF47, 0xE4);

        // No scrolling first
        $this->ppu->writeByte(0xFF43, 0); // SCX = 0

        $this->ppu->step(456);

        $buffer = $this->framebuffer->getFramebuffer();

        // First 8 pixels should be white (tile 0)
        $white = Color::fromDmgShade(0);
        $this->assertEquals($white, $buffer[0][0]);
        $this->assertEquals($white, $buffer[0][7]);

        // Next 8 pixels should be black (tile 1)
        $black = Color::fromDmgShade(3);
        $this->assertEquals($black, $buffer[0][8]);
        $this->assertEquals($black, $buffer[0][15]);
    }

    public function testSpriteRendering(): void
    {
        // Create a simple sprite tile (tile 0 in sprite area)
        // Vertical line on left side (color 3)
        for ($y = 0; $y < 8; $y++) {
            $this->vram->writeByte($y * 2, 0x80);     // 10000000 (left pixel)
            $this->vram->writeByte($y * 2 + 1, 0x80); // 10000000
        }

        // Set up sprite in OAM
        // Sprite 0: Y=16, X=8, Tile=0, Flags=0
        $this->oam->writeByte(0, 16);  // Y position
        $this->oam->writeByte(1, 8);   // X position
        $this->oam->writeByte(2, 0);   // Tile index
        $this->oam->writeByte(3, 0);   // Flags

        // Enable LCD, BG, and sprites
        $this->ppu->writeByte(0xFF40, 0x83); // LCD on, BG on, OBJ on

        // Set up palettes
        $this->ppu->writeByte(0xFF47, 0xFC); // BG palette (white)
        $this->ppu->writeByte(0xFF48, 0xE4); // OBP0 (black)

        // Render first scanline (Y=0, but sprite is at Y=16-16=0)
        $this->ppu->step(456);

        $buffer = $this->framebuffer->getFramebuffer();

        // Sprite should be rendered at X=0 (8-8)
        $black = Color::fromDmgShade(3);
        $this->assertEquals($black, $buffer[0][0], 'Left pixel should be black from sprite');

        // Other pixels should be white (background)
        $white = Color::fromDmgShade(0);
        $this->assertEquals($white, $buffer[0][1], 'Second pixel should be white from background');
    }

    public function testWindowRendering(): void
    {
        // Create different tiles for BG and Window
        // BG tile (tile 0): all white
        for ($y = 0; $y < 8; $y++) {
            $this->vram->writeByte($y * 2, 0x00);
            $this->vram->writeByte($y * 2 + 1, 0x00);
        }

        // Window tile (tile 1): all black
        for ($y = 0; $y < 8; $y++) {
            $this->vram->writeByte(16 + $y * 2, 0xFF);
            $this->vram->writeByte(16 + $y * 2 + 1, 0xFF);
        }

        // BG tile map uses tile 0
        $this->vram->writeByte(0x1800, 0x00);

        // Window tile map uses tile 1
        $this->vram->writeByte(0x1C00, 0x01);

        // Enable LCD, BG, and Window
        $this->ppu->writeByte(0xFF40, 0xE1); // LCD on, Window on (tilemap 1), BG on

        // Set window position to X=7, Y=0 (WX is offset by 7)
        $this->ppu->writeByte(0xFF4A, 0);  // WY = 0
        $this->ppu->writeByte(0xFF4B, 7);  // WX = 7 (actual position 0)

        $this->ppu->writeByte(0xFF47, 0xE4);

        // Render first scanline
        $this->ppu->step(456);

        $buffer = $this->framebuffer->getFramebuffer();

        // All pixels should be black (window covering entire line)
        $black = Color::fromDmgShade(3);
        $this->assertEquals($black, $buffer[0][0], 'Window should cover background with black');
        $this->assertEquals($black, $buffer[0][10], 'Window should extend across scanline');
    }

    public function testUnsignedTileAddressing(): void
    {
        // Test unsigned addressing mode (LCDC bit 4 = 1)
        // Tiles 0-255 at 0x8000-0x8FFF

        // Create tile 0 (white)
        for ($y = 0; $y < 8; $y++) {
            $this->vram->writeByte($y * 2, 0x00);
            $this->vram->writeByte($y * 2 + 1, 0x00);
        }

        // Create tile 255 (black) at offset 255*16
        $offset = 255 * 16;
        for ($y = 0; $y < 8; $y++) {
            $this->vram->writeByte($offset + $y * 2, 0xFF);
            $this->vram->writeByte($offset + $y * 2 + 1, 0xFF);
        }

        // Set tile map to use tile 255
        $this->vram->writeByte(0x1800, 0xFF);

        // Enable LCD, BG, unsigned addressing
        $this->ppu->writeByte(0xFF40, 0x91); // LCD on, BG on, unsigned mode

        $this->ppu->writeByte(0xFF47, 0xE4);

        $this->ppu->step(456);

        $buffer = $this->framebuffer->getFramebuffer();

        // Should render black (tile 255)
        $black = Color::fromDmgShade(3);
        $this->assertEquals($black, $buffer[0][0]);
    }

    public function testSignedTileAddressing(): void
    {
        // Test signed addressing mode (LCDC bit 4 = 0)
        // Tiles -128 to 127 relative to 0x9000

        // Create tile at 0x9000 (index 0 in signed mode)
        $offset = 0x1000; // 0x9000 - 0x8000
        for ($y = 0; $y < 8; $y++) {
            $this->vram->writeByte($offset + $y * 2, 0xFF);
            $this->vram->writeByte($offset + $y * 2 + 1, 0xFF);
        }

        // Set tile map to use tile 0 (signed)
        $this->vram->writeByte(0x1800, 0x00);

        // Enable LCD, BG, signed addressing (bit 4 = 0)
        $this->ppu->writeByte(0xFF40, 0x81); // LCD on, BG on, signed mode

        $this->ppu->writeByte(0xFF47, 0xE4);

        $this->ppu->step(456);

        $buffer = $this->framebuffer->getFramebuffer();

        // Should render black
        $black = Color::fromDmgShade(3);
        $this->assertEquals($black, $buffer[0][0]);
    }
}
