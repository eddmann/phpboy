<?php

declare(strict_types=1);

namespace Tests\Unit\Cartridge;

use Gb\Cartridge\Mbc5;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Mbc5Test extends TestCase
{
    /**
     * @return array<int, int>
     */
    private function createRom(int $banks = 4): array
    {
        $rom = [];
        for ($bank = 0; $bank < $banks; $bank++) {
            for ($i = 0; $i < 0x4000; $i++) {
                $rom[] = $bank;
            }
        }
        return $rom;
    }

    #[Test]
    public function it_reads_rom_bank_0(): void
    {
        $rom = $this->createRom(4);
        $mbc = new Mbc5($rom, count($rom), 0, false, false);

        $this->assertSame(0, $mbc->readByte(0x0000));
        $this->assertSame(0, $mbc->readByte(0x3FFF));
    }

    #[Test]
    public function it_switches_rom_banks(): void
    {
        $rom = $this->createRom(8);
        $mbc = new Mbc5($rom, count($rom), 0, false, false);

        // Default bank 1
        $this->assertSame(1, $mbc->readByte(0x4000));

        // Switch to bank 2
        $mbc->writeByte(0x2000, 0x02);
        $this->assertSame(2, $mbc->readByte(0x4000));

        // Switch to bank 3
        $mbc->writeByte(0x2000, 0x03);
        $this->assertSame(3, $mbc->readByte(0x4000));
    }

    #[Test]
    public function it_supports_9_bit_bank_numbers(): void
    {
        // Use 300 banks to test 9-bit addressing without excessive memory usage
        $rom = $this->createRom(300);
        $mbc = new Mbc5($rom, count($rom), 0, false, false);

        // Set lower 8 bits to 0xFF (bank 255)
        $mbc->writeByte(0x2000, 0xFF);
        $this->assertSame(255, $mbc->readByte(0x4000));

        // Set 9th bit to 1 (bank 0x100 = 256)
        $mbc->writeByte(0x2000, 0x00);
        $mbc->writeByte(0x3000, 0x01);
        $this->assertSame(256, $mbc->readByte(0x4000));

        // Test bank 299 (close to limit)
        $mbc->writeByte(0x2000, 0x2B); // 299 = 0x12B, lower 8 bits = 0x2B
        $mbc->writeByte(0x3000, 0x01); // upper bit = 1
        $this->assertSame(299, $mbc->readByte(0x4000));

        // Clear 9th bit (back to bank 0x2B = 43)
        $mbc->writeByte(0x3000, 0x00);
        $this->assertSame(43, $mbc->readByte(0x4000));
    }

    #[Test]
    public function it_allows_bank_0_selection(): void
    {
        $rom = $this->createRom(4);
        $mbc = new Mbc5($rom, count($rom), 0, false, false);

        // MBC5 can select bank 0 (unlike MBC1)
        $mbc->writeByte(0x2000, 0x00);
        $mbc->writeByte(0x3000, 0x00);
        $this->assertSame(0, $mbc->readByte(0x4000));
    }

    #[Test]
    public function it_enables_ram(): void
    {
        $rom = $this->createRom(2);
        $mbc = new Mbc5($rom, count($rom), 8192, false, false);

        // RAM should be disabled by default
        $mbc->writeByte(0xA000, 0x42);
        $this->assertSame(0xFF, $mbc->readByte(0xA000));

        // Enable RAM
        $mbc->writeByte(0x0000, 0x0A);
        $mbc->writeByte(0xA000, 0x42);
        $this->assertSame(0x42, $mbc->readByte(0xA000));
    }

    #[Test]
    public function it_switches_ram_banks(): void
    {
        $rom = $this->createRom(2);
        $mbc = new Mbc5($rom, count($rom), 128 * 1024, false, false); // 16 banks

        // Enable RAM
        $mbc->writeByte(0x0000, 0x0A);

        // Write to different banks
        for ($bank = 0; $bank < 16; $bank++) {
            $mbc->writeByte(0x4000, $bank);
            $mbc->writeByte(0xA000, $bank + 0x10);
        }

        // Read from different banks
        for ($bank = 0; $bank < 16; $bank++) {
            $mbc->writeByte(0x4000, $bank);
            $this->assertSame($bank + 0x10, $mbc->readByte(0xA000));
        }
    }

    #[Test]
    public function it_uses_4_bit_ram_bank_register(): void
    {
        $rom = $this->createRom(2);
        $mbc = new Mbc5($rom, count($rom), 128 * 1024, false, false);

        // Enable RAM
        $mbc->writeByte(0x0000, 0x0A);

        // Write bank number 0x0F (15)
        $mbc->writeByte(0x4000, 0x0F);
        $mbc->writeByte(0xA000, 0xFF);

        // Read back
        $mbc->writeByte(0x4000, 0x0F);
        $this->assertSame(0xFF, $mbc->readByte(0xA000));

        // Writing 0x1F should be masked to 0x0F
        $mbc->writeByte(0x4000, 0x1F);
        $this->assertSame(0xFF, $mbc->readByte(0xA000)); // Should read same as 0x0F
    }

    #[Test]
    public function it_gets_and_sets_ram(): void
    {
        $rom = $this->createRom(2);
        $mbc = new Mbc5($rom, count($rom), 8192, false, false);

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

        $mbcNoBattery = new Mbc5($rom, count($rom), 8192, false, false);
        $this->assertFalse($mbcNoBattery->hasBatteryBackedRam());

        $mbcWithBattery = new Mbc5($rom, count($rom), 8192, true, false);
        $this->assertTrue($mbcWithBattery->hasBatteryBackedRam());

        $mbcWithBatteryNoRam = new Mbc5($rom, count($rom), 0, true, false);
        $this->assertFalse($mbcWithBatteryNoRam->hasBatteryBackedRam());
    }

    #[Test]
    public function it_handles_rumble_without_affecting_banking(): void
    {
        $rom = $this->createRom(2);
        $mbc = new Mbc5($rom, count($rom), 16 * 1024, false, true); // With rumble, 2 banks

        // Enable RAM
        $mbc->writeByte(0x0000, 0x0A);

        // Write to bank 0 with rumble bit set (bit 3)
        $mbc->writeByte(0x4000, 0x08); // Rumble bit set, bank 0
        $mbc->writeByte(0xA000, 0x11);

        // Read back
        $mbc->writeByte(0x4000, 0x08);
        $this->assertSame(0x11, $mbc->readByte(0xA000));

        // Write to bank 1 with rumble bit clear
        $mbc->writeByte(0x4000, 0x01);
        $mbc->writeByte(0xA000, 0x22);

        // Verify bank 0 is unchanged
        $mbc->writeByte(0x4000, 0x08);
        $this->assertSame(0x11, $mbc->readByte(0xA000));

        // Verify bank 1
        $mbc->writeByte(0x4000, 0x01);
        $this->assertSame(0x22, $mbc->readByte(0xA000));
    }
}
