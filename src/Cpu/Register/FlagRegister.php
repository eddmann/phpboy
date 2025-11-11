<?php

declare(strict_types=1);

namespace Gb\Cpu\Register;

/**
 * FlagRegister - CPU flags register (F register)
 *
 * The Game Boy CPU has 4 flags in the F register:
 * - Z (Zero) at bit 7: Set when result is zero
 * - N (Subtract) at bit 6: Set when last operation was subtraction
 * - H (Half Carry) at bit 5: Set when carry from bit 3 to bit 4
 * - C (Carry) at bit 4: Set when carry from bit 7 or borrow occurred
 *
 * Bits 0-3 are always 0 on the Game Boy.
 *
 * Phase 1 Optimization: Lazy synchronization
 * - Flag updates are extremely frequent (~500K times per second)
 * - AF register reads are infrequent (~10K times per second)
 * - Only sync when AF is actually accessed
 * - Expected gain: +5% performance
 */
final class FlagRegister
{
    private const int FLAG_ZERO = 0x80;        // Bit 7
    private const int FLAG_SUBTRACT = 0x40;    // Bit 6
    private const int FLAG_HALF_CARRY = 0x20;  // Bit 5
    private const int FLAG_CARRY = 0x10;       // Bit 4

    private int $value;
    private ?Register16 $afRegister = null;

    /**
     * Phase 1 Optimization: Lazy sync flag
     * True if flags have been modified since last AF sync
     */
    private bool $dirty = false;

    /**
     * @param int $initialValue Initial flag register value (default: 0x00)
     * @param Register16|null $afRegister Optional AF register to keep in sync
     */
    public function __construct(int $initialValue = 0x00, ?Register16 $afRegister = null)
    {
        // Mask to only keep flag bits (bits 4-7)
        $this->value = $initialValue & 0xF0;
        $this->afRegister = $afRegister;
        $this->dirty = true; // Mark as dirty to force initial sync
        $this->syncToAF();
    }

    /**
     * Get the raw flag register value
     *
     * @return int Value with only bits 4-7 set
     */
    public function get(): int
    {
        return $this->value;
    }

    /**
     * Set the raw flag register value
     *
     * Phase 1 Optimization: Mark dirty instead of immediate sync
     *
     * @param int $value Value to set (bits 0-3 will be cleared)
     */
    public function set(int $value): void
    {
        // Mask to only keep flag bits (bits 4-7)
        $this->value = $value & 0xF0;
        $this->markDirty();
    }

    /**
     * Synchronize flags to AF register (if linked)
     *
     * Phase 1 Optimization: Only sync if dirty
     */
    private function syncToAF(): void
    {
        if ($this->dirty && $this->afRegister !== null) {
            $this->afRegister->setLow($this->value);
            $this->dirty = false;
        }
    }

    /**
     * Phase 1 Optimization: Mark flags as dirty (needing sync)
     */
    private function markDirty(): void
    {
        $this->dirty = true;
    }

    /**
     * Phase 1 Optimization: Force immediate sync (called before AF reads)
     */
    public function flush(): void
    {
        $this->syncToAF();
    }

    /**
     * Synchronize flags from AF register (if linked)
     */
    public function syncFromAF(): void
    {
        if ($this->afRegister !== null) {
            $this->value = $this->afRegister->getLow() & 0xF0;
        }
    }

    /**
     * Get Zero flag (Z)
     *
     * @return bool True if zero flag is set
     */
    public function getZero(): bool
    {
        return ($this->value & self::FLAG_ZERO) !== 0;
    }

    /**
     * Set or clear Zero flag (Z)
     *
     * Phase 1 Optimization: Mark dirty instead of immediate sync
     *
     * @param bool $value True to set, false to clear
     */
    public function setZero(bool $value): void
    {
        if ($value) {
            $this->value |= self::FLAG_ZERO;
        } else {
            $this->value &= ~self::FLAG_ZERO;
        }
        $this->markDirty();
    }

    /**
     * Get Subtract flag (N)
     *
     * @return bool True if subtract flag is set
     */
    public function getSubtract(): bool
    {
        return ($this->value & self::FLAG_SUBTRACT) !== 0;
    }

    /**
     * Set or clear Subtract flag (N)
     *
     * Phase 1 Optimization: Mark dirty instead of immediate sync
     *
     * @param bool $value True to set, false to clear
     */
    public function setSubtract(bool $value): void
    {
        if ($value) {
            $this->value |= self::FLAG_SUBTRACT;
        } else {
            $this->value &= ~self::FLAG_SUBTRACT;
        }
        $this->markDirty();
    }

    /**
     * Get Half Carry flag (H)
     *
     * @return bool True if half carry flag is set
     */
    public function getHalfCarry(): bool
    {
        return ($this->value & self::FLAG_HALF_CARRY) !== 0;
    }

    /**
     * Set or clear Half Carry flag (H)
     *
     * Phase 1 Optimization: Mark dirty instead of immediate sync
     *
     * @param bool $value True to set, false to clear
     */
    public function setHalfCarry(bool $value): void
    {
        if ($value) {
            $this->value |= self::FLAG_HALF_CARRY;
        } else {
            $this->value &= ~self::FLAG_HALF_CARRY;
        }
        $this->markDirty();
    }

    /**
     * Get Carry flag (C)
     *
     * @return bool True if carry flag is set
     */
    public function getCarry(): bool
    {
        return ($this->value & self::FLAG_CARRY) !== 0;
    }

    /**
     * Set or clear Carry flag (C)
     *
     * Phase 1 Optimization: Mark dirty instead of immediate sync
     *
     * @param bool $value True to set, false to clear
     */
    public function setCarry(bool $value): void
    {
        if ($value) {
            $this->value |= self::FLAG_CARRY;
        } else {
            $this->value &= ~self::FLAG_CARRY;
        }
        $this->markDirty();
    }

    /**
     * Clear all flags
     *
     * Phase 1 Optimization: Mark dirty instead of immediate sync
     */
    public function clear(): void
    {
        $this->value = 0x00;
        $this->markDirty();
    }

    // Convenience aliases for shorter method names

    /**
     * Alias for getZero()
     */
    public function getZ(): bool
    {
        return $this->getZero();
    }

    /**
     * Alias for setZero()
     */
    public function setZ(bool $value): void
    {
        $this->setZero($value);
    }

    /**
     * Alias for getSubtract()
     */
    public function getN(): bool
    {
        return $this->getSubtract();
    }

    /**
     * Alias for setSubtract()
     */
    public function setN(bool $value): void
    {
        $this->setSubtract($value);
    }

    /**
     * Alias for getHalfCarry()
     */
    public function getH(): bool
    {
        return $this->getHalfCarry();
    }

    /**
     * Alias for setHalfCarry()
     */
    public function setH(bool $value): void
    {
        $this->setHalfCarry($value);
    }

    /**
     * Alias for getCarry()
     */
    public function getC(): bool
    {
        return $this->getCarry();
    }

    /**
     * Alias for setCarry()
     */
    public function setC(bool $value): void
    {
        $this->setCarry($value);
    }
}
