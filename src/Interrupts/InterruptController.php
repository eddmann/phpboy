<?php

declare(strict_types=1);

namespace Gb\Interrupts;

use Gb\Bus\DeviceInterface;

/**
 * Interrupt Controller for the Game Boy.
 *
 * Manages interrupt requests and enables via two registers:
 * - IF (Interrupt Flag) at 0xFF0F: tracks requested interrupts
 * - IE (Interrupt Enable) at 0xFFFF: masks which interrupts are enabled
 *
 * When an interrupt is both requested (IF bit set) and enabled (IE bit set),
 * and IME is enabled in the CPU, the interrupt can be serviced.
 *
 * Priority order (highest to lowest):
 * 1. VBlank (bit 0)
 * 2. LCD Stat (bit 1)
 * 3. Timer (bit 2)
 * 4. Serial (bit 3)
 * 5. Joypad (bit 4)
 *
 * Reference: Pan Docs - Interrupts
 */
final class InterruptController implements DeviceInterface
{
    private const IF_ADDRESS = 0xFF0F;
    private const IE_ADDRESS = 0xFFFF;

    /**
     * IF register: Interrupt Flag (0xFF0F)
     * Bits 0-4 represent pending interrupts, bits 5-7 unused (read as 1).
     */
    private int $interruptFlag = 0xE0; // Upper 3 bits always set

    /**
     * IE register: Interrupt Enable (0xFFFF)
     * Bits 0-4 mask which interrupts are enabled.
     */
    private int $interruptEnable = 0x00;

    /**
     * Read a byte from the interrupt controller.
     *
     * @param int $address Memory address (0xFF0F or 0xFFFF)
     * @return int Byte value (0x00-0xFF)
     */
    public function readByte(int $address): int
    {
        return match ($address) {
            self::IF_ADDRESS => $this->interruptFlag | 0xE0, // Upper 3 bits always 1
            self::IE_ADDRESS => $this->interruptEnable,
            default => 0xFF, // Unmapped addresses return 0xFF
        };
    }

    /**
     * Write a byte to the interrupt controller.
     *
     * @param int $address Memory address (0xFF0F or 0xFFFF)
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $address, int $value): void
    {
        match ($address) {
            self::IF_ADDRESS => $this->interruptFlag = ($value & 0x1F) | 0xE0, // Only bits 0-4 writable
            self::IE_ADDRESS => $this->interruptEnable = $value & 0x1F,        // Only bits 0-4 writable
            default => null, // Ignore writes to unmapped addresses
        };
    }

    /**
     * Request an interrupt by setting the corresponding bit in IF.
     *
     * @param InterruptType $type The interrupt type to request
     */
    public function requestInterrupt(InterruptType $type): void
    {
        $this->interruptFlag |= $type->getBitMask();
    }

    /**
     * Get the highest priority pending interrupt.
     *
     * Returns the first interrupt that is both requested (IF) and enabled (IE),
     * checking in priority order (VBlank > LCD > Timer > Serial > Joypad).
     *
     * @return InterruptType|null The pending interrupt, or null if none
     */
    public function getPendingInterrupt(): ?InterruptType
    {
        // Get interrupts that are both requested and enabled
        $pending = $this->interruptFlag & $this->interruptEnable & 0x1F;

        if ($pending === 0) {
            return null;
        }

        // Check each interrupt in priority order (bit 0 = highest priority)
        foreach (InterruptType::cases() as $interrupt) {
            if (($pending & $interrupt->getBitMask()) !== 0) {
                return $interrupt;
            }
        }

        return null;
    }

    /**
     * Acknowledge (clear) an interrupt by clearing the corresponding bit in IF.
     *
     * Called by the CPU after it has jumped to the interrupt vector.
     *
     * @param InterruptType $type The interrupt type to acknowledge
     */
    public function acknowledgeInterrupt(InterruptType $type): void
    {
        $this->interruptFlag &= ~$type->getBitMask();
    }

    /**
     * Check if any interrupt is pending (for HALT behavior).
     *
     * Returns true if any interrupt is requested (IF), regardless of IE or IME.
     * This is used to wake the CPU from HALT.
     *
     * @return bool True if any interrupt is requested
     */
    public function hasAnyPendingInterrupt(): bool
    {
        return ($this->interruptFlag & 0x1F) !== 0;
    }
}
