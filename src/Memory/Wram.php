<?php

declare(strict_types=1);

namespace Gb\Memory;

use Gb\Bus\DeviceInterface;

/**
 * Working RAM (WRAM)
 *
 * 8KB of working RAM mapped to 0xC000-0xDFFF.
 * In DMG mode: Single 8KB bank (only bank 0 and 1 used)
 * In CGB mode: Bank 0 (0xC000-0xCFFF) fixed + switchable banks 1-7 (0xD000-0xDFFF)
 *
 * Total: 8 banks × 4KB = 32KB
 */
final class Wram implements DeviceInterface
{
    /** @var array<int, array<int, int>> Working RAM storage (8 banks × 4KB) */
    private array $banks;

    /** @var int Current bank selected for 0xD000-0xDFFF (1-7) */
    private int $currentBank = 1;

    public function __construct()
    {
        // Initialize 8 banks of 4KB each
        $this->banks = [];
        for ($bank = 0; $bank < 8; $bank++) {
            $this->banks[$bank] = array_fill(0, 4096, 0x00);
        }
    }

    /**
     * Read a byte from WRAM.
     *
     * @param int $address Address within WRAM (0xC000-0xDFFF, but passed as 0x0000-0x1FFF offset)
     * @return int Byte value (0x00-0xFF)
     */
    public function readByte(int $address): int
    {
        // Mask to 8KB for backward compatibility
        $address = $address & 0x1FFF;

        if ($address < 0x1000) {
            // 0xC000-0xCFFF: Bank 0 (fixed)
            return $this->banks[0][$address];
        } else {
            // 0xD000-0xDFFF: Switchable bank (1-7)
            $offset = $address - 0x1000;
            return $this->banks[$this->currentBank][$offset];
        }
    }

    /**
     * Write a byte to WRAM.
     *
     * @param int $address Address within WRAM (0xC000-0xDFFF, but passed as 0x0000-0x1FFF offset)
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $address, int $value): void
    {
        // Mask to 8KB for backward compatibility
        $address = $address & 0x1FFF;
        $value = $value & 0xFF;

        if ($address < 0x1000) {
            // 0xC000-0xCFFF: Bank 0 (fixed)
            $this->banks[0][$address] = $value;
        } else {
            // 0xD000-0xDFFF: Switchable bank (1-7)
            $offset = $address - 0x1000;
            $this->banks[$this->currentBank][$offset] = $value;
        }
    }

    /**
     * Get current bank number (for savestate serialization).
     *
     * @return int Current bank (1-7)
     */
    public function getCurrentBank(): int
    {
        return $this->currentBank;
    }

    /**
     * Set current bank number.
     *
     * @param int $bank Bank number (0-7, but 0 is treated as 1)
     */
    public function setCurrentBank(int $bank): void
    {
        // Bank 0 is treated as bank 1
        $this->currentBank = ($bank & 0x07) === 0 ? 1 : ($bank & 0x07);
    }

    /**
     * Get all bank data (for savestate serialization).
     *
     * @return array<int, array<int, int>> All 8 banks
     */
    public function getAllBanks(): array
    {
        return $this->banks;
    }

    /**
     * Set bank data (for savestate deserialization).
     *
     * @param int $bankNumber Bank number (0-7)
     * @param array<int, int> $data Bank data (4096 bytes)
     */
    public function setBankData(int $bankNumber, array $data): void
    {
        if ($bankNumber >= 0 && $bankNumber < 8) {
            $this->banks[$bankNumber] = $data;
        }
    }
}
