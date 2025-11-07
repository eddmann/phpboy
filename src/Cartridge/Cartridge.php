<?php

declare(strict_types=1);

namespace Gb\Cartridge;

use Gb\Bus\DeviceInterface;

/**
 * Game Boy Cartridge
 *
 * Handles ROM and RAM access for Game Boy cartridges.
 * For now, implements simple ROM-only cartridge (no MBC).
 * MBC support will be added in Step 10.
 *
 * Memory layout:
 * - 0x0000-0x3FFF: ROM Bank 0 (16KB, fixed)
 * - 0x4000-0x7FFF: ROM Bank N (16KB, switchable with MBC)
 * - 0xA000-0xBFFF: External RAM (8KB, switchable with MBC)
 *
 * For ROM-only cartridges:
 * - Total ROM: 32KB (no banking)
 * - No external RAM
 */
final class Cartridge implements DeviceInterface
{
    /** @var array<int, int> ROM data (up to 32KB for ROM-only) */
    private array $rom;

    /** @var array<int, int> External RAM (8KB) */
    private array $ram;

    /** @var int Total ROM size */
    private int $romSize;

    /** @var CartridgeHeader Parsed cartridge header */
    private readonly CartridgeHeader $header;

    /**
     * @param array<int, int> $romData ROM data loaded from .gb file
     */
    public function __construct(array $romData)
    {
        $this->rom = $romData;
        $this->romSize = count($romData);

        // Parse cartridge header
        $this->header = CartridgeHeader::fromRom($romData);

        // Initialize 8KB of external RAM (will be used by MBC cartridges)
        $this->ram = array_fill(0, 8192, 0x00);
    }

    /**
     * Read a byte from the cartridge.
     *
     * @param int $address Address within cartridge range (0x0000-0x7FFF for ROM, 0xA000-0xBFFF for RAM)
     * @return int Byte value (0x00-0xFF)
     */
    public function readByte(int $address): int
    {
        if ($address < 0x8000) {
            // ROM read (0x0000-0x7FFF)
            if ($address < $this->romSize) {
                return $this->rom[$address];
            }
            return 0xFF; // Out of bounds
        } elseif ($address >= 0xA000 && $address <= 0xBFFF) {
            // External RAM read (0xA000-0xBFFF)
            $offset = $address - 0xA000;
            return $this->ram[$offset];
        }

        return 0xFF; // Invalid address
    }

    /**
     * Write a byte to the cartridge.
     *
     * @param int $address Address within cartridge range
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $address, int $value): void
    {
        if ($address < 0x8000) {
            // ROM write - typically used for MBC control
            // For ROM-only cartridges, writes are ignored
            // MBC implementation will override this behavior
            return;
        } elseif ($address >= 0xA000 && $address <= 0xBFFF) {
            // External RAM write (0xA000-0xBFFF)
            $offset = $address - 0xA000;
            $this->ram[$offset] = $value & 0xFF;
        }
    }

    /**
     * Load ROM data from a byte array.
     *
     * @param array<int, int> $romData ROM data
     */
    public static function fromRom(array $romData): self
    {
        return new self($romData);
    }

    /**
     * Get the parsed cartridge header.
     *
     * @return CartridgeHeader
     */
    public function getHeader(): CartridgeHeader
    {
        return $this->header;
    }
}
