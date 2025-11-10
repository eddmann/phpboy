<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use Gb\Memory\Vram;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VramBankTest extends TestCase
{
    private Vram $vram;

    protected function setUp(): void
    {
        $this->vram = new Vram();
    }

    #[Test]
    public function it_defaults_to_bank_zero(): void
    {
        $this->assertSame(0, $this->vram->getBank());
    }

    #[Test]
    public function it_sets_bank_to_one(): void
    {
        $this->vram->setBank(1);
        $this->assertSame(1, $this->vram->getBank());
    }

    #[Test]
    public function it_masks_bank_to_one_bit(): void
    {
        // Only bit 0 should be used
        $this->vram->setBank(0xFF);
        $this->assertSame(1, $this->vram->getBank());

        $this->vram->setBank(0xFE);
        $this->assertSame(0, $this->vram->getBank());
    }

    #[Test]
    public function it_writes_and_reads_from_bank_0(): void
    {
        $this->vram->setBank(0);
        $this->vram->writeByte(0x0100, 0xAB);

        $value = $this->vram->readByte(0x0100);
        $this->assertSame(0xAB, $value);
    }

    #[Test]
    public function it_writes_and_reads_from_bank_1(): void
    {
        $this->vram->setBank(1);
        $this->vram->writeByte(0x0100, 0xCD);

        $value = $this->vram->readByte(0x0100);
        $this->assertSame(0xCD, $value);
    }

    #[Test]
    public function it_keeps_banks_independent(): void
    {
        // Write to bank 0
        $this->vram->setBank(0);
        $this->vram->writeByte(0x0200, 0x11);

        // Write to bank 1
        $this->vram->setBank(1);
        $this->vram->writeByte(0x0200, 0x22);

        // Read from bank 0
        $this->vram->setBank(0);
        $this->assertSame(0x11, $this->vram->readByte(0x0200));

        // Read from bank 1
        $this->vram->setBank(1);
        $this->assertSame(0x22, $this->vram->readByte(0x0200));
    }

    #[Test]
    public function it_returns_correct_bank_data(): void
    {
        // Write to both banks
        $this->vram->setBank(0);
        $this->vram->writeByte(0x0300, 0xAA);

        $this->vram->setBank(1);
        $this->vram->writeByte(0x0300, 0xBB);

        // Get data from each bank
        $bank0Data = $this->vram->getData(0);
        $bank1Data = $this->vram->getData(1);

        $this->assertSame(0xAA, $bank0Data[0x0300]);
        $this->assertSame(0xBB, $bank1Data[0x0300]);
    }

    #[Test]
    public function it_masks_addresses(): void
    {
        // VRAM is 8KB, so addresses should wrap
        $this->vram->setBank(0);
        $this->vram->writeByte(0x0000, 0x12);

        // Address 0x2000 should map to 0x0000 (8KB = 0x2000)
        $this->vram->writeByte(0x2000, 0x34);

        // Both should have written to the same location
        $this->assertSame(0x34, $this->vram->readByte(0x0000));
    }
}
