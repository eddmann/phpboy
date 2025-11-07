<?php

declare(strict_types=1);

namespace Tests\Unit\Cartridge;

use Gb\Cartridge\CartridgeHeader;
use PHPUnit\Framework\TestCase;

final class CartridgeHeaderTest extends TestCase
{
    public function testParseHeaderWithCgbEnhanced(): void
    {
        $rom = array_fill(0, 0x0150, 0x00);

        // Set title
        $rom[0x0134] = ord('T');
        $rom[0x0135] = ord('E');
        $rom[0x0136] = ord('S');
        $rom[0x0137] = ord('T');

        // Set CGB flag to 0x80 (CGB enhanced)
        $rom[0x0143] = 0x80;

        // Set cartridge type
        $rom[0x0147] = 0x01; // MBC1

        $header = CartridgeHeader::fromRom($rom);

        $this->assertSame('TEST', $header->title);
        $this->assertSame(0x80, $header->cgbFlag);
        $this->assertTrue($header->isCgbSupported());
        $this->assertFalse($header->isCgbOnly());
        $this->assertFalse($header->isDmgOnly());
    }

    public function testParseHeaderWithCgbOnly(): void
    {
        $rom = array_fill(0, 0x0150, 0x00);

        // Set CGB flag to 0xC0 (CGB only)
        $rom[0x0143] = 0xC0;

        $header = CartridgeHeader::fromRom($rom);

        $this->assertSame(0xC0, $header->cgbFlag);
        $this->assertTrue($header->isCgbSupported());
        $this->assertTrue($header->isCgbOnly());
        $this->assertFalse($header->isDmgOnly());
    }

    public function testParseHeaderWithDmgOnly(): void
    {
        $rom = array_fill(0, 0x0150, 0x00);

        // Set CGB flag to 0x00 (DMG only)
        $rom[0x0143] = 0x00;

        $header = CartridgeHeader::fromRom($rom);

        $this->assertSame(0x00, $header->cgbFlag);
        $this->assertFalse($header->isCgbSupported());
        $this->assertFalse($header->isCgbOnly());
        $this->assertTrue($header->isDmgOnly());
    }

    public function testTitleParsing(): void
    {
        $rom = array_fill(0, 0x0150, 0x00);

        // Set a title
        $title = 'POKEMON RED';
        for ($i = 0; $i < strlen($title); $i++) {
            $rom[0x0134 + $i] = ord($title[$i]);
        }

        $header = CartridgeHeader::fromRom($rom);

        $this->assertSame($title, $header->title);
    }

    public function testTitleNullTermination(): void
    {
        $rom = array_fill(0, 0x0150, 0x00);

        // Set a null-terminated title
        $rom[0x0134] = ord('A');
        $rom[0x0135] = ord('B');
        $rom[0x0136] = 0x00; // Null terminator
        $rom[0x0137] = ord('C'); // Should not be included

        $header = CartridgeHeader::fromRom($rom);

        $this->assertSame('AB', $header->title);
    }

    public function testCartridgeTypeAndSizes(): void
    {
        $rom = array_fill(0, 0x0150, 0x00);

        $rom[0x0147] = 0x03; // MBC1+RAM+BATTERY
        $rom[0x0148] = 0x05; // 1MB ROM
        $rom[0x0149] = 0x03; // 32KB RAM

        $header = CartridgeHeader::fromRom($rom);

        $this->assertSame(0x03, $header->cartridgeType);
        $this->assertSame(0x05, $header->romSize);
        $this->assertSame(0x03, $header->ramSize);
    }
}
