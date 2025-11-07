<?php

declare(strict_types=1);

namespace Gb\Cpu\Register;

/**
 * Register8 - 8-bit register abstraction
 *
 * Represents a single 8-bit register with automatic masking
 * to ensure values stay within valid range (0x00-0xFF).
 */
final class Register8
{
    private int $value;

    /**
     * @param int $initialValue Initial register value (default: 0x00)
     */
    public function __construct(int $initialValue = 0x00)
    {
        $this->value = $initialValue & 0xFF;
    }

    /**
     * Get the current register value
     *
     * @return int Value in range 0x00-0xFF
     */
    public function get(): int
    {
        return $this->value;
    }

    /**
     * Set the register value with automatic masking
     *
     * @param int $value Value to set (will be masked to 8-bit)
     */
    public function set(int $value): void
    {
        $this->value = $value & 0xFF;
    }

    /**
     * Increment the register value with wrapping
     */
    public function increment(): void
    {
        $this->value = ($this->value + 1) & 0xFF;
    }

    /**
     * Decrement the register value with wrapping
     */
    public function decrement(): void
    {
        $this->value = ($this->value - 1) & 0xFF;
    }
}
