<?php

declare(strict_types=1);

namespace Tests\Unit\Cartridge;

use Gb\Cartridge\Cartridge;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CartridgeTest extends TestCase
{
    #[Test]
    public function it_reads_from_rom_bank_0(): void
    {
        $rom = array_fill(0, 32768, 0x00);
        $rom[0x0000] = 0x12;
        $rom[0x0100] = 0x34;
        $rom[0x3FFF] = 0x56;

        $cartridge = new Cartridge($rom);

        $this->assertSame(0x12, $cartridge->readByte(0x0000));
        $this->assertSame(0x34, $cartridge->readByte(0x0100));
        $this->assertSame(0x56, $cartridge->readByte(0x3FFF));
    }

    #[Test]
    public function it_reads_from_rom_bank_1(): void
    {
        $rom = array_fill(0, 32768, 0x00);
        $rom[0x4000] = 0x78;
        $rom[0x7FFF] = 0x9A;

        $cartridge = new Cartridge($rom);

        $this->assertSame(0x78, $cartridge->readByte(0x4000));
        $this->assertSame(0x9A, $cartridge->readByte(0x7FFF));
    }

    #[Test]
    public function it_returns_0xff_for_out_of_bounds_rom_reads(): void
    {
        $rom = array_fill(0, 16384, 0x00); // Only 16KB ROM

        $cartridge = new Cartridge($rom);

        // Reading beyond ROM size should return 0xFF
        $this->assertSame(0xFF, $cartridge->readByte(0x4000));
        $this->assertSame(0xFF, $cartridge->readByte(0x7FFF));
    }

    #[Test]
    public function it_writes_to_external_ram(): void
    {
        $rom = array_fill(0, 32768, 0x00);
        $cartridge = new Cartridge($rom);

        // Write to external RAM
        $cartridge->writeByte(0xA000, 0xAA);
        $cartridge->writeByte(0xA100, 0xBB);
        $cartridge->writeByte(0xBFFF, 0xCC);

        // Read back
        $this->assertSame(0xAA, $cartridge->readByte(0xA000));
        $this->assertSame(0xBB, $cartridge->readByte(0xA100));
        $this->assertSame(0xCC, $cartridge->readByte(0xBFFF));
    }

    #[Test]
    public function it_ignores_writes_to_rom_area(): void
    {
        $rom = array_fill(0, 32768, 0x00);
        $rom[0x0100] = 0x42;

        $cartridge = new Cartridge($rom);

        // Try to write to ROM (should be ignored for ROM-only cartridge)
        $cartridge->writeByte(0x0100, 0x99);

        // Should still read original value
        $this->assertSame(0x42, $cartridge->readByte(0x0100));
    }

    #[Test]
    public function it_returns_0xff_for_invalid_addresses(): void
    {
        $rom = array_fill(0, 32768, 0x00);
        $cartridge = new Cartridge($rom);

        // Addresses outside cartridge range
        $this->assertSame(0xFF, $cartridge->readByte(0x8000));
        $this->assertSame(0xFF, $cartridge->readByte(0x9FFF));
        $this->assertSame(0xFF, $cartridge->readByte(0xC000));
    }

    #[Test]
    public function it_can_be_created_from_rom_data(): void
    {
        $rom = array_fill(0, 32768, 0x00);
        $rom[0x0134] = 0x54; // Title area

        $cartridge = Cartridge::fromRom($rom);

        $this->assertSame(0x54, $cartridge->readByte(0x0134));
    }

    #[Test]
    public function it_masks_values_to_8_bits(): void
    {
        $rom = array_fill(0, 32768, 0x00);
        $cartridge = new Cartridge($rom);

        // Write value > 0xFF to external RAM
        $cartridge->writeByte(0xA000, 0x1FF);

        // Should be masked to 8 bits
        $this->assertSame(0xFF, $cartridge->readByte(0xA000));
    }
}
