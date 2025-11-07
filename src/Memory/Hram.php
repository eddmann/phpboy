<?php

declare(strict_types=1);

namespace Gb\Memory;

use Gb\Bus\DeviceInterface;

/**
 * High RAM (HRAM)
 *
 * 127 bytes of fast RAM mapped to 0xFF80-0xFFFE.
 * Located on the CPU die, providing faster access than WRAM.
 * Often used for interrupt handlers and time-critical code.
 *
 * Note: 0xFFFF (IE register) is separate and not part of HRAM.
 */
final class Hram implements DeviceInterface
{
    /** @var array<int, int> High RAM storage (127 bytes) */
    private array $ram;

    public function __construct()
    {
        // Initialize 127 bytes of HRAM with 0x00
        $this->ram = array_fill(0, 127, 0x00);
    }

    /**
     * Read a byte from HRAM.
     *
     * @param int $address Address within HRAM (0xFF80-0xFFFE, but passed as 0x00-0x7E offset)
     * @return int Byte value (0x00-0xFF)
     */
    public function readByte(int $address): int
    {
        $offset = $address & 0x7F; // Mask to 127 bytes
        return $this->ram[$offset];
    }

    /**
     * Write a byte to HRAM.
     *
     * @param int $address Address within HRAM (0xFF80-0xFFFE, but passed as 0x00-0x7E offset)
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $address, int $value): void
    {
        $offset = $address & 0x7F; // Mask to 127 bytes
        $this->ram[$offset] = $value & 0xFF;
    }
}
