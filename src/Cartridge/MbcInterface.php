<?php

declare(strict_types=1);

namespace Gb\Cartridge;

/**
 * Memory Bank Controller Interface
 *
 * Defines the interface for all MBC implementations.
 * MBCs handle ROM/RAM banking and additional features like RTC, rumble, etc.
 */
interface MbcInterface
{
    /**
     * Read a byte from ROM or RAM.
     *
     * @param int $address Address to read from
     * @return int Byte value (0x00-0xFF)
     */
    public function readByte(int $address): int;

    /**
     * Write a byte to ROM (for banking control) or RAM.
     *
     * @param int $address Address to write to
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $address, int $value): void;

    /**
     * Get the external RAM data for saving.
     *
     * @return array<int, int> RAM data
     */
    public function getRam(): array;

    /**
     * Set the external RAM data (for loading saves).
     *
     * @param array<int, int> $ram RAM data
     */
    public function setRam(array $ram): void;

    /**
     * Check if this cartridge has battery-backed RAM that should be saved.
     *
     * @return bool True if RAM should be persisted
     */
    public function hasBatteryBackedRam(): bool;

    /**
     * Step the MBC (for RTC, etc.).
     *
     * @param int $cycles Number of cycles elapsed
     */
    public function step(int $cycles): void;
}
