<?php

declare(strict_types=1);

namespace Gb\Cartridge;

/**
 * MBC5 (Memory Bank Controller 5)
 *
 * Features:
 * - ROM banking: up to 512 banks (8MB max) with 9-bit bank number
 * - RAM banking: up to 16 banks (128KB max)
 * - Optional rumble support (not emulated)
 *
 * Register Layout:
 * - 0x0000-0x1FFF: RAM Enable (write 0x0A to enable)
 * - 0x2000-0x2FFF: ROM Bank Number (lower 8 bits)
 * - 0x3000-0x3FFF: ROM Bank Number (9th bit, bit 0)
 * - 0x4000-0x5FFF: RAM Bank Number (4 bits, 0x00-0x0F)
 *
 * Unlike MBC1, MBC5:
 * - Does not have the bank 0 quirk (bank 0 is valid)
 * - Uses 9-bit ROM bank numbers (0x000-0x1FF)
 * - Supports up to 16 RAM banks
 *
 * Reference: Pan Docs - MBC5
 */
final class Mbc5 implements MbcInterface
{
    /** @var array<int, int> ROM data */
    private array $rom;

    /** @var array<int, int> External RAM */
    private array $ram;

    /** @var int Number of ROM banks */
    private int $romBankCount;

    /** @var int RAM size in bytes */
    private int $ramSize;

    /** @var bool Whether RAM has battery backup */
    private bool $hasBattery;

    /** @var bool Whether rumble is present */
    private bool $hasRumble;

    /** @var bool RAM enable flag */
    private bool $ramEnabled = false;

    /** @var int ROM bank number (9 bits, 0x000-0x1FF) */
    private int $romBank = 0x01;

    /** @var int RAM bank number (4 bits, 0x00-0x0F) */
    private int $ramBank = 0x00;

    /**
     * @param array<int, int> $rom ROM data
     * @param int $romSize ROM size in bytes
     * @param int $ramSize RAM size in bytes
     * @param bool $hasBattery Whether RAM has battery backup
     * @param bool $hasRumble Whether rumble is present
     */
    public function __construct(array $rom, int $romSize, int $ramSize, bool $hasBattery, bool $hasRumble)
    {
        $this->rom = $rom;
        $this->romBankCount = max(2, (int)($romSize / (16 * 1024)));
        $this->ramSize = $ramSize;
        $this->hasBattery = $hasBattery;
        $this->hasRumble = $hasRumble;

        // Initialize RAM
        $this->ram = array_fill(0, $ramSize, 0x00);
    }

    public function readByte(int $address): int
    {
        if ($address < 0x4000) {
            // ROM Bank 0 (0x0000-0x3FFF)
            return $this->rom[$address] ?? 0xFF;
        }

        if ($address < 0x8000) {
            // ROM Bank N (0x4000-0x7FFF)
            $bank = $this->romBank % $this->romBankCount;
            $offset = $bank * 0x4000 + ($address - 0x4000);
            if ($offset < count($this->rom)) {
                return $this->rom[$offset];
            }
            return 0xFF;
        }

        if ($address >= 0xA000 && $address < 0xC000) {
            // External RAM (0xA000-0xBFFF)
            if (!$this->ramEnabled || $this->ramSize === 0) {
                return 0xFF; // RAM disabled or not present
            }

            $ramBankCount = max(1, $this->ramSize / 0x2000);
            $bank = $this->ramBank % $ramBankCount;
            $offset = $bank * 0x2000 + ($address - 0xA000);

            if ($offset < $this->ramSize) {
                return $this->ram[$offset];
            }
            return 0xFF;
        }

        return 0xFF; // Out of range
    }

    public function writeByte(int $address, int $value): void
    {
        if ($address < 0x2000) {
            // RAM Enable (0x0000-0x1FFF)
            $this->ramEnabled = ($value & 0x0F) === 0x0A;
            return;
        }

        if ($address < 0x3000) {
            // ROM Bank Number - lower 8 bits (0x2000-0x2FFF)
            $this->romBank = ($this->romBank & 0x100) | ($value & 0xFF);
            return;
        }

        if ($address < 0x4000) {
            // ROM Bank Number - 9th bit (0x3000-0x3FFF)
            $this->romBank = ($this->romBank & 0xFF) | (($value & 0x01) << 8);
            return;
        }

        if ($address < 0x6000) {
            // RAM Bank Number (0x4000-0x5FFF)
            // If rumble is present, bit 3 controls rumble (not emulated)
            // Mask out rumble bit if rumble is enabled
            $mask = $this->hasRumble ? 0x07 : 0x0F;
            $this->ramBank = $value & $mask;
            return;
        }

        if ($address >= 0xA000 && $address < 0xC000) {
            // External RAM write (0xA000-0xBFFF)
            if (!$this->ramEnabled || $this->ramSize === 0) {
                return; // RAM disabled or not present
            }

            $ramBankCount = max(1, $this->ramSize / 0x2000);
            $bank = $this->ramBank % $ramBankCount;
            $offset = $bank * 0x2000 + ($address - 0xA000);

            if ($offset < $this->ramSize) {
                $this->ram[$offset] = $value & 0xFF;
            }
        }
    }

    public function getRam(): array
    {
        return $this->ram;
    }

    public function setRam(array $ram): void
    {
        $this->ram = $ram;
    }

    public function hasBatteryBackedRam(): bool
    {
        return $this->hasBattery && $this->ramSize > 0;
    }

    public function step(int $cycles): void
    {
        // No-op for MBC5
    }
}
