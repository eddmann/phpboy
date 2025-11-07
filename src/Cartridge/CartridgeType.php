<?php

declare(strict_types=1);

namespace Gb\Cartridge;

/**
 * Game Boy Cartridge Types
 *
 * Defines all known cartridge types based on the cartridge type byte (0x0147).
 * Each type specifies the Memory Bank Controller (MBC) and additional features
 * like RAM, battery backup, timer, rumble, etc.
 *
 * Reference: Pan Docs - Cartridge Type
 */
enum CartridgeType: int
{
    case ROM_ONLY = 0x00;
    case MBC1 = 0x01;
    case MBC1_RAM = 0x02;
    case MBC1_RAM_BATTERY = 0x03;
    case MBC2 = 0x05;
    case MBC2_BATTERY = 0x06;
    case ROM_RAM = 0x08;
    case ROM_RAM_BATTERY = 0x09;
    case MMM01 = 0x0B;
    case MMM01_RAM = 0x0C;
    case MMM01_RAM_BATTERY = 0x0D;
    case MBC3_TIMER_BATTERY = 0x0F;
    case MBC3_TIMER_RAM_BATTERY = 0x10;
    case MBC3 = 0x11;
    case MBC3_RAM = 0x12;
    case MBC3_RAM_BATTERY = 0x13;
    case MBC5 = 0x19;
    case MBC5_RAM = 0x1A;
    case MBC5_RAM_BATTERY = 0x1B;
    case MBC5_RUMBLE = 0x1C;
    case MBC5_RUMBLE_RAM = 0x1D;
    case MBC5_RUMBLE_RAM_BATTERY = 0x1E;
    case MBC6 = 0x20;
    case MBC7_SENSOR_RUMBLE_RAM_BATTERY = 0x22;
    case POCKET_CAMERA = 0xFC;
    case BANDAI_TAMA5 = 0xFD;
    case HuC3 = 0xFE;
    case HuC1_RAM_BATTERY = 0xFF;

    /**
     * Get the base MBC type (ignoring RAM, battery, rumble, etc.)
     *
     * @return string MBC type name (e.g., "MBC1", "MBC3", "MBC5", "NONE")
     */
    public function getMbcType(): string
    {
        return match ($this) {
            self::MBC1, self::MBC1_RAM, self::MBC1_RAM_BATTERY => 'MBC1',
            self::MBC2, self::MBC2_BATTERY => 'MBC2',
            self::MBC3_TIMER_BATTERY, self::MBC3_TIMER_RAM_BATTERY,
            self::MBC3, self::MBC3_RAM, self::MBC3_RAM_BATTERY => 'MBC3',
            self::MBC5, self::MBC5_RAM, self::MBC5_RAM_BATTERY,
            self::MBC5_RUMBLE, self::MBC5_RUMBLE_RAM, self::MBC5_RUMBLE_RAM_BATTERY => 'MBC5',
            self::MBC6 => 'MBC6',
            self::MBC7_SENSOR_RUMBLE_RAM_BATTERY => 'MBC7',
            self::MMM01, self::MMM01_RAM, self::MMM01_RAM_BATTERY => 'MMM01',
            self::HuC1_RAM_BATTERY => 'HuC1',
            self::HuC3 => 'HuC3',
            self::POCKET_CAMERA => 'CAMERA',
            self::BANDAI_TAMA5 => 'TAMA5',
            default => 'NONE',
        };
    }

    /**
     * Check if this cartridge type has external RAM.
     *
     * @return bool True if cartridge has RAM
     */
    public function hasRam(): bool
    {
        return match ($this) {
            self::MBC1_RAM, self::MBC1_RAM_BATTERY,
            self::MBC2, self::MBC2_BATTERY, // MBC2 has built-in RAM
            self::ROM_RAM, self::ROM_RAM_BATTERY,
            self::MMM01_RAM, self::MMM01_RAM_BATTERY,
            self::MBC3_TIMER_RAM_BATTERY, self::MBC3_RAM, self::MBC3_RAM_BATTERY,
            self::MBC5_RAM, self::MBC5_RAM_BATTERY,
            self::MBC5_RUMBLE_RAM, self::MBC5_RUMBLE_RAM_BATTERY,
            self::MBC7_SENSOR_RUMBLE_RAM_BATTERY,
            self::HuC1_RAM_BATTERY,
            self::HuC3 => true,
            default => false,
        };
    }

    /**
     * Check if this cartridge type has battery backup.
     *
     * @return bool True if cartridge has battery
     */
    public function hasBattery(): bool
    {
        return match ($this) {
            self::MBC1_RAM_BATTERY,
            self::MBC2_BATTERY,
            self::ROM_RAM_BATTERY,
            self::MMM01_RAM_BATTERY,
            self::MBC3_TIMER_BATTERY, self::MBC3_TIMER_RAM_BATTERY, self::MBC3_RAM_BATTERY,
            self::MBC5_RAM_BATTERY, self::MBC5_RUMBLE_RAM_BATTERY,
            self::MBC7_SENSOR_RUMBLE_RAM_BATTERY,
            self::HuC1_RAM_BATTERY => true,
            default => false,
        };
    }

    /**
     * Check if this cartridge type has a real-time clock (RTC).
     *
     * @return bool True if cartridge has RTC
     */
    public function hasTimer(): bool
    {
        return match ($this) {
            self::MBC3_TIMER_BATTERY, self::MBC3_TIMER_RAM_BATTERY => true,
            default => false,
        };
    }

    /**
     * Check if this cartridge type has rumble support.
     *
     * @return bool True if cartridge has rumble
     */
    public function hasRumble(): bool
    {
        return match ($this) {
            self::MBC5_RUMBLE, self::MBC5_RUMBLE_RAM, self::MBC5_RUMBLE_RAM_BATTERY,
            self::MBC7_SENSOR_RUMBLE_RAM_BATTERY => true,
            default => false,
        };
    }

    /**
     * Try to create CartridgeType from byte value.
     *
     * @param int $value Cartridge type byte
     * @return self|null CartridgeType or null if unknown
     */
    public static function tryFrom(int $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Get a human-readable description of the cartridge type.
     *
     * @return string Description
     */
    public function getDescription(): string
    {
        $parts = [];
        $parts[] = $this->getMbcType();

        if ($this->hasRam()) {
            $parts[] = 'RAM';
        }
        if ($this->hasBattery()) {
            $parts[] = 'Battery';
        }
        if ($this->hasTimer()) {
            $parts[] = 'Timer';
        }
        if ($this->hasRumble()) {
            $parts[] = 'Rumble';
        }

        return implode('+', $parts);
    }
}
