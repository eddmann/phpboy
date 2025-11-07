<?php

declare(strict_types=1);

namespace Tests\Unit\Bus;

use Gb\Bus\MemoryView;
use Gb\Bus\SystemBus;
use Gb\Memory\Vram;
use Gb\Memory\Wram;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MemoryViewTest extends TestCase
{
    private SystemBus $bus;

    protected function setUp(): void
    {
        $this->bus = new SystemBus();
        $this->bus->attachDevice('vram', new Vram(), 0x8000, 0x9FFF);
        $this->bus->attachDevice('wram', new Wram(), 0xC000, 0xDFFF);
    }

    #[Test]
    public function it_provides_offset_based_access_to_vram(): void
    {
        // Create a view for VRAM starting at 0x8000
        $vramView = new MemoryView($this->bus, 0x8000);

        // Write using absolute address
        $this->bus->writeByte(0x8000, 0xAA);
        $this->bus->writeByte(0x8100, 0xBB);

        // Read using offset (0 = 0x8000, 0x100 = 0x8100)
        $this->assertSame(0xAA, $vramView->readByte(0x0000));
        $this->assertSame(0xBB, $vramView->readByte(0x0100));
    }

    #[Test]
    public function it_allows_writing_through_memory_view(): void
    {
        $vramView = new MemoryView($this->bus, 0x8000);

        // Write using offset
        $vramView->writeByte(0x0000, 0xCC);
        $vramView->writeByte(0x0200, 0xDD);

        // Read using absolute address
        $this->assertSame(0xCC, $this->bus->readByte(0x8000));
        $this->assertSame(0xDD, $this->bus->readByte(0x8200));
    }

    #[Test]
    public function it_supports_16bit_word_reads(): void
    {
        $wramView = new MemoryView($this->bus, 0xC000);

        // Write two bytes
        $this->bus->writeByte(0xC000, 0x34); // Low byte
        $this->bus->writeByte(0xC001, 0x12); // High byte

        // Read as 16-bit word (little-endian)
        $word = $wramView->readWord(0x0000);
        $this->assertSame(0x1234, $word);
    }

    #[Test]
    public function it_supports_16bit_word_writes(): void
    {
        $wramView = new MemoryView($this->bus, 0xC000);

        // Write 16-bit word (little-endian)
        $wramView->writeWord(0x0000, 0xABCD);

        // Read back individual bytes
        $this->assertSame(0xCD, $this->bus->readByte(0xC000)); // Low byte
        $this->assertSame(0xAB, $this->bus->readByte(0xC001)); // High byte
    }

    #[Test]
    public function it_handles_different_base_addresses(): void
    {
        // Create views for different regions
        $vramView = new MemoryView($this->bus, 0x8000);
        $wramView = new MemoryView($this->bus, 0xC000);

        // Write to both views
        $vramView->writeByte(0x0000, 0x11);
        $wramView->writeByte(0x0000, 0x22);

        // Verify they write to different regions
        $this->assertSame(0x11, $this->bus->readByte(0x8000));
        $this->assertSame(0x22, $this->bus->readByte(0xC000));

        // Verify views read correctly
        $this->assertSame(0x11, $vramView->readByte(0x0000));
        $this->assertSame(0x22, $wramView->readByte(0x0000));
    }

    #[Test]
    public function it_properly_masks_base_address_to_16_bits(): void
    {
        // Create view with address > 0xFFFF (should be masked)
        $view = new MemoryView($this->bus, 0x1C000);

        // Should wrap to 0xC000
        $view->writeByte(0x0000, 0x42);
        $this->assertSame(0x42, $this->bus->readByte(0xC000));
    }
}
