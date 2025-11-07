<?php

declare(strict_types=1);

namespace Gb\Cartridge;

/**
 * MBC1 (Memory Bank Controller 1)
 *
 * Features:
 * - ROM banking: up to 125 usable banks (2MB max)
 * - RAM banking: up to 4 banks (32KB max)
 * - Two banking modes: ROM mode and RAM mode
 *
 * Register Layout:
 * - 0x0000-0x1FFF: RAM Enable (write 0x0A to enable, anything else to disable)
 * - 0x2000-0x3FFF: ROM Bank Number (lower 5 bits)
 * - 0x4000-0x5FFF: RAM Bank Number / Upper ROM Bank Number (2 bits)
 * - 0x6000-0x7FFF: Banking Mode Select (0 = ROM mode, 1 = RAM mode)
 *
 * ROM Bank 0 quirk: Banks 0x00, 0x20, 0x40, 0x60 are never selected for 0x4000-0x7FFF.
 * They automatically increment to 0x01, 0x21, 0x41, 0x61 respectively.
 *
 * Reference: Pan Docs - MBC1
 */
final class Mbc1 implements MbcInterface
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

    /** @var bool RAM enable flag */
    private bool $ramEnabled = false;

    /** @var int ROM bank number (lower 5 bits) */
    private int $romBankLower = 0x01;

    /** @var int RAM bank number / upper ROM bank bits (2 bits) */
    private int $bankUpper = 0x00;

    /** @var int Banking mode (0 = ROM mode, 1 = RAM mode) */
    private int $bankingMode = 0;

    /**
     * @param array<int, int> $rom ROM data
     * @param int $romSize ROM size in bytes
     * @param int $ramSize RAM size in bytes
     * @param bool $hasBattery Whether RAM has battery backup
     */
    public function __construct(array $rom, int $romSize, int $ramSize, bool $hasBattery)
    {
        $this->rom = $rom;
        $this->romBankCount = max(2, (int)($romSize / (16 * 1024)));
        $this->ramSize = $ramSize;
        $this->hasBattery = $hasBattery;

        // Initialize RAM
        $this->ram = array_fill(0, $ramSize, 0x00);
    }

    public function readByte(int $address): int
    {
        if ($address < 0x4000) {
            // ROM Bank 0 (0x0000-0x3FFF)
            // In ROM mode, bank 0 is fixed
            // In RAM mode, bank 0 can be offset by upper bits
            $bank = $this->bankingMode === 1 ? ($this->bankUpper << 5) : 0;
            $bank = $bank % $this->romBankCount;
            $offset = $bank * 0x4000 + $address;
            return $this->rom[$offset] ?? 0xFF;
        }

        if ($address < 0x8000) {
            // ROM Bank N (0x4000-0x7FFF)
            $bank = ($this->bankUpper << 5) | $this->romBankLower;
            $bank = $bank % $this->romBankCount;
            $offset = $bank * 0x4000 + ($address - 0x4000);
            return $this->rom[$offset] ?? 0xFF;
        }

        if ($address >= 0xA000 && $address < 0xC000) {
            // External RAM (0xA000-0xBFFF)
            if (!$this->ramEnabled || $this->ramSize === 0) {
                return 0xFF; // RAM disabled or not present
            }

            // RAM bank selection (only in RAM mode)
            $ramBank = $this->bankingMode === 1 ? $this->bankUpper : 0;
            $ramBank = $ramBank % max(1, $this->ramSize / 0x2000);

            $offset = $ramBank * 0x2000 + ($address - 0xA000);
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
            // Write 0x0A to enable RAM, anything else disables it
            $this->ramEnabled = ($value & 0x0F) === 0x0A;
            return;
        }

        if ($address < 0x4000) {
            // ROM Bank Number - lower 5 bits (0x2000-0x3FFF)
            $this->romBankLower = $value & 0x1F;

            // Bank 0 is not selectable, use bank 1 instead
            if ($this->romBankLower === 0x00) {
                $this->romBankLower = 0x01;
            }
            return;
        }

        if ($address < 0x6000) {
            // RAM Bank Number / Upper ROM Bank Number (0x4000-0x5FFF)
            $this->bankUpper = $value & 0x03;
            return;
        }

        if ($address < 0x8000) {
            // Banking Mode Select (0x6000-0x7FFF)
            $this->bankingMode = $value & 0x01;
            return;
        }

        if ($address >= 0xA000 && $address < 0xC000) {
            // External RAM write (0xA000-0xBFFF)
            if (!$this->ramEnabled || $this->ramSize === 0) {
                return; // RAM disabled or not present
            }

            // RAM bank selection (only in RAM mode)
            $ramBank = $this->bankingMode === 1 ? $this->bankUpper : 0;
            $ramBank = $ramBank % max(1, $this->ramSize / 0x2000);

            $offset = $ramBank * 0x2000 + ($address - 0xA000);
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
        // No-op for MBC1
    }
}
