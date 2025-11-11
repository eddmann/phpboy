<?php

declare(strict_types=1);

namespace Gb\Memory;

use Gb\Bus\DeviceInterface;

/**
 * Working RAM (WRAM)
 *
 * 8KB of working RAM mapped to 0xC000-0xDFFF.
 * In DMG mode: Single 8KB bank
 * In CGB mode: Bank 0 (0xC000-0xCFFF) + switchable banks 1-7 (0xD000-0xDFFF)
 *
 * For now, implements DMG-style 8KB WRAM. CGB bank switching will be added later.
 */
final class Wram implements DeviceInterface
{
    /** @var array<int, int> Working RAM storage (8KB = 8192 bytes) */
    private array $ram;

    public function __construct()
    {
        // Initialize 8KB of RAM with 0x00
        $this->ram = array_fill(0, 8192, 0x00);
    }

    /**
     * Read a byte from WRAM.
     *
     * @param int $address Address within WRAM (0xC000-0xDFFF, but passed as 0x0000-0x1FFF offset)
     * @return int Byte value (0x00-0xFF)
     */
    public function readByte(int $address): int
    {
        $offset = $address & 0x1FFF; // Mask to 8KB
        return $this->ram[$offset];
    }

    /**
     * Write a byte to WRAM.
     *
     * @param int $address Address within WRAM (0xC000-0xDFFF, but passed as 0x0000-0x1FFF offset)
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $address, int $value): void
    {
        $offset = $address & 0x1FFF; // Mask to 8KB
        $this->ram[$offset] = $value & 0xFF;
    }
}
