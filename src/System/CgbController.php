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
 * - KEY0 (0xFF4C): CGB mode enable (undocumented)
 * - KEY1 (0xFF4D): Speed switch control
 * - VBK (0xFF4F): VRAM bank select
 * - RP (0xFF56): Infrared communications port (stub)
 * - OPRI (0xFF6C): Object priority mode
 * - SVBK (0xFF70): WRAM bank select (CGB only)
 * - HDMA1-5 (0xFF51-0xFF55): HDMA registers (future)
 *
 * Reference: Pan Docs - CGB Registers
 */
final class CgbController implements DeviceInterface
{
    // Register addresses
    private const KEY0 = 0xFF4C; // CGB mode enable (undocumented)
    private const KEY1 = 0xFF4D; // Speed switch
    private const VBK = 0xFF4F;  // VRAM bank
    private const RP = 0xFF56;   // Infrared port
    private const OPRI = 0xFF6C; // Object priority mode
    private const SVBK = 0xFF70; // WRAM bank

    /** @var int KEY0 register: CGB mode enable (0x04=DMG mode, 0x80=CGB mode) */
    private int $key0 = 0x00;

    /** @var int KEY1 register: speed switch control */
    private int $key1 = 0x00;

    /** @var bool Current speed mode (false=normal, true=double) */
    private bool $doubleSpeed = false;

    /** @var int OPRI register: object priority mode (bit 0) */
    private int $opri = 0x00;

    /** @var bool Is KEY0 writable (becomes read-only after first write to 0xFF50) */
    private bool $key0Writable = true;

    public function __construct(
        private readonly Vram $vram,
        private readonly Wram $wram,
        bool $isCgbMode = false,
    ) {
        // Initialize KEY0 and OPRI based on CGB mode
        if ($isCgbMode) {
            $this->key0 = 0x80; // CGB mode enabled
            $this->opri = 0x00; // CGB uses OAM position priority
        } else {
            $this->key0 = 0x04; // DMG compatibility mode
            $this->opri = 0x01; // DMG uses coordinate-based priority
        }
    }

    public function readByte(int $address): int
    {
        return match ($address) {
            self::KEY0 => $this->key0,
            self::KEY1 => $this->readKey1(),
            self::VBK => $this->vram->getBank() | 0xFE, // Only bit 0 used, others return 1
            self::RP => 0xFF, // Infrared stub: always return 0xFF
            self::OPRI => $this->opri | 0xFE, // Only bit 0 used, others return 1
            self::SVBK => $this->wram->getCurrentBank() | 0xF8, // Only bits 2-0 used, others return 1
            default => 0xFF,
        };
    }

    public function writeByte(int $address, int $value): void
    {
        match ($address) {
            self::KEY0 => $this->writeKey0($value),
            self::KEY1 => $this->writeKey1($value),
            self::VBK => $this->vram->setBank($value & 0x01),
            self::RP => null, // Infrared stub: ignore writes
            self::OPRI => $this->opri = $value & 0x01, // Only bit 0 is writable
            self::SVBK => $this->wram->setCurrentBank($value & 0x07), // Bits 2-0 for bank 0-7
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

    private function writeKey0(int $value): void
    {
        // KEY0 is only writable before boot ROM is disabled (0xFF50 write)
        // After that, it becomes read-only
        if ($this->key0Writable) {
            $this->key0 = $value;
        }
    }

    private function writeKey1(int $value): void
    {
        // Only bit 0 is writable (prepare speed switch)
        $this->key1 = $value & 0x01;
    }

    /**
     * Disable KEY0 write access (called when boot ROM is disabled via 0xFF50).
     */
    public function lockKey0(): void
    {
        $this->key0Writable = false;
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

    /**
     * Get KEY0 register value (for savestate serialization).
     *
     * @return int KEY0 register value
     */
    public function getKey0(): int
    {
        return $this->key0;
    }

    /**
     * Get KEY1 register value (for savestate serialization).
     *
     * @return int KEY1 register value
     */
    public function getKey1(): int
    {
        return $this->key1;
    }

    /**
     * Get OPRI register value (for savestate serialization).
     *
     * @return int OPRI register value
     */
    public function getOpri(): int
    {
        return $this->opri;
    }

    /**
     * Get KEY0 writable flag (for savestate serialization).
     *
     * @return bool True if KEY0 is writable
     */
    public function isKey0Writable(): bool
    {
        return $this->key0Writable;
    }

    /**
     * Set KEY0 register value (for savestate deserialization).
     *
     * @param int $value KEY0 register value
     */
    public function setKey0(int $value): void
    {
        $this->key0 = $value;
    }

    /**
     * Set KEY1 register value (for savestate deserialization).
     *
     * @param int $value KEY1 register value
     */
    public function setKey1(int $value): void
    {
        $this->key1 = $value & 0x01;
    }

    /**
     * Set OPRI register value (for savestate deserialization).
     *
     * @param int $value OPRI register value
     */
    public function setOpri(int $value): void
    {
        $this->opri = $value & 0x01;
    }

    /**
     * Set double-speed mode (for savestate deserialization).
     *
     * @param bool $doubleSpeed True if in double-speed mode
     */
    public function setDoubleSpeed(bool $doubleSpeed): void
    {
        $this->doubleSpeed = $doubleSpeed;
    }

    /**
     * Set KEY0 writable flag (for savestate deserialization).
     *
     * @param bool $writable True if KEY0 is writable
     */
    public function setKey0Writable(bool $writable): void
    {
        $this->key0Writable = $writable;
    }
}
