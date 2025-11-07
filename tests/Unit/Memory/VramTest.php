<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use Gb\Memory\Vram;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VramTest extends TestCase
{
    #[Test]
    public function it_initializes_with_zeros(): void
    {
        $vram = new Vram();

        $this->assertSame(0x00, $vram->readByte(0x0000));
        $this->assertSame(0x00, $vram->readByte(0x1000));
        $this->assertSame(0x00, $vram->readByte(0x1FFF));
    }

    #[Test]
    public function it_reads_and_writes_bytes(): void
    {
        $vram = new Vram();

        $vram->writeByte(0x0000, 0x11);
        $vram->writeByte(0x0800, 0x22);
        $vram->writeByte(0x1FFF, 0x33);

        $this->assertSame(0x11, $vram->readByte(0x0000));
        $this->assertSame(0x22, $vram->readByte(0x0800));
        $this->assertSame(0x33, $vram->readByte(0x1FFF));
    }

    #[Test]
    public function it_masks_addresses_to_8kb(): void
    {
        $vram = new Vram();

        $vram->writeByte(0x0000, 0x99);

        // Address wraps at 8KB boundary
        $this->assertSame(0x99, $vram->readByte(0x2000));
    }

    #[Test]
    public function it_masks_values_to_8_bits(): void
    {
        $vram = new Vram();

        $vram->writeByte(0x0000, 0x1AB);
        $this->assertSame(0xAB, $vram->readByte(0x0000));
    }

    #[Test]
    public function it_provides_direct_data_access(): void
    {
        $vram = new Vram();

        $vram->writeByte(0x0000, 0x44);
        $vram->writeByte(0x0001, 0x55);

        $data = $vram->getData();

        $this->assertSame(0x44, $data[0]);
        $this->assertSame(0x55, $data[1]);
    }
}
