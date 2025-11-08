<?php

declare(strict_types=1);

namespace Tests\Unit\Dma;

use Gb\Bus\MockBus;
use Gb\Dma\OamDma;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OamDmaTest extends TestCase
{
    private MockBus $bus;
    private OamDma $dma;

    protected function setUp(): void
    {
        $this->bus = new MockBus();
        $this->dma = new OamDma($this->bus);
    }

    #[Test]
    public function it_initializes_with_dma_register_zero(): void
    {
        $this->assertSame(0x00, $this->dma->readByte(0xFF46));
    }

    #[Test]
    public function it_reads_and_writes_dma_register(): void
    {
        $this->dma->writeByte(0xFF46, 0xC1);
        $this->assertSame(0xC1, $this->dma->readByte(0xFF46));
    }

    #[Test]
    public function it_starts_dma_transfer_on_write(): void
    {
        $this->assertFalse($this->dma->isDmaActive());

        $this->dma->writeByte(0xFF46, 0xC1);

        $this->assertTrue($this->dma->isDmaActive());
    }

    #[Test]
    public function it_transfers_160_bytes_from_source_to_oam(): void
    {
        // Populate source memory at 0xC100-0xC19F
        for ($i = 0; $i < 160; $i++) {
            $this->bus->writeByte(0xC100 + $i, $i);
        }

        // Start DMA transfer from 0xC100
        $this->dma->writeByte(0xFF46, 0xC1);

        // Transfer completes after 161 M-cycles (1 delay + 160 transfer) = 644 T-cycles
        $this->dma->tick(644);

        // Verify all 160 bytes were copied to OAM (0xFE00-0xFE9F)
        for ($i = 0; $i < 160; $i++) {
            $this->assertSame($i, $this->bus->readByte(0xFE00 + $i));
        }

        $this->assertFalse($this->dma->isDmaActive());
    }

    #[Test]
    public function it_handles_partial_transfer(): void
    {
        // Populate source memory
        for ($i = 0; $i < 160; $i++) {
            $this->bus->writeByte(0xD000 + $i, 0xAA + $i);
        }

        // Start DMA transfer
        $this->dma->writeByte(0xFF46, 0xD0);

        // Transfer 50 bytes (1 delay + 50 transfer = 51 M-cycles = 204 T-cycles)
        $this->dma->tick(204);

        $this->assertTrue($this->dma->isDmaActive());

        // First 50 bytes should be transferred
        for ($i = 0; $i < 50; $i++) {
            $this->assertSame(0xAA + $i, $this->bus->readByte(0xFE00 + $i));
        }

        // Remaining bytes should still be 0x00
        for ($i = 50; $i < 160; $i++) {
            $this->assertSame(0x00, $this->bus->readByte(0xFE00 + $i));
        }
    }

    #[Test]
    public function it_completes_transfer_after_160_cycles(): void
    {
        // Populate source memory
        for ($i = 0; $i < 160; $i++) {
            $this->bus->writeByte(0x8000 + $i, $i * 2);
        }

        // Start DMA transfer
        $this->dma->writeByte(0xFF46, 0x80);

        $this->assertTrue($this->dma->isDmaActive());

        // Transfer in chunks (100 M-cycles = 400 T-cycles)
        $this->dma->tick(400);
        $this->assertTrue($this->dma->isDmaActive());

        // Complete transfer (61 more M-cycles = 244 T-cycles)
        $this->dma->tick(244);
        $this->assertFalse($this->dma->isDmaActive());

        // Verify all bytes transferred
        for ($i = 0; $i < 160; $i++) {
            $this->assertSame(($i * 2) & 0xFF, $this->bus->readByte(0xFE00 + $i));
        }
    }

    #[Test]
    public function it_handles_multiple_transfers(): void
    {
        // First transfer
        for ($i = 0; $i < 160; $i++) {
            $this->bus->writeByte(0xC000 + $i, $i);
        }
        $this->dma->writeByte(0xFF46, 0xC0);
        $this->dma->tick(644); // 161 M-cycles = 644 T-cycles

        // Verify first transfer
        $this->assertSame(0x00, $this->bus->readByte(0xFE00));
        $this->assertSame(0x9F, $this->bus->readByte(0xFE9F));

        // Second transfer (overwrite OAM)
        for ($i = 0; $i < 160; $i++) {
            $this->bus->writeByte(0xD000 + $i, 0xFF - $i);
        }
        $this->dma->writeByte(0xFF46, 0xD0);
        $this->dma->tick(644); // 161 M-cycles = 644 T-cycles

        // Verify second transfer overwrote OAM
        $this->assertSame(0xFF, $this->bus->readByte(0xFE00));
        $this->assertSame(0x60, $this->bus->readByte(0xFE9F));
    }

    #[Test]
    public function it_uses_source_address_low_byte_as_zero(): void
    {
        // Source should always be XX00-XX9F
        for ($i = 0; $i < 160; $i++) {
            $this->bus->writeByte(0xC100 + $i, 0x42);
        }

        // Write 0xC1 to DMA register (should use 0xC100 as source, not 0xC146)
        $this->dma->writeByte(0xFF46, 0xC1);
        $this->dma->tick(644); // 161 M-cycles = 644 T-cycles

        // All OAM bytes should be 0x42
        for ($i = 0; $i < 160; $i++) {
            $this->assertSame(0x42, $this->bus->readByte(0xFE00 + $i));
        }
    }
}
