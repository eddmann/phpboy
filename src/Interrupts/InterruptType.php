<?php

declare(strict_types=1);

namespace Gb\Interrupts;

/**
 * Interrupt types for the Game Boy.
 *
 * The Game Boy supports 5 hardware interrupts, each with a priority level
 * (VBlank has highest priority, Joypad has lowest).
 *
 * Each interrupt has:
 * - A bit position in IF/IE registers
 * - A memory address vector where the ISR begins
 * - A priority level (lower bit = higher priority)
 *
 * Reference: Pan Docs - Interrupts
 */
enum InterruptType: int
{
    case VBlank = 0;  // Bit 0, Vector 0x0040, Priority 1 (highest)
    case LcdStat = 1; // Bit 1, Vector 0x0048, Priority 2
    case Timer = 2;   // Bit 2, Vector 0x0050, Priority 3
    case Serial = 3;  // Bit 3, Vector 0x0058, Priority 4
    case Joypad = 4;  // Bit 4, Vector 0x0060, Priority 5 (lowest)

    /**
     * Get the interrupt vector address for this interrupt type.
     *
     * @return int Memory address where ISR should execute (0x0040-0x0060)
     */
    public function getVector(): int
    {
        return match ($this) {
            self::VBlank => 0x0040,
            self::LcdStat => 0x0048,
            self::Timer => 0x0050,
            self::Serial => 0x0058,
            self::Joypad => 0x0060,
        };
    }

    /**
     * Get the bit mask for this interrupt type.
     *
     * @return int Bit mask (0x01, 0x02, 0x04, 0x08, or 0x10)
     */
    public function getBitMask(): int
    {
        return 1 << $this->value;
    }
}
