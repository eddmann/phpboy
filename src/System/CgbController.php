<?php

declare(strict_types=1);

namespace Gb\System;

use Gb\Bus\DeviceInterface;
use Gb\Memory\Vram;
use Gb\Memory\Wram;

/**
 * Game Boy Color Controller
 *
 * Handles CGB-specific registers:
 * - VBK (0xFF4F): VRAM bank select
 * - KEY1 (0xFF4D): Speed switch control
 * - RP (0xFF56): Infrared communications port (stub)
 * - SVBK (0xFF70): WRAM bank select
 * - HDMA1-5 (0xFF51-0xFF55): HDMA registers (handled by HdmaController)
 *
 * Reference: Pan Docs - CGB Registers
 */
final class CgbController implements DeviceInterface
{
    // Register addresses
    private const KEY1 = 0xFF4D; // Speed switch
    private const VBK = 0xFF4F;  // VRAM bank
    private const RP = 0xFF56;   // Infrared port
    private const SVBK = 0xFF70; // WRAM bank

    /** @var int KEY1 register: speed switch control */
    private int $key1 = 0x00;

    /** @var bool Current speed mode (false=normal, true=double) */
    private bool $doubleSpeed = false;

    public function __construct(
        private readonly Vram $vram,
        private readonly Wram $wram,
    ) {
    }

    public function readByte(int $address): int
    {
        return match ($address) {
            self::KEY1 => $this->readKey1(),
            self::VBK => $this->vram->getBank() | 0xFE, // Only bit 0 used, others return 1
            self::RP => 0xFF, // Infrared stub: always return 0xFF
            self::SVBK => $this->wram->getBank() | 0xF8, // Only bits 0-2 used, others return 1
            default => 0xFF,
        };
    }

    public function writeByte(int $address, int $value): void
    {
        match ($address) {
            self::KEY1 => $this->writeKey1($value),
            self::VBK => $this->vram->setBank($value & 0x01),
            self::RP => null, // Infrared stub: ignore writes
            self::SVBK => $this->wram->setBank($value & 0x07),
            default => null,
        };
    }

    private function readKey1(): int
    {
        // Bit 7: Current speed (0=normal, 1=double)
        // Bit 0: Prepare speed switch
        $speedBit = $this->doubleSpeed ? 0x80 : 0x00;
        return ($this->key1 & 0x01) | $speedBit | 0x7E; // Bits 1-6 always 1
    }

    private function writeKey1(int $value): void
    {
        // Only bit 0 is writable (prepare speed switch)
        $this->key1 = $value & 0x01;
    }

    /**
     * Trigger speed switch (called by STOP instruction when KEY1 bit 0 is set).
     */
    public function triggerSpeedSwitch(): void
    {
        if (($this->key1 & 0x01) !== 0) {
            $this->doubleSpeed = !$this->doubleSpeed;
            $this->key1 = 0x00; // Clear prepare bit
        }
    }

    /**
     * Check if currently in double-speed mode.
     *
     * @return bool True if in double-speed mode
     */
    public function isDoubleSpeed(): bool
    {
        return $this->doubleSpeed;
    }

    /**
     * Check if speed switch is prepared.
     *
     * @return bool True if KEY1 bit 0 is set
     */
    public function isSpeedSwitchPrepared(): bool
    {
        return ($this->key1 & 0x01) !== 0;
    }
}
