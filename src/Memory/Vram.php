<?php

declare(strict_types=1);

namespace Gb\Memory;

use Gb\Bus\DeviceInterface;

/**
 * Video RAM (VRAM)
 *
 * 8KB of video RAM mapped to 0x8000-0x9FFF.
 * Contains tile data and tile maps used by the PPU.
 *
 * In DMG mode: Single 8KB bank
 * In CGB mode: 2 banks (16KB total), switchable via VBK register
 *
 * Memory layout:
 * - 0x8000-0x87FF: Tile data block 0
 * - 0x8800-0x8FFF: Tile data block 1
 * - 0x9000-0x97FF: Tile data block 2
 * - 0x9800-0x9BFF: Tile map 0
 * - 0x9C00-0x9FFF: Tile map 1
 *
 * For now, implements DMG-style 8KB VRAM. CGB bank switching will be added later.
 */
final class Vram implements DeviceInterface
{
    /** @var array<int, int> Video RAM storage (8KB = 8192 bytes) */
    private array $ram;

    public function __construct()
    {
        // Initialize 8KB of VRAM with 0x00
        $this->ram = array_fill(0, 8192, 0x00);
    }

    /**
     * Read a byte from VRAM.
     *
     * @param int $address Address within VRAM (0x8000-0x9FFF, but passed as 0x0000-0x1FFF offset)
     * @return int Byte value (0x00-0xFF)
     */
    public function readByte(int $address): int
    {
        $offset = $address & 0x1FFF; // Mask to 8KB
        return $this->ram[$offset];
    }

    /**
     * Write a byte to VRAM.
     *
     * @param int $address Address within VRAM (0x8000-0x9FFF, but passed as 0x0000-0x1FFF offset)
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $address, int $value): void
    {
        $offset = $address & 0x1FFF; // Mask to 8KB
        $this->ram[$offset] = $value & 0xFF;
    }

    /**
     * Get direct access to VRAM data (for PPU access).
     * This allows the PPU to read VRAM efficiently without going through the bus.
     *
     * @return array<int, int> Reference to VRAM array
     */
    public function getData(): array
    {
        return $this->ram;
    }
}
