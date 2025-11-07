<?php

declare(strict_types=1);

namespace Tests\Unit\Ppu;

use Gb\Ppu\ColorPalette;
use PHPUnit\Framework\TestCase;

final class ColorPaletteTest extends TestCase
{
    private ColorPalette $palette;

    protected function setUp(): void
    {
        $this->palette = new ColorPalette();
    }

    public function testBgIndexReadReturnsValueWithBit6Set(): void
    {
        $this->palette->writeBgIndex(0x00);
        $value = $this->palette->readBgIndex();

        // Bit 6 should always be set
        $this->assertSame(0x40, $value & 0x40);
    }

    public function testBgIndexWriteAndRead(): void
    {
        $this->palette->writeBgIndex(0x85); // Auto-increment + index 5
        $value = $this->palette->readBgIndex();

        // Should have bit 7 (auto-increment) and lower 6 bits for index
        $this->assertSame(0x85 | 0x40, $value);
    }

    public function testBgDataWriteAndRead(): void
    {
        // Set index to 0
        $this->palette->writeBgIndex(0x00);

        // Write a byte
        $this->palette->writeBgData(0xAB);

        // Reset index to 0 to read it back
        $this->palette->writeBgIndex(0x00);
        $value = $this->palette->readBgData();

        $this->assertSame(0xAB, $value);
    }

    public function testBgDataAutoIncrement(): void
    {
        // Enable auto-increment (bit 7) and set index to 0
        $this->palette->writeBgIndex(0x80);

        // Write two bytes
        $this->palette->writeBgData(0x11);
        $this->palette->writeBgData(0x22);

        // Index should have incremented to 2
        $index = $this->palette->readBgIndex();
        $this->assertSame(0x82, $index & 0xBF); // Mask out bit 6
    }

    public function testBgDataAutoIncrementWraps(): void
    {
        // Set index to 63 with auto-increment
        $this->palette->writeBgIndex(0x80 | 0x3F);

        // Write a byte, should wrap to 0
        $this->palette->writeBgData(0xFF);

        // Index should have wrapped to 0
        $index = $this->palette->readBgIndex();
        $this->assertSame(0x80, $index & 0xBF); // Mask out bit 6
    }

    public function testBgDataWithoutAutoIncrement(): void
    {
        // Set index to 5 without auto-increment
        $this->palette->writeBgIndex(0x05);

        // Write a byte
        $this->palette->writeBgData(0xAA);

        // Index should still be 5
        $index = $this->palette->readBgIndex();
        $this->assertSame(0x05, $index & 0x3F);
    }

    public function testObjIndexAndData(): void
    {
        // Object palette should work the same as background palette
        $this->palette->writeObjIndex(0x80 | 0x02);
        $this->palette->writeObjData(0x12);
        $this->palette->writeObjData(0x34);

        // Reset index to read back
        $this->palette->writeObjIndex(0x02);
        $this->assertSame(0x12, $this->palette->readObjData());

        $this->palette->writeObjIndex(0x03);
        $this->assertSame(0x34, $this->palette->readObjData());
    }

    public function testGetBgColorFrom15BitRgb(): void
    {
        // Write a 15-bit color to palette 0, color 1
        // Color: 0x7FFF (white: all bits set)
        // Index: palette 0, color 1 = byte 2-3
        $this->palette->writeBgIndex(0x02);
        $this->palette->writeBgData(0xFF); // Low byte
        $this->palette->writeBgData(0x7F); // High byte

        $color = $this->palette->getBgColor(0, 1);

        // White should be RGB(255, 255, 255)
        $this->assertSame(255, $color->r);
        $this->assertSame(255, $color->g);
        $this->assertSame(255, $color->b);
    }

    public function testGetBgColorBlack(): void
    {
        // Write black (0x0000) to palette 1, color 2
        // Index: palette 1, color 2 = (1*8) + (2*2) = 12
        $this->palette->writeBgIndex(12);
        $this->palette->writeBgData(0x00); // Low byte
        $this->palette->writeBgData(0x00); // High byte

        $color = $this->palette->getBgColor(1, 2);

        // Black should be RGB(0, 0, 0)
        $this->assertSame(0, $color->r);
        $this->assertSame(0, $color->g);
        $this->assertSame(0, $color->b);
    }

    public function testGetBgColorRed(): void
    {
        // Pure red: 0b0000000000011111 = 0x001F
        // Index: palette 2, color 3 = (2*8) + (3*2) = 22
        $this->palette->writeBgIndex(22);
        $this->palette->writeBgData(0x1F); // Low byte
        $this->palette->writeBgData(0x00); // High byte

        $color = $this->palette->getBgColor(2, 3);

        // Pure red in 5-bit scaled to 8-bit
        $this->assertSame(255, $color->r);
        $this->assertSame(0, $color->g);
        $this->assertSame(0, $color->b);
    }

    public function testGetObjColor(): void
    {
        // Write a color to object palette 3, color 1
        // Index: palette 3, color 1 = (3*8) + (1*2) = 26
        $this->palette->writeObjIndex(26);
        $this->palette->writeObjData(0xE0); // Low byte: 0b11100000 (green bits)
        $this->palette->writeObjData(0x03); // High byte: 0b00000011 (green bits)

        $color = $this->palette->getObjColor(3, 1);

        // Pure green: 0b0000001111100000 = 0x03E0
        $this->assertSame(0, $color->r);
        $this->assertSame(255, $color->g);
        $this->assertSame(0, $color->b);
    }
}
