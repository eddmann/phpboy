<?php

declare(strict_types=1);

namespace Tests\Unit\Ppu;

use Gb\Ppu\Oam;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OamTest extends TestCase
{
    #[Test]
    public function it_initializes_with_zeros(): void
    {
        $oam = new Oam();

        $this->assertSame(0x00, $oam->readByte(0x00));
        $this->assertSame(0x00, $oam->readByte(0x50));
        $this->assertSame(0x00, $oam->readByte(0x9F)); // Last byte
    }

    #[Test]
    public function it_reads_and_writes_sprite_attributes(): void
    {
        $oam = new Oam();

        // Write sprite 0 attributes
        $oam->writeByte(0x00, 0x10); // Y position
        $oam->writeByte(0x01, 0x20); // X position
        $oam->writeByte(0x02, 0x30); // Tile index
        $oam->writeByte(0x03, 0x40); // Flags

        $this->assertSame(0x10, $oam->readByte(0x00));
        $this->assertSame(0x20, $oam->readByte(0x01));
        $this->assertSame(0x30, $oam->readByte(0x02));
        $this->assertSame(0x40, $oam->readByte(0x03));
    }

    #[Test]
    public function it_returns_0xff_for_out_of_bounds_reads(): void
    {
        $oam = new Oam();

        // OAM is 160 bytes (0x00-0x9F)
        // Reading beyond should return 0xFF
        $this->assertSame(0xFF, $oam->readByte(0xA0));
        $this->assertSame(0xFF, $oam->readByte(0xFF));
    }

    #[Test]
    public function it_ignores_out_of_bounds_writes(): void
    {
        $oam = new Oam();

        // Try to write beyond OAM bounds (should be ignored)
        $oam->writeByte(0xA0, 0x99);
        $oam->writeByte(0xFF, 0x88);

        // Reading should still return 0xFF (default)
        $this->assertSame(0xFF, $oam->readByte(0xA0));
        $this->assertSame(0xFF, $oam->readByte(0xFF));
    }

    #[Test]
    public function it_masks_values_to_8_bits(): void
    {
        $oam = new Oam();

        $oam->writeByte(0x00, 0x1FF);
        $this->assertSame(0xFF, $oam->readByte(0x00));
    }

    #[Test]
    public function it_provides_direct_data_access(): void
    {
        $oam = new Oam();

        $oam->writeByte(0x00, 0xAA);
        $oam->writeByte(0x01, 0xBB);

        $data = $oam->getData();

        $this->assertSame(0xAA, $data[0]);
        $this->assertSame(0xBB, $data[1]);
        $this->assertCount(160, $data);
    }
}
