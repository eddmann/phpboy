<?php

declare(strict_types=1);

namespace Tests\Unit\Cartridge;

use Gb\Cartridge\CartridgeHeader;
use Gb\Cartridge\CartridgeType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CartridgeHeaderTest extends TestCase
{
    /** Nintendo logo data for testing */
    private const NINTENDO_LOGO = [
        0xCE, 0xED, 0x66, 0x66, 0xCC, 0x0D, 0x00, 0x0B,
        0x03, 0x73, 0x00, 0x83, 0x00, 0x0C, 0x00, 0x0D,
        0x00, 0x08, 0x11, 0x1F, 0x88, 0x89, 0x00, 0x0E,
        0xDC, 0xCC, 0x6E, 0xE6, 0xDD, 0xDD, 0xD9, 0x99,
        0xBB, 0xBB, 0x67, 0x63, 0x6E, 0x0E, 0xEC, 0xCC,
        0xDD, 0xDC, 0x99, 0x9F, 0xBB, 0xB9, 0x33, 0x3E,
    ];

    /**
     * @param array<int, int> $headerData
     * @return array<int, int>
     */
    private function createRomWithHeader(array $headerData = []): array
    {
        $rom = array_fill(0, 0x8000, 0x00);

        // Set Nintendo logo
        foreach (self::NINTENDO_LOGO as $i => $byte) {
            $rom[0x0104 + $i] = $byte;
        }

        // Apply custom header data
        foreach ($headerData as $address => $value) {
            $rom[$address] = $value;
        }

        return $rom;
    }

    #[Test]
    public function it_parses_header_with_cgb_enhanced(): void
    {
        $rom = $this->createRomWithHeader([
            0x0134 => ord('T'),
            0x0135 => ord('E'),
            0x0136 => ord('S'),
            0x0137 => ord('T'),
            0x0143 => 0x80, // CGB enhanced
            0x0147 => 0x01, // MBC1
        ]);

        $header = CartridgeHeader::fromRom($rom);

        $this->assertSame('TEST', $header->title);
        $this->assertSame(0x80, $header->cgbFlag);
        $this->assertTrue($header->isCgbSupported());
        $this->assertFalse($header->isCgbOnly());
        $this->assertFalse($header->isDmgOnly());
    }

    #[Test]
    public function it_parses_header_with_cgb_only(): void
    {
        $rom = $this->createRomWithHeader([
            0x0143 => 0xC0, // CGB only
        ]);

        $header = CartridgeHeader::fromRom($rom);

        $this->assertSame(0xC0, $header->cgbFlag);
        $this->assertTrue($header->isCgbSupported());
        $this->assertTrue($header->isCgbOnly());
        $this->assertFalse($header->isDmgOnly());
    }

    #[Test]
    public function it_parses_header_with_dmg_only(): void
    {
        $rom = $this->createRomWithHeader([
            0x0143 => 0x00, // DMG only
        ]);

        $header = CartridgeHeader::fromRom($rom);

        $this->assertSame(0x00, $header->cgbFlag);
        $this->assertFalse($header->isCgbSupported());
        $this->assertFalse($header->isCgbOnly());
        $this->assertTrue($header->isDmgOnly());
    }

    #[Test]
    public function it_parses_title(): void
    {
        $title = 'POKEMON RED';
        $headerData = [];
        for ($i = 0; $i < strlen($title); $i++) {
            $headerData[0x0134 + $i] = ord($title[$i]);
        }

        $rom = $this->createRomWithHeader($headerData);
        $header = CartridgeHeader::fromRom($rom);

        $this->assertSame($title, $header->title);
    }

    #[Test]
    public function it_handles_title_null_termination(): void
    {
        $rom = $this->createRomWithHeader([
            0x0134 => ord('A'),
            0x0135 => ord('B'),
            0x0136 => 0x00, // Null terminator
            0x0137 => ord('C'), // Should not be included
        ]);

        $header = CartridgeHeader::fromRom($rom);
        $this->assertSame('AB', $header->title);
    }

    #[Test]
    public function it_parses_cartridge_types(): void
    {
        $rom = $this->createRomWithHeader([
            0x0147 => 0x03, // MBC1+RAM+BATTERY
        ]);

        $header = CartridgeHeader::fromRom($rom);
        $this->assertSame(CartridgeType::MBC1_RAM_BATTERY, $header->cartridgeType);
    }

    #[Test]
    public function it_calculates_rom_size(): void
    {
        $testCases = [
            0x00 => 32 * 1024,      // 32 KiB
            0x01 => 64 * 1024,      // 64 KiB
            0x02 => 128 * 1024,     // 128 KiB
            0x03 => 256 * 1024,     // 256 KiB
            0x04 => 512 * 1024,     // 512 KiB
            0x05 => 1024 * 1024,    // 1 MiB
            0x06 => 2 * 1024 * 1024, // 2 MiB
            0x07 => 4 * 1024 * 1024, // 4 MiB
            0x08 => 8 * 1024 * 1024, // 8 MiB
        ];

        foreach ($testCases as $code => $expectedSize) {
            $rom = $this->createRomWithHeader([
                0x0148 => $code,
            ]);

            $header = CartridgeHeader::fromRom($rom);
            $this->assertSame($expectedSize, $header->getRomSize(), "ROM size for code 0x{$code}");
            $this->assertSame($expectedSize / (16 * 1024), $header->getRomBankCount());
        }
    }

    #[Test]
    public function it_calculates_ram_size(): void
    {
        $testCases = [
            0x00 => 0,          // No RAM
            0x02 => 8 * 1024,   // 8 KiB
            0x03 => 32 * 1024,  // 32 KiB
            0x04 => 128 * 1024, // 128 KiB
            0x05 => 64 * 1024,  // 64 KiB
        ];

        foreach ($testCases as $code => $expectedSize) {
            $rom = $this->createRomWithHeader([
                0x0147 => 0x01, // MBC1
                0x0149 => $code,
            ]);

            $header = CartridgeHeader::fromRom($rom);
            $this->assertSame($expectedSize, $header->getRamSize(), "RAM size for code 0x{$code}");
        }
    }

    #[Test]
    public function it_handles_mbc2_ram_size(): void
    {
        $rom = $this->createRomWithHeader([
            0x0147 => 0x05, // MBC2
            0x0149 => 0x00,
        ]);

        $header = CartridgeHeader::fromRom($rom);
        $this->assertSame(512, $header->getRamSize()); // MBC2 has built-in 512 bytes
    }

    #[Test]
    public function it_validates_nintendo_logo(): void
    {
        $rom = $this->createRomWithHeader([]);
        $header = CartridgeHeader::fromRom($rom);
        $this->assertTrue($header->isLogoValid);

        // Invalid logo
        $rom[0x0104] = 0xFF;
        $header = CartridgeHeader::fromRom($rom);
        $this->assertFalse($header->isLogoValid);
    }

    #[Test]
    public function it_validates_header_checksum(): void
    {
        // Create ROM with valid checksum
        $rom = $this->createRomWithHeader([
            0x0134 => ord('T'),
            0x0135 => ord('E'),
            0x0136 => ord('S'),
            0x0137 => ord('T'),
        ]);

        // Calculate correct checksum
        $sum = 0;
        for ($i = 0x0134; $i <= 0x014C; $i++) {
            $sum = ($sum + $rom[$i]) & 0xFF;
        }
        $checksum = (0x100 - $sum - 1) & 0xFF;
        $rom[0x014D] = $checksum;

        $header = CartridgeHeader::fromRom($rom);
        $this->assertTrue($header->isHeaderChecksumValid);

        // Invalid checksum
        $rom[0x014D] = 0xFF;
        $header = CartridgeHeader::fromRom($rom);
        $this->assertFalse($header->isHeaderChecksumValid);
    }

    #[Test]
    public function it_detects_sgb_support(): void
    {
        $rom = $this->createRomWithHeader([
            0x0146 => 0x03, // SGB enhanced
        ]);
        $header = CartridgeHeader::fromRom($rom);
        $this->assertTrue($header->isSgbSupported());

        $rom = $this->createRomWithHeader([
            0x0146 => 0x00, // No SGB
        ]);
        $header = CartridgeHeader::fromRom($rom);
        $this->assertFalse($header->isSgbSupported());
    }

    #[Test]
    public function it_parses_destination_code(): void
    {
        $rom = $this->createRomWithHeader([
            0x014A => 0x00, // Japan
        ]);
        $header = CartridgeHeader::fromRom($rom);
        $this->assertTrue($header->isJapanese());

        $rom = $this->createRomWithHeader([
            0x014A => 0x01, // Overseas
        ]);
        $header = CartridgeHeader::fromRom($rom);
        $this->assertFalse($header->isJapanese());
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $rom = $this->createRomWithHeader([
            0x0134 => ord('T'),
            0x0135 => ord('E'),
            0x0136 => ord('S'),
            0x0137 => ord('T'),
            0x0143 => 0x80, // CGB enhanced
            0x0146 => 0x03, // SGB support
            0x0147 => 0x03, // MBC1+RAM+BATTERY
            0x0148 => 0x02, // 128 KiB ROM
            0x0149 => 0x02, // 8 KiB RAM
            0x014A => 0x01, // Overseas
            0x014C => 0x05, // Version 5
        ]);

        $header = CartridgeHeader::fromRom($rom);
        $array = $header->toArray();

        $this->assertSame('TEST', $array['title']);
        $this->assertSame('MBC1+RAM+Battery', $array['cartridgeType']);
        $this->assertSame('128 KiB (8 banks)', $array['romSize']);
        $this->assertSame('8 KiB (1 banks)', $array['ramSize']);
        $this->assertSame('CGB Enhanced', $array['cgbMode']);
        $this->assertSame('Yes', $array['sgbSupport']);
        $this->assertSame('Overseas', $array['destination']);
        $this->assertSame(5, $array['version']);
    }
}
