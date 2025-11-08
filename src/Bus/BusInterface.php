<?php

declare(strict_types=1);

namespace Gb\Bus;

/**
 * Memory Bus Interface
 *
 * Defines the contract for memory access in the Game Boy system.
 * The bus is responsible for routing read/write operations to the
 * appropriate hardware components (ROM, RAM, I/O registers, etc.).
 */
interface BusInterface
{
    /**
     * Read a byte from memory at the specified address.
     *
     * @param int $address Memory address (0x0000-0xFFFF)
     * @return int Byte value (0x00-0xFF)
     */
    public function readByte(int $address): int;

    /**
     * Write a byte to memory at the specified address.
     *
     * @param int $address Memory address (0x0000-0xFFFF)
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $address, int $value): void;

    /**
     * Tick timing-sensitive components at M-cycle granularity.
     *
     * Called by CPU during memory operations for M-cycle accurate timing.
     *
     * @param int $cycles Number of T-cycles (typically 4 for 1 M-cycle)
     */
    public function tickComponents(int $cycles): void;
}
