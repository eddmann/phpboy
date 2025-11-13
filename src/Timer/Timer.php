<?php

declare(strict_types=1);

namespace Gb\Timer;

use Gb\Bus\DeviceInterface;
use Gb\Interrupts\InterruptController;
use Gb\Interrupts\InterruptType;

/**
 * Timer Controller for the Game Boy.
 *
 * Manages four timer-related registers:
 * - DIV (0xFF04): Divider register, increments at 16384 Hz
 * - TIMA (0xFF05): Timer counter, increments at programmable frequency
 * - TMA (0xFF06): Timer modulo, reload value for TIMA on overflow
 * - TAC (0xFF07): Timer control, enable bit and frequency select
 *
 * DIV increments every 256 M-cycles (1024 T-cycles / 256 CPU cycles).
 * TIMA frequency is selected via TAC bits 0-1:
 * - 00: 4096 Hz   (1024 M-cycles / 4096 CPU cycles)
 * - 01: 262144 Hz (16 M-cycles / 64 CPU cycles)
 * - 10: 65536 Hz  (64 M-cycles / 256 CPU cycles)
 * - 11: 16384 Hz  (256 M-cycles / 1024 CPU cycles)
 *
 * When TIMA overflows, it is reloaded from TMA and a timer interrupt is requested.
 *
 * Reference: Pan Docs - Timer and Divider Registers
 */
final class Timer implements DeviceInterface
{
    private const DIV_ADDRESS = 0xFF04;
    private const TIMA_ADDRESS = 0xFF05;
    private const TMA_ADDRESS = 0xFF06;
    private const TAC_ADDRESS = 0xFF07;

    /**
     * DIV register: Divider register (0xFF04)
     * Upper 8 bits of the 16-bit internal divider counter.
     */
    private int $div = 0x00;

    /**
     * Internal divider counter (16-bit).
     * DIV is the upper 8 bits of this counter.
     */
    private int $divCounter = 0x0000;

    /**
     * TIMA register: Timer counter (0xFF05)
     */
    private int $tima = 0x00;

    /**
     * TMA register: Timer modulo (0xFF06)
     * Value to reload TIMA when it overflows.
     */
    private int $tma = 0x00;

    /**
     * TAC register: Timer control (0xFF07)
     * Bit 2: Timer enable (1=enabled, 0=disabled)
     * Bits 1-0: Clock select (frequency)
     */
    private int $tac = 0x00;

    /**
     * Internal counter for TIMA increments.
     */
    private int $timaCounter = 0;

    /**
     * @param InterruptController $interruptController Interrupt controller for timer interrupts
     */
    public function __construct(
        private readonly InterruptController $interruptController,
    ) {
    }

    /**
     * Read a byte from the timer registers.
     *
     * @param int $address Memory address (0xFF04-0xFF07)
     * @return int Byte value (0x00-0xFF)
     */
    public function readByte(int $address): int
    {
        return match ($address) {
            self::DIV_ADDRESS => $this->div,
            self::TIMA_ADDRESS => $this->tima,
            self::TMA_ADDRESS => $this->tma,
            self::TAC_ADDRESS => $this->tac | 0xF8, // Upper 5 bits always read as 1
            default => 0xFF,
        };
    }

    /**
     * Write a byte to the timer registers.
     *
     * @param int $address Memory address (0xFF04-0xFF07)
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $address, int $value): void
    {
        match ($address) {
            self::DIV_ADDRESS => $this->resetDiv(),
            self::TIMA_ADDRESS => $this->tima = $value & 0xFF,
            self::TMA_ADDRESS => $this->tma = $value & 0xFF,
            self::TAC_ADDRESS => $this->tac = $value & 0x07, // Only bits 0-2 writable
            default => null,
        };
    }

    /**
     * Reset the DIV register and internal divider counter.
     * Writing any value to DIV resets it to 0x00.
     */
    private function resetDiv(): void
    {
        $this->div = 0x00;
        $this->divCounter = 0x0000;
    }

    /**
     * Update the timer by the specified number of CPU cycles (T-cycles).
     *
     * @param int $cycles Number of CPU cycles elapsed (typically 4, 8, 12, 16, 20, or 24)
     */
    public function tick(int $cycles): void
    {
        // Update DIV (increments every 256 CPU cycles / 1024 T-cycles)
        $this->divCounter += $cycles;
        while ($this->divCounter >= 256) {
            $this->divCounter -= 256;
            $this->div = ($this->div + 1) & 0xFF;
        }

        // Update TIMA if timer is enabled (TAC bit 2)
        if (($this->tac & 0x04) !== 0) {
            $frequency = $this->getTimerFrequency();
            $this->timaCounter += $cycles;

            while ($this->timaCounter >= $frequency) {
                $this->timaCounter -= $frequency;
                $this->incrementTima();
            }
        }
    }

    /**
     * Get the number of CPU cycles per TIMA increment based on TAC frequency select.
     *
     * @return int Cycles per TIMA increment (64, 256, 1024, or 4096)
     */
    private function getTimerFrequency(): int
    {
        return match ($this->tac & 0x03) {
            0b00 => 1024, // 4096 Hz
            0b01 => 16,   // 262144 Hz
            0b10 => 64,   // 65536 Hz
            0b11 => 256,  // 16384 Hz
        };
    }

    /**
     * Increment TIMA and handle overflow.
     */
    private function incrementTima(): void
    {
        $this->tima++;

        if ($this->tima > 0xFF) {
            // TIMA overflow: reload from TMA and request timer interrupt
            $this->tima = $this->tma;
            $this->interruptController->requestInterrupt(InterruptType::Timer);
        }
    }

    /**
     * Get the current DIV register value.
     *
     * @return int DIV register (0x00-0xFF)
     */
    public function getDiv(): int
    {
        return $this->div;
    }

    /**
     * Get the internal divider counter.
     *
     * @return int 16-bit divider counter
     */
    public function getDivCounter(): int
    {
        return $this->divCounter;
    }

    /**
     * Get the TIMA register value.
     *
     * @return int TIMA register (0x00-0xFF)
     */
    public function getTima(): int
    {
        return $this->tima;
    }

    /**
     * Get the TMA register value.
     *
     * @return int TMA register (0x00-0xFF)
     */
    public function getTma(): int
    {
        return $this->tma;
    }

    /**
     * Get the TAC register value.
     *
     * @return int TAC register (0x00-0x07)
     */
    public function getTac(): int
    {
        return $this->tac;
    }

    /**
     * Get the TIMA counter accumulator.
     *
     * @return int TIMA counter
     */
    public function getTimaCounter(): int
    {
        return $this->timaCounter;
    }

    /**
     * Set the DIV register value (used for savestate restoration).
     *
     * @param int $value DIV register value
     */
    public function setDiv(int $value): void
    {
        $this->div = $value & 0xFF;
    }

    /**
     * Set the internal divider counter (used for savestate restoration).
     *
     * @param int $value 16-bit divider counter
     */
    public function setDivCounter(int $value): void
    {
        $this->divCounter = $value & 0xFFFF;
    }

    /**
     * Set the TIMA register value (used for savestate restoration).
     *
     * @param int $value TIMA register value
     */
    public function setTima(int $value): void
    {
        $this->tima = $value & 0xFF;
    }

    /**
     * Set the TMA register value (used for savestate restoration).
     *
     * @param int $value TMA register value
     */
    public function setTma(int $value): void
    {
        $this->tma = $value & 0xFF;
    }

    /**
     * Set the TAC register value (used for savestate restoration).
     *
     * @param int $value TAC register value
     */
    public function setTac(int $value): void
    {
        $this->tac = $value & 0x07;
    }

    /**
     * Set the TIMA counter accumulator (used for savestate restoration).
     *
     * @param int $value TIMA counter
     */
    public function setTimaCounter(int $value): void
    {
        $this->timaCounter = $value;
    }
}
