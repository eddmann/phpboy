<?php

declare(strict_types=1);

namespace Tests\Integration;

use Gb\Bus\SystemBus;
use Gb\Cartridge\Cartridge;
use Gb\Memory\Hram;
use Gb\Memory\Vram;
use Gb\Memory\Wram;
use Gb\Ppu\Oam;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for memory system with CPU
 *
 * Tests that the CPU can interact correctly with the SystemBus
 * and all attached memory devices.
 */
final class MemoryIntegrationTest extends TestCase
{
    private SystemBus $bus;

    protected function setUp(): void
    {
        $this->bus = new SystemBus();

        // Create a simple ROM with test instructions
        $rom = array_fill(0, 32768, 0x00);

        // Set up a simple program at 0x0100 (entry point)
        // LD A, $42 (load 0x42 into A register)
        $rom[0x0100] = 0x3E; // LD A, n
        $rom[0x0101] = 0x42; // value

        // LD (HL), A (write A to address in HL)
        $rom[0x0102] = 0x77;

        // LD A, (HL) (read from address in HL to A)
        $rom[0x0103] = 0x7E;

        // Attach all devices
        $this->bus->attachDevice('cartridge', new Cartridge($rom), 0x0000, 0x7FFF);
        $this->bus->attachDevice('vram', new Vram(), 0x8000, 0x9FFF);
        $this->bus->attachDevice('wram', new Wram(), 0xC000, 0xDFFF);
        $this->bus->attachDevice('oam', new Oam(), 0xFE00, 0xFE9F);
        $this->bus->attachDevice('hram', new Hram(), 0xFF80, 0xFFFE);
    }

    #[Test]
    public function cpu_can_read_from_rom(): void
    {
        // CPU should be able to read the instruction at 0x0100
        $instruction = $this->bus->readByte(0x0100);
        $this->assertSame(0x3E, $instruction);

        $value = $this->bus->readByte(0x0101);
        $this->assertSame(0x42, $value);
    }

    #[Test]
    public function cpu_can_write_to_wram_and_read_back(): void
    {
        // Write a value to WRAM
        $this->bus->writeByte(0xC000, 0xAA);
        $this->bus->writeByte(0xC100, 0xBB);
        $this->bus->writeByte(0xDFFF, 0xCC);

        // Read back from WRAM
        $this->assertSame(0xAA, $this->bus->readByte(0xC000));
        $this->assertSame(0xBB, $this->bus->readByte(0xC100));
        $this->assertSame(0xCC, $this->bus->readByte(0xDFFF));
    }

    #[Test]
    public function cpu_can_write_to_vram_and_read_back(): void
    {
        // Write tile data to VRAM
        $this->bus->writeByte(0x8000, 0x11);
        $this->bus->writeByte(0x8100, 0x22);
        $this->bus->writeByte(0x9FFF, 0x33);

        // Read back from VRAM
        $this->assertSame(0x11, $this->bus->readByte(0x8000));
        $this->assertSame(0x22, $this->bus->readByte(0x8100));
        $this->assertSame(0x33, $this->bus->readByte(0x9FFF));
    }

    #[Test]
    public function cpu_can_access_hram(): void
    {
        // Write to HRAM
        $this->bus->writeByte(0xFF80, 0x99);
        $this->bus->writeByte(0xFFFE, 0x88);

        // Read back from HRAM
        $this->assertSame(0x99, $this->bus->readByte(0xFF80));
        $this->assertSame(0x88, $this->bus->readByte(0xFFFE));
    }

    #[Test]
    public function cpu_can_access_oam(): void
    {
        // Write sprite attributes to OAM
        $this->bus->writeByte(0xFE00, 0x10); // Y position
        $this->bus->writeByte(0xFE01, 0x20); // X position
        $this->bus->writeByte(0xFE02, 0x30); // Tile index
        $this->bus->writeByte(0xFE03, 0x40); // Flags

        // Read back from OAM
        $this->assertSame(0x10, $this->bus->readByte(0xFE00));
        $this->assertSame(0x20, $this->bus->readByte(0xFE01));
        $this->assertSame(0x30, $this->bus->readByte(0xFE02));
        $this->assertSame(0x40, $this->bus->readByte(0xFE03));
    }

    #[Test]
    public function echo_ram_mirrors_wram(): void
    {
        // Write to WRAM
        $this->bus->writeByte(0xC000, 0xAA);
        $this->bus->writeByte(0xC500, 0xBB);
        $this->bus->writeByte(0xDDFF, 0xCC);

        // Read from Echo RAM (should mirror WRAM)
        $this->assertSame(0xAA, $this->bus->readByte(0xE000));
        $this->assertSame(0xBB, $this->bus->readByte(0xE500));
        $this->assertSame(0xCC, $this->bus->readByte(0xFDFF));

        // Write to Echo RAM
        $this->bus->writeByte(0xE100, 0xDD);

        // Should be reflected in WRAM
        $this->assertSame(0xDD, $this->bus->readByte(0xC100));
    }

    #[Test]
    public function full_memory_map_integration(): void
    {
        // Test reading from all major memory regions
        $romValue = $this->bus->readByte(0x0100); // ROM
        $this->assertSame(0x3E, $romValue);

        // VRAM
        $this->bus->writeByte(0x8000, 0x11);
        $this->assertSame(0x11, $this->bus->readByte(0x8000));

        // External RAM
        $this->bus->writeByte(0xA000, 0x22);
        $this->assertSame(0x22, $this->bus->readByte(0xA000));

        // WRAM
        $this->bus->writeByte(0xC000, 0x33);
        $this->assertSame(0x33, $this->bus->readByte(0xC000));

        // Echo RAM
        $this->assertSame(0x33, $this->bus->readByte(0xE000));

        // OAM
        $this->bus->writeByte(0xFE00, 0x44);
        $this->assertSame(0x44, $this->bus->readByte(0xFE00));

        // Prohibited area (should return 0xFF)
        $this->assertSame(0xFF, $this->bus->readByte(0xFEA0));

        // HRAM
        $this->bus->writeByte(0xFF80, 0x55);
        $this->assertSame(0x55, $this->bus->readByte(0xFF80));

        // IE register
        $this->bus->writeByte(0xFFFF, 0x1F);
        $this->assertSame(0x1F, $this->bus->readByte(0xFFFF));
    }
}
