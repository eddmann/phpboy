<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use Gb\Memory\Wram;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WramTest extends TestCase
{
    #[Test]
    public function it_initializes_with_zeros(): void
    {
        $wram = new Wram();

        $this->assertSame(0x00, $wram->readByte(0x0000));
        $this->assertSame(0x00, $wram->readByte(0x1000));
        $this->assertSame(0x00, $wram->readByte(0x1FFF));
    }

    #[Test]
    public function it_reads_and_writes_bytes(): void
    {
        $wram = new Wram();

        $wram->writeByte(0x0000, 0xAA);
        $wram->writeByte(0x1000, 0xBB);
        $wram->writeByte(0x1FFF, 0xCC);

        $this->assertSame(0xAA, $wram->readByte(0x0000));
        $this->assertSame(0xBB, $wram->readByte(0x1000));
        $this->assertSame(0xCC, $wram->readByte(0x1FFF));
    }

    #[Test]
    public function it_masks_addresses_to_8kb(): void
    {
        $wram = new Wram();

        // Write to address 0x0000
        $wram->writeByte(0x0000, 0x42);

        // Address 0x2000 should wrap to 0x0000 (8KB = 0x2000)
        $this->assertSame(0x42, $wram->readByte(0x2000));

        // Address 0x4000 should also wrap to 0x0000
        $this->assertSame(0x42, $wram->readByte(0x4000));
    }

    #[Test]
    public function it_masks_values_to_8_bits(): void
    {
        $wram = new Wram();

        $wram->writeByte(0x0000, 0x1FF);
        $this->assertSame(0xFF, $wram->readByte(0x0000));

        $wram->writeByte(0x0001, 0x300);
        $this->assertSame(0x00, $wram->readByte(0x0001));
    }
}
