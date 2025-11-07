<?php

declare(strict_types=1);

namespace Gb\Ppu;

use Gb\Bus\DeviceInterface;

/**
 * Object Attribute Memory (OAM)
 *
 * 160 bytes of sprite attribute memory mapped to 0xFE00-0xFE9F.
 * Contains 40 sprite entries, each 4 bytes:
 *   - Byte 0: Y position
 *   - Byte 1: X position
 *   - Byte 2: Tile index
 *   - Byte 3: Attributes/flags
 *
 * OAM is directly accessible by the CPU when not in use by the PPU.
 * During certain PPU modes, OAM access is restricted (returns 0xFF).
 */
final class Oam implements DeviceInterface
{
    /** @var array<int, int> OAM storage (160 bytes = 40 sprites × 4 bytes) */
    private array $oam;

    public function __construct()
    {
        // Initialize 160 bytes of OAM with 0x00
        $this->oam = array_fill(0, 160, 0x00);
    }

    /**
     * Read a byte from OAM.
     *
     * @param int $address Address within OAM (0xFE00-0xFE9F, but passed as 0x00-0x9F offset)
     * @return int Byte value (0x00-0xFF)
     */
    public function readByte(int $address): int
    {
        $offset = $address & 0xFF; // Mask to 160 bytes
        if ($offset >= 160) {
            return 0xFF; // Out of range
        }
        return $this->oam[$offset];
    }

    /**
     * Write a byte to OAM.
     *
     * @param int $address Address within OAM (0xFE00-0xFE9F, but passed as 0x00-0x9F offset)
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $address, int $value): void
    {
        $offset = $address & 0xFF; // Mask to 160 bytes
        if ($offset < 160) {
            $this->oam[$offset] = $value & 0xFF;
        }
    }

    /**
     * Get direct access to OAM data (for PPU access and DMA).
     * This allows the PPU and DMA controller to access OAM efficiently.
     *
     * @return array<int, int> Reference to OAM array
     */
    public function getData(): array
    {
        return $this->oam;
    }
}
