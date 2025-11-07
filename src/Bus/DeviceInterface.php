<?php

declare(strict_types=1);

namespace Gb\Bus;

/**
 * Memory-Mapped Device Interface
 *
 * Defines the contract for hardware components that can be attached to the SystemBus.
 * Each device handles reads/writes to its own address range.
 *
 * Examples: Cartridge ROM/RAM, VRAM, WRAM, HRAM, OAM, I/O registers
 */
interface DeviceInterface
{
    /**
     * Read a byte from the device at the specified address.
     *
     * @param int $address Memory address within the device's range
     * @return int Byte value (0x00-0xFF)
     */
    public function readByte(int $address): int;

    /**
     * Write a byte to the device at the specified address.
     *
     * @param int $address Memory address within the device's range
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $address, int $value): void;
}
