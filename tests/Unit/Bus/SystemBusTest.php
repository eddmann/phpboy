<?php

declare(strict_types=1);

namespace Tests\Unit\Bus;

use Gb\Bus\SystemBus;
use Gb\Cartridge\Cartridge;
use Gb\Memory\Hram;
use Gb\Memory\Vram;
use Gb\Memory\Wram;
use Gb\Ppu\Oam;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SystemBusTest extends TestCase
{
    private SystemBus $bus;

    protected function setUp(): void
    {
        $this->bus = new SystemBus();

        // Attach all standard devices
        $rom = array_fill(0, 32768, 0x00);
        $rom[0x100] = 0xAB; // Set a test value in ROM
        $rom[0x4000] = 0xCD; // Set a test value in ROM bank 1

        $this->bus->attachDevice('cartridge', new Cartridge($rom), 0x0000, 0x7FFF);
        $this->bus->attachDevice('vram', new Vram(), 0x8000, 0x9FFF);
        $this->bus->attachDevice('wram', new Wram(), 0xC000, 0xDFFF);
        $this->bus->attachDevice('oam', new Oam(), 0xFE00, 0xFE9F);
        $this->bus->attachDevice('hram', new Hram(), 0xFF80, 0xFFFE);
    }

    #[Test]
    public function it_routes_rom_reads_to_cartridge(): void
    {
        // ROM Bank 0
        $this->assertSame(0xAB, $this->bus->readByte(0x0100));

        // ROM Bank 1
        $this->assertSame(0xCD, $this->bus->readByte(0x4000));
    }

    #[Test]
    public function it_routes_vram_reads_and_writes(): void
    {
        $this->bus->writeByte(0x8000, 0x12);
        $this->assertSame(0x12, $this->bus->readByte(0x8000));

        $this->bus->writeByte(0x9FFF, 0x34);
        $this->assertSame(0x34, $this->bus->readByte(0x9FFF));
    }

    #[Test]
    public function it_routes_wram_reads_and_writes(): void
    {
        $this->bus->writeByte(0xC000, 0x56);
        $this->assertSame(0x56, $this->bus->readByte(0xC000));

        $this->bus->writeByte(0xDFFF, 0x78);
        $this->assertSame(0x78, $this->bus->readByte(0xDFFF));
    }

    #[Test]
    public function it_mirrors_echo_ram_to_wram(): void
    {
        // Write to WRAM
        $this->bus->writeByte(0xC000, 0xAA);
        $this->bus->writeByte(0xC100, 0xBB);
        $this->bus->writeByte(0xDDFF, 0xCC);

        // Read from Echo RAM (should mirror WRAM)
        $this->assertSame(0xAA, $this->bus->readByte(0xE000)); // 0xE000 -> 0xC000
        $this->assertSame(0xBB, $this->bus->readByte(0xE100)); // 0xE100 -> 0xC100
        $this->assertSame(0xCC, $this->bus->readByte(0xFDFF)); // 0xFDFF -> 0xDDFF

        // Write to Echo RAM (should write to WRAM)
        $this->bus->writeByte(0xE000, 0xDD);
        $this->assertSame(0xDD, $this->bus->readByte(0xC000)); // Should be mirrored
        $this->assertSame(0xDD, $this->bus->readByte(0xE000)); // Should read same value
    }

    #[Test]
    public function it_routes_oam_reads_and_writes(): void
    {
        $this->bus->writeByte(0xFE00, 0x11);
        $this->assertSame(0x11, $this->bus->readByte(0xFE00));

        $this->bus->writeByte(0xFE9F, 0x22);
        $this->assertSame(0x22, $this->bus->readByte(0xFE9F));
    }

    #[Test]
    public function it_returns_open_bus_for_prohibited_area(): void
    {
        // Prohibited area (0xFEA0-0xFEFF) should return 0xFF
        $this->assertSame(0xFF, $this->bus->readByte(0xFEA0));
        $this->assertSame(0xFF, $this->bus->readByte(0xFEAF));
        $this->assertSame(0xFF, $this->bus->readByte(0xFEFF));

        // Writes should be ignored (no crash)
        $this->bus->writeByte(0xFEA0, 0x42);
        $this->assertSame(0xFF, $this->bus->readByte(0xFEA0));
    }

    #[Test]
    public function it_routes_hram_reads_and_writes(): void
    {
        $this->bus->writeByte(0xFF80, 0x99);
        $this->assertSame(0x99, $this->bus->readByte(0xFF80));

        $this->bus->writeByte(0xFFFE, 0x88);
        $this->assertSame(0x88, $this->bus->readByte(0xFFFE));
    }

    #[Test]
    public function it_handles_interrupt_enable_register(): void
    {
        // IE register at 0xFFFF
        $this->bus->writeByte(0xFFFF, 0x1F);
        $this->assertSame(0x1F, $this->bus->readByte(0xFFFF));
    }

    #[Test]
    public function it_routes_external_ram_to_cartridge(): void
    {
        // External RAM (0xA000-0xBFFF)
        $this->bus->writeByte(0xA000, 0x55);
        $this->assertSame(0x55, $this->bus->readByte(0xA000));

        $this->bus->writeByte(0xBFFF, 0x66);
        $this->assertSame(0x66, $this->bus->readByte(0xBFFF));
    }

    #[Test]
    public function it_returns_open_bus_for_unimplemented_io_registers(): void
    {
        // I/O registers not yet implemented should return 0xFF
        $this->assertSame(0xFF, $this->bus->readByte(0xFF00));
        $this->assertSame(0xFF, $this->bus->readByte(0xFF4D));
        $this->assertSame(0xFF, $this->bus->readByte(0xFF7F));
    }

    #[Test]
    public function it_handles_address_wrapping(): void
    {
        // Test that addresses are properly masked to 16 bits
        $this->bus->writeByte(0xC000, 0x42);
        $this->assertSame(0x42, $this->bus->readByte(0xC000));

        // Writing with value > 0xFF should mask to 8 bits
        $this->bus->writeByte(0xC001, 0x1FF);
        $this->assertSame(0xFF, $this->bus->readByte(0xC001));
    }

    #[Test]
    public function it_can_retrieve_attached_devices(): void
    {
        $vram = $this->bus->getDevice('vram');
        $this->assertInstanceOf(Vram::class, $vram);

        $wram = $this->bus->getDevice('wram');
        $this->assertInstanceOf(Wram::class, $wram);

        $nonExistent = $this->bus->getDevice('nonexistent');
        $this->assertNull($nonExistent);
    }
}
