<?php

declare(strict_types=1);

namespace Gb\Bus;

/**
 * Mock Memory Bus for Testing
 *
 * Simple memory implementation backed by an array.
 * Used for unit testing CPU and other components without full bus logic.
 */
final class MockBus implements BusInterface
{
    /** @var array<int, int> Memory storage (address => byte value) */
    private array $memory = [];

    /**
     * @param array<int, int> $initialMemory Optional initial memory contents
     */
    public function __construct(array $initialMemory = [])
    {
        $this->memory = $initialMemory;
    }

    /**
     * Read a byte from memory at the specified address.
     *
     * @param int $address Memory address (0x0000-0xFFFF)
     * @return int Byte value (0x00-0xFF), defaults to 0x00 if uninitialized
     */
    public function readByte(int $address): int
    {
        return $this->memory[$address & 0xFFFF] ?? 0x00;
    }

    /**
     * Write a byte to memory at the specified address.
     *
     * @param int $address Memory address (0x0000-0xFFFF)
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $address, int $value): void
    {
        $this->memory[$address & 0xFFFF] = $value & 0xFF;
    }

    /**
     * Load a chunk of memory at a specific address.
     * Useful for loading ROM data for testing.
     *
     * @param int $startAddress Starting address
     * @param array<int> $data Array of byte values
     */
    public function loadMemory(int $startAddress, array $data): void
    {
        foreach ($data as $offset => $byte) {
            $this->writeByte($startAddress + $offset, $byte);
        }
    }

    /**
     * Get all memory contents (for debugging/testing).
     *
     * @return array<int, int> Memory contents
     */
    public function getMemory(): array
    {
        return $this->memory;
    }

    /**
     * Clear all memory.
     */
    public function clear(): void
    {
        $this->memory = [];
    }
}
