<?php

declare(strict_types=1);

namespace Gb\Clock;

/**
 * Clock - Cycle tracking service
 *
 * Tracks CPU cycle count for synchronization between CPU, PPU, APU, and Timer.
 * The Game Boy operates at 4.194304 MHz (4194304 Hz), with each machine cycle (M-cycle)
 * taking 4 clock cycles (T-cycles).
 *
 * This class tracks M-cycles for timing accuracy across all emulator components.
 */
final class Clock
{
    private int $cycles = 0;

    /**
     * Advance the clock by the specified number of cycles
     *
     * @param int $cycles Number of cycles to advance (typically 1, 2, 3, or 4 M-cycles)
     */
    public function tick(int $cycles): void
    {
        $this->cycles += $cycles;
    }

    /**
     * Get the total cycle count
     *
     * @return int Total cycles elapsed
     */
    public function getCycles(): int
    {
        return $this->cycles;
    }

    /**
     * Reset the clock to zero
     *
     * Useful for testing or when restarting emulation
     */
    public function reset(): void
    {
        $this->cycles = 0;
    }

    /**
     * Get elapsed cycles since a given checkpoint and update checkpoint
     *
     * @param int &$checkpoint Reference to checkpoint value (will be updated to current cycles)
     * @return int Cycles elapsed since checkpoint
     */
    public function getElapsed(int &$checkpoint): int
    {
        $elapsed = $this->cycles - $checkpoint;
        $checkpoint = $this->cycles;
        return $elapsed;
    }
}
