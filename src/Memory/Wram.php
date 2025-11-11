<?php

declare(strict_types=1);

namespace Gb\Memory;

use Gb\Bus\DeviceInterface;

/**
 * Working RAM (WRAM)
 *
 * 8KB of working RAM mapped to 0xC000-0xDFFF.
 * In DMG mode: Single 8KB bank
 * In CGB mode: Bank 0 (0xC000-0xCFFF) + switchable banks 1-7 (0xD000-0xDFFF)
 *
 * CGB WRAM: 32KB (8 banks of 4KB each)
 * - Bank 0: Always at 0xC000-0xCFFF
 * - Banks 1-7: Switchable at 0xD000-0xDFFF via SVBK register
 */
final class Wram implements DeviceInterface
{
    /** @var array<int, array<int, int>> Working RAM storage (8 banks × 4KB) */
    private array $banks;

    /** @var int Currently selected bank for 0xD000-0xDFFF (1-7, defaults to 1) */
    private int $currentBank = 1;

    public function __construct()
    {
        // Initialize 8 banks of 4KB each with 0x00
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
        $offset = $address & 0x1FFF;

        if ($offset < 0x1000) {
            // 0xC000-0xCFFF: Always bank 0
            return $this->banks[0][$offset];
        } else {
            // 0xD000-0xDFFF: Switchable bank (1-7)
            $bankOffset = $offset - 0x1000;
            return $this->banks[$this->currentBank][$bankOffset];
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
        $offset = $address & 0x1FFF;
        $value = $value & 0xFF;

        if ($offset < 0x1000) {
            // 0xC000-0xCFFF: Always bank 0
            $this->banks[0][$offset] = $value;
        } else {
            // 0xD000-0xDFFF: Switchable bank (1-7)
            $bankOffset = $offset - 0x1000;
            $this->banks[$this->currentBank][$bankOffset] = $value;
        }
    }

    /**
     * Set the current WRAM bank (CGB only, controlled by SVBK register).
     *
     * @param int $bank Bank number (0-7, where 0 is treated as 1)
     */
    public function setBank(int $bank): void
    {
        // Bank 0 is treated as bank 1 (banks 1-7 are valid)
        $this->currentBank = ($bank & 0x07) === 0 ? 1 : ($bank & 0x07);
    }

    /**
     * Get the current WRAM bank number.
     *
     * @return int Current bank (1-7)
     */
    public function getBank(): int
    {
        return $this->currentBank;
    }
}
