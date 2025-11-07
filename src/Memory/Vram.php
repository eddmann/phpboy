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
 * - 0x9800-0x9BFF: Tile map 0 (Bank 0: tile indices, Bank 1: tile attributes in CGB)
 * - 0x9C00-0x9FFF: Tile map 1 (Bank 0: tile indices, Bank 1: tile attributes in CGB)
 *
 * Bank 0: Tile data (same as DMG)
 * Bank 1: Tile attributes (CGB only) for tile maps
 */
final class Vram implements DeviceInterface
{
    /** @var array<int, array<int, int>> Video RAM storage (2 banks × 8KB) */
    private array $banks;

    /** @var int Current VRAM bank (0 or 1) */
    private int $currentBank = 0;

    public function __construct()
    {
        // Initialize 2 banks of 8KB VRAM with 0x00
        $this->banks = [
            0 => array_fill(0, 8192, 0x00),
            1 => array_fill(0, 8192, 0x00),
        ];
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
        return $this->banks[$this->currentBank][$offset];
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
        $this->banks[$this->currentBank][$offset] = $value & 0xFF;
    }

    /**
     * Get direct access to VRAM bank data (for PPU access).
     * This allows the PPU to read VRAM efficiently without going through the bus.
     *
     * @param int $bank Bank number (0 or 1)
     * @return array<int, int> Reference to VRAM bank array
     */
    public function getData(int $bank = 0): array
    {
        return $this->banks[$bank];
    }

    /**
     * Set the current VRAM bank (CGB only).
     *
     * @param int $bank Bank number (0 or 1)
     */
    public function setBank(int $bank): void
    {
        $this->currentBank = $bank & 0x01; // Only bit 0 is used
    }

    /**
     * Get the current VRAM bank.
     *
     * @return int Current bank number (0 or 1)
     */
    public function getBank(): int
    {
        return $this->currentBank;
    }
}
