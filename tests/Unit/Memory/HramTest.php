<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use Gb\Memory\Hram;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HramTest extends TestCase
{
    #[Test]
    public function it_initializes_with_zeros(): void
    {
        $hram = new Hram();

        $this->assertSame(0x00, $hram->readByte(0x00));
        $this->assertSame(0x00, $hram->readByte(0x40));
        $this->assertSame(0x00, $hram->readByte(0x7E)); // Last byte
    }

    #[Test]
    public function it_reads_and_writes_bytes(): void
    {
        $hram = new Hram();

        $hram->writeByte(0x00, 0xAA);
        $hram->writeByte(0x40, 0xBB);
        $hram->writeByte(0x7E, 0xCC);

        $this->assertSame(0xAA, $hram->readByte(0x00));
        $this->assertSame(0xBB, $hram->readByte(0x40));
        $this->assertSame(0xCC, $hram->readByte(0x7E));
    }

    #[Test]
    public function it_masks_addresses_to_127_bytes(): void
    {
        $hram = new Hram();

        $hram->writeByte(0x00, 0x77);

        // Address should wrap at 127 bytes (0x7F)
        $this->assertSame(0x77, $hram->readByte(0x80));
    }

    #[Test]
    public function it_masks_values_to_8_bits(): void
    {
        $hram = new Hram();

        $hram->writeByte(0x00, 0x2FF);
        $this->assertSame(0xFF, $hram->readByte(0x00));
    }
}
