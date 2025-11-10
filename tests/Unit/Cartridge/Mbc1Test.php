<?php

declare(strict_types=1);

namespace Tests\Unit\Cartridge;

use Gb\Cartridge\Mbc1;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Mbc1Test extends TestCase
{
    /**
     * @return array<int, int>
     */
    private function createRom(int $banks = 4): array
    {
        $rom = [];
        for ($bank = 0; $bank < $banks; $bank++) {
            for ($i = 0; $i < 0x4000; $i++) {
                $rom[] = $bank; // Fill each bank with its bank number for easy testing
            }
        }
        return $rom;
    }

    #[Test]
    public function it_reads_rom_bank_0(): void
    {
        $rom = $this->createRom(4);
        $mbc = new Mbc1($rom, count($rom), 0, false);

        // Bank 0 should be accessible at 0x0000-0x3FFF
        $this->assertSame(0, $mbc->readByte(0x0000));
        $this->assertSame(0, $mbc->readByte(0x3FFF));
    }

    #[Test]
    public function it_reads_rom_bank_n(): void
    {
        $rom = $this->createRom(4);
        $mbc = new Mbc1($rom, count($rom), 0, false);

        // Bank 1 should be accessible at 0x4000-0x7FFF by default
        $this->assertSame(1, $mbc->readByte(0x4000));
        $this->assertSame(1, $mbc->readByte(0x7FFF));
    }

    #[Test]
    public function it_switches_rom_banks(): void
    {
        $rom = $this->createRom(8);
        $mbc = new Mbc1($rom, count($rom), 0, false);

        // Switch to bank 2
        $mbc->writeByte(0x2000, 0x02);
        $this->assertSame(2, $mbc->readByte(0x4000));

        // Switch to bank 3
        $mbc->writeByte(0x2000, 0x03);
        $this->assertSame(3, $mbc->readByte(0x4000));
    }

    #[Test]
    public function it_handles_rom_bank_0_quirk(): void
    {
        $rom = $this->createRom(4);
        $mbc = new Mbc1($rom, count($rom), 0, false);

        // Selecting bank 0 should actually select bank 1
        $mbc->writeByte(0x2000, 0x00);
        $this->assertSame(1, $mbc->readByte(0x4000));
    }

    #[Test]
    public function it_handles_upper_bank_bits(): void
    {
        $rom = $this->createRom(64);
        $mbc = new Mbc1($rom, count($rom), 0, false);

        // Set lower 5 bits to 0x01
        $mbc->writeByte(0x2000, 0x01);

        // Set upper 2 bits to 0x01 (bank 0x21 = 33)
        $mbc->writeByte(0x4000, 0x01);
        $this->assertSame(33, $mbc->readByte(0x4000));

        // Set upper 2 bits to 0x02 (bank 0x41 = 65, wraps to 1 with 64 banks)
        $mbc->writeByte(0x4000, 0x02);
        $this->assertSame(1, $mbc->readByte(0x4000));
    }

    #[Test]
    public function it_enables_and_disables_ram(): void
    {
        $rom = $this->createRom(2);
        $mbc = new Mbc1($rom, count($rom), 8192, false);

        // RAM should be disabled by default
        $mbc->writeByte(0xA000, 0x42);
        $this->assertSame(0xFF, $mbc->readByte(0xA000));

        // Enable RAM
        $mbc->writeByte(0x0000, 0x0A);
        $mbc->writeByte(0xA000, 0x42);
        $this->assertSame(0x42, $mbc->readByte(0xA000));

        // Disable RAM
        $mbc->writeByte(0x0000, 0x00);
        $this->assertSame(0xFF, $mbc->readByte(0xA000));
    }

    #[Test]
    public function it_switches_ram_banks(): void
    {
        $rom = $this->createRom(2);
        $mbc = new Mbc1($rom, count($rom), 32 * 1024, false); // 4 banks of 8KB

        // Enable RAM
        $mbc->writeByte(0x0000, 0x0A);

        // Select RAM mode
        $mbc->writeByte(0x6000, 0x01);

        // Write to bank 0
        $mbc->writeByte(0x4000, 0x00);
        $mbc->writeByte(0xA000, 0x11);

        // Write to bank 1
        $mbc->writeByte(0x4000, 0x01);
        $mbc->writeByte(0xA000, 0x22);

        // Read from bank 0
        $mbc->writeByte(0x4000, 0x00);
        $this->assertSame(0x11, $mbc->readByte(0xA000));

        // Read from bank 1
        $mbc->writeByte(0x4000, 0x01);
        $this->assertSame(0x22, $mbc->readByte(0xA000));
    }

    #[Test]
    public function it_switches_banking_modes(): void
    {
        $rom = $this->createRom(64);
        $mbc = new Mbc1($rom, count($rom), 32 * 1024, false);

        // ROM banking mode (default)
        $mbc->writeByte(0x6000, 0x00);
        $mbc->writeByte(0x4000, 0x01); // Upper bits = 1
        $this->assertSame(0, $mbc->readByte(0x0000)); // Bank 0 at 0x0000

        // RAM banking mode
        $mbc->writeByte(0x6000, 0x01);
        $mbc->writeByte(0x4000, 0x01); // Upper bits = 1
        $this->assertSame(32, $mbc->readByte(0x0000)); // Bank 32 at 0x0000 (upper bits affect bank 0)
    }

    #[Test]
    public function it_gets_and_sets_ram(): void
    {
        $rom = $this->createRom(2);
        $mbc = new Mbc1($rom, count($rom), 8192, false);

        $ram = array_fill(0, 8192, 0x42);
        $mbc->setRam($ram);

        $mbc->writeByte(0x0000, 0x0A); // Enable RAM
        $this->assertSame(0x42, $mbc->readByte(0xA000));

        $retrieved = $mbc->getRam();
        $this->assertSame($ram, $retrieved);
    }

    #[Test]
    public function it_detects_battery_backed_ram(): void
    {
        $rom = $this->createRom(2);

        $mbcNoBattery = new Mbc1($rom, count($rom), 8192, false);
        $this->assertFalse($mbcNoBattery->hasBatteryBackedRam());

        $mbcWithBattery = new Mbc1($rom, count($rom), 8192, true);
        $this->assertTrue($mbcWithBattery->hasBatteryBackedRam());

        $mbcWithBatteryNoRam = new Mbc1($rom, count($rom), 0, true);
        $this->assertFalse($mbcWithBatteryNoRam->hasBatteryBackedRam());
    }
}
