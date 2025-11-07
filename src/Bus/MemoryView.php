<?php

declare(strict_types=1);

namespace Gb\Bus;

/**
 * Memory View
 *
 * Provides an offset-based view into a region of the bus memory.
 * Useful for components like the PPU to access specific memory regions (e.g., VRAM)
 * without needing to know the absolute bus addresses.
 *
 * Example: A MemoryView for VRAM with base=0x8000 allows reading tile data
 * at offset 0x0000-0x1FFF instead of absolute addresses 0x8000-0x9FFF.
 */
final class MemoryView
{
    private BusInterface $bus;
    private int $baseAddress;

    /**
     * @param BusInterface $bus The underlying bus
     * @param int $baseAddress Base address for this view (e.g., 0x8000 for VRAM)
     */
    public function __construct(BusInterface $bus, int $baseAddress)
    {
        $this->bus = $bus;
        $this->baseAddress = $baseAddress & 0xFFFF;
    }

    /**
     * Read a byte from the view at the specified offset.
     *
     * @param int $offset Offset from base address
     * @return int Byte value (0x00-0xFF)
     */
    public function readByte(int $offset): int
    {
        return $this->bus->readByte($this->baseAddress + $offset);
    }

    /**
     * Write a byte to the view at the specified offset.
     *
     * @param int $offset Offset from base address
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $offset, int $value): void
    {
        $this->bus->writeByte($this->baseAddress + $offset, $value);
    }

    /**
     * Read a 16-bit word (little-endian) from the view.
     *
     * @param int $offset Offset from base address
     * @return int Word value (0x0000-0xFFFF)
     */
    public function readWord(int $offset): int
    {
        $low = $this->readByte($offset);
        $high = $this->readByte($offset + 1);
        return ($high << 8) | $low;
    }

    /**
     * Write a 16-bit word (little-endian) to the view.
     *
     * @param int $offset Offset from base address
     * @param int $value Word value to write (0x0000-0xFFFF)
     */
    public function writeWord(int $offset, int $value): void
    {
        $this->writeByte($offset, $value & 0xFF);
        $this->writeByte($offset + 1, ($value >> 8) & 0xFF);
    }
}
