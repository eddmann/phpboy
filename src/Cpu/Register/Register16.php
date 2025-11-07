<?php

declare(strict_types=1);

namespace Gb\Cpu\Register;

/**
 * Register16 - 16-bit register abstraction
 *
 * Represents a 16-bit register with automatic masking
 * to ensure values stay within valid range (0x0000-0xFFFF).
 * Can also be used to represent register pairs (e.g., BC, DE, HL).
 */
final class Register16
{
    private int $value;

    /**
     * @param int $initialValue Initial register value (default: 0x0000)
     */
    public function __construct(int $initialValue = 0x0000)
    {
        $this->value = $initialValue & 0xFFFF;
    }

    /**
     * Get the current register value
     *
     * @return int Value in range 0x0000-0xFFFF
     */
    public function get(): int
    {
        return $this->value;
    }

    /**
     * Set the register value with automatic masking
     *
     * @param int $value Value to set (will be masked to 16-bit)
     */
    public function set(int $value): void
    {
        $this->value = $value & 0xFFFF;
    }

    /**
     * Get the high byte (upper 8 bits)
     *
     * @return int High byte (0x00-0xFF)
     */
    public function getHigh(): int
    {
        return ($this->value >> 8) & 0xFF;
    }

    /**
     * Get the low byte (lower 8 bits)
     *
     * @return int Low byte (0x00-0xFF)
     */
    public function getLow(): int
    {
        return $this->value & 0xFF;
    }

    /**
     * Set the high byte (upper 8 bits)
     *
     * @param int $value High byte value (will be masked to 8-bit)
     */
    public function setHigh(int $value): void
    {
        $this->value = (($value & 0xFF) << 8) | ($this->value & 0x00FF);
    }

    /**
     * Set the low byte (lower 8 bits)
     *
     * @param int $value Low byte value (will be masked to 8-bit)
     */
    public function setLow(int $value): void
    {
        $this->value = ($this->value & 0xFF00) | ($value & 0xFF);
    }

    /**
     * Increment the register value with wrapping
     */
    public function increment(): void
    {
        $this->value = ($this->value + 1) & 0xFFFF;
    }

    /**
     * Decrement the register value with wrapping
     */
    public function decrement(): void
    {
        $this->value = ($this->value - 1) & 0xFFFF;
    }
}
