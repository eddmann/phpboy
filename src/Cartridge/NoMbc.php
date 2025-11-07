<?php

declare(strict_types=1);

namespace Gb\Cartridge;

/**
 * No MBC (ROM Only) Cartridge
 *
 * Simple ROM-only cartridge with no banking.
 * - ROM: 32KB fixed at 0x0000-0x7FFF
 * - RAM: 8KB at 0xA000-0xBFFF (if available)
 * - Writes to ROM area are ignored
 *
 * Used by early Game Boy games like Tetris.
 */
final class NoMbc implements MbcInterface
{
    /** @var array<int, int> ROM data */
    private array $rom;

    /** @var array<int, int> External RAM */
    private array $ram;

    /** @var int RAM size in bytes */
    private int $ramSize;

    /** @var bool Whether RAM has battery backup */
    private bool $hasBattery;

    /**
     * @param array<int, int> $rom ROM data
     * @param int $ramSize RAM size in bytes
     * @param bool $hasBattery Whether RAM has battery backup
     */
    public function __construct(array $rom, int $ramSize, bool $hasBattery)
    {
        $this->rom = $rom;
        // ROM-only cartridges may still have RAM; default to 8KB if not specified
        $this->ramSize = $ramSize > 0 ? $ramSize : 8192;
        $this->hasBattery = $hasBattery;

        // Initialize RAM
        $this->ram = array_fill(0, $this->ramSize, 0x00);
    }

    public function readByte(int $address): int
    {
        if ($address < 0x8000) {
            // ROM read (0x0000-0x7FFF)
            return $this->rom[$address] ?? 0xFF;
        }

        if ($address >= 0xA000 && $address < 0xC000) {
            // External RAM read (0xA000-0xBFFF)
            if ($this->ramSize === 0) {
                return 0xFF; // No RAM
            }

            $offset = $address - 0xA000;
            if ($offset < $this->ramSize) {
                return $this->ram[$offset];
            }
            return 0xFF;
        }

        return 0xFF; // Out of range
    }

    public function writeByte(int $address, int $value): void
    {
        if ($address < 0x8000) {
            // ROM write - ignored for ROM-only cartridges
            return;
        }

        if ($address >= 0xA000 && $address < 0xC000) {
            // External RAM write (0xA000-0xBFFF)
            if ($this->ramSize === 0) {
                return; // No RAM
            }

            $offset = $address - 0xA000;
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
        // No-op for ROM-only cartridges
    }
}
