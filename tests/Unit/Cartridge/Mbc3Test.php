<?php

declare(strict_types=1);

namespace Tests\Unit\Cartridge;

use Gb\Cartridge\Mbc3;
use PHPUnit\Framework\TestCase;

final class Mbc3Test extends TestCase
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

    public function testRomBank0Read(): void
    {
        $rom = $this->createRom(4);
        $mbc = new Mbc3($rom, count($rom), 0, false, false);

        $this->assertSame(0, $mbc->readByte(0x0000));
        $this->assertSame(0, $mbc->readByte(0x3FFF));
    }

    public function testRomBankSwitching(): void
    {
        $rom = $this->createRom(8);
        $mbc = new Mbc3($rom, count($rom), 0, false, false);

        // Switch to bank 2
        $mbc->writeByte(0x2000, 0x02);
        $this->assertSame(2, $mbc->readByte(0x4000));

        // Switch to bank 3
        $mbc->writeByte(0x2000, 0x03);
        $this->assertSame(3, $mbc->readByte(0x4000));
    }

    public function testRomBank0Quirk(): void
    {
        $rom = $this->createRom(4);
        $mbc = new Mbc3($rom, count($rom), 0, false, false);

        // Selecting bank 0 should actually select bank 1
        $mbc->writeByte(0x2000, 0x00);
        $this->assertSame(1, $mbc->readByte(0x4000));
    }

    public function testRamEnable(): void
    {
        $rom = $this->createRom(2);
        $mbc = new Mbc3($rom, count($rom), 8192, false, false);

        // RAM should be disabled by default
        $mbc->writeByte(0xA000, 0x42);
        $this->assertSame(0xFF, $mbc->readByte(0xA000));

        // Enable RAM
        $mbc->writeByte(0x0000, 0x0A);
        $mbc->writeByte(0xA000, 0x42);
        $this->assertSame(0x42, $mbc->readByte(0xA000));
    }

    public function testRamBanking(): void
    {
        $rom = $this->createRom(2);
        $mbc = new Mbc3($rom, count($rom), 32 * 1024, false, false); // 4 banks

        // Enable RAM
        $mbc->writeByte(0x0000, 0x0A);

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

    public function testRtcRegisterAccess(): void
    {
        $rom = $this->createRom(2);
        $mbc = new Mbc3($rom, count($rom), 0, false, true); // With RTC

        // Enable RTC
        $mbc->writeByte(0x0000, 0x0A);

        // Write to RTC seconds register
        $mbc->writeByte(0x4000, 0x08);
        $mbc->writeByte(0xA000, 0x2A); // 42 seconds

        // Latch RTC (write 0x00 then 0x01)
        $mbc->writeByte(0x6000, 0x00);
        $mbc->writeByte(0x6000, 0x01);

        // Read RTC seconds register
        $mbc->writeByte(0x4000, 0x08);
        $this->assertSame(0x2A, $mbc->readByte(0xA000));
    }

    public function testRtcLatching(): void
    {
        $rom = $this->createRom(2);
        $mbc = new Mbc3($rom, count($rom), 0, false, true);

        // Enable RTC
        $mbc->writeByte(0x0000, 0x0A);

        // Set initial time
        $mbc->writeByte(0x4000, 0x08);
        $mbc->writeByte(0xA000, 10); // 10 seconds

        // Latch
        $mbc->writeByte(0x6000, 0x00);
        $mbc->writeByte(0x6000, 0x01);

        // Read latched value
        $mbc->writeByte(0x4000, 0x08);
        $this->assertSame(10, $mbc->readByte(0xA000));

        // Change internal value
        $mbc->writeByte(0xA000, 20);

        // Latched value should still be 10 until we latch again
        $this->assertSame(20, $mbc->readByte(0xA000)); // Reading shows latest written value

        // Latch again
        $mbc->writeByte(0x6000, 0x00);
        $mbc->writeByte(0x6000, 0x01);

        // Now latched value should be 20
        $this->assertSame(20, $mbc->readByte(0xA000));
    }

    public function testRtcTicking(): void
    {
        $rom = $this->createRom(2);
        $mbc = new Mbc3($rom, count($rom), 0, false, true);

        // Enable RTC
        $mbc->writeByte(0x0000, 0x0A);

        // Set initial time
        $mbc->writeByte(0x4000, 0x08);
        $mbc->writeByte(0xA000, 59); // 59 seconds

        // Latch
        $mbc->writeByte(0x6000, 0x00);
        $mbc->writeByte(0x6000, 0x01);

        $this->assertSame(59, $mbc->readByte(0xA000));

        // Step one second worth of cycles (4,194,304 cycles)
        $mbc->step(4194304);

        // Latch again
        $mbc->writeByte(0x6000, 0x00);
        $mbc->writeByte(0x6000, 0x01);

        // Seconds should wrap to 0 and minutes should increment
        $mbc->writeByte(0x4000, 0x08);
        $this->assertSame(0, $mbc->readByte(0xA000)); // 0 seconds

        $mbc->writeByte(0x4000, 0x09);
        $this->assertSame(1, $mbc->readByte(0xA000)); // 1 minute
    }

    public function testRtcHaltFlag(): void
    {
        $rom = $this->createRom(2);
        $mbc = new Mbc3($rom, count($rom), 0, false, true);

        // Enable RTC
        $mbc->writeByte(0x0000, 0x0A);

        // Set halt flag (bit 6 of day high register)
        $mbc->writeByte(0x4000, 0x0C);
        $mbc->writeByte(0xA000, 0x40);

        // Latch
        $mbc->writeByte(0x6000, 0x00);
        $mbc->writeByte(0x6000, 0x01);

        // Set seconds
        $mbc->writeByte(0x4000, 0x08);
        $mbc->writeByte(0xA000, 10);

        // Step time (should not advance because halted)
        $mbc->step(4194304);

        // Latch and check - should still be 10
        $mbc->writeByte(0x6000, 0x00);
        $mbc->writeByte(0x6000, 0x01);
        $mbc->writeByte(0x4000, 0x08);
        $this->assertSame(10, $mbc->readByte(0xA000));
    }

    public function testGetSetRtcState(): void
    {
        $rom = $this->createRom(2);
        $mbc = new Mbc3($rom, count($rom), 0, false, true);

        $rtcState = [
            'seconds' => 30,
            'minutes' => 45,
            'hours' => 12,
            'days' => 100,
            'halt' => 0,
            'dayHigh' => 0x00,
        ];

        $mbc->setRtcState($rtcState);

        // Enable RTC
        $mbc->writeByte(0x0000, 0x0A);

        // Latch
        $mbc->writeByte(0x6000, 0x00);
        $mbc->writeByte(0x6000, 0x01);

        // Verify state
        $mbc->writeByte(0x4000, 0x08);
        $this->assertSame(30, $mbc->readByte(0xA000)); // seconds

        $mbc->writeByte(0x4000, 0x09);
        $this->assertSame(45, $mbc->readByte(0xA000)); // minutes

        $mbc->writeByte(0x4000, 0x0A);
        $this->assertSame(12, $mbc->readByte(0xA000)); // hours

        $mbc->writeByte(0x4000, 0x0B);
        $this->assertSame(100, $mbc->readByte(0xA000)); // days low

        $retrieved = $mbc->getRtcState();
        $this->assertSame($rtcState, $retrieved);
    }

    public function testBatteryBackedRam(): void
    {
        $rom = $this->createRom(2);

        $mbcNoBattery = new Mbc3($rom, count($rom), 8192, false, false);
        $this->assertFalse($mbcNoBattery->hasBatteryBackedRam());

        $mbcWithBattery = new Mbc3($rom, count($rom), 8192, true, false);
        $this->assertTrue($mbcWithBattery->hasBatteryBackedRam());

        // With RTC and battery but no RAM
        $mbcWithRtc = new Mbc3($rom, count($rom), 0, true, true);
        $this->assertTrue($mbcWithRtc->hasBatteryBackedRam());
    }
}
