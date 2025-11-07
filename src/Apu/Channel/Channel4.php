<?php

declare(strict_types=1);

namespace Gb\Apu\Channel;

/**
 * Channel 4: Noise Generator
 *
 * NR41 (0xFF20): Length timer
 * NR42 (0xFF21): Volume & envelope
 * NR43 (0xFF22): Frequency & randomness
 * NR44 (0xFF23): Control
 *
 * Uses LFSR (Linear Feedback Shift Register) for noise generation.
 *
 * Reference: Pan Docs - Sound Controller
 */
final class Channel4 implements ChannelInterface
{
    // Divisor codes for frequency calculation
    private const DIVISORS = [8, 16, 32, 48, 64, 80, 96, 112];

    // NR41: Length timer
    private int $lengthLoad = 0;
    private int $lengthCounter = 0;

    // NR42: Volume envelope
    private int $initialVolume = 0;    // Bits 7-4
    private bool $envelopeAdd = false; // Bit 3
    private int $envelopePeriod = 0;   // Bits 2-0
    private int $currentVolume = 0;
    private int $envelopeTimer = 0;

    // NR43: Frequency & randomness
    private int $clockShift = 0;       // Bits 7-4
    private bool $widthMode = false;   // Bit 3 (0=15-bit, 1=7-bit)
    private int $divisorCode = 0;      // Bits 2-0

    // NR44: Control
    private bool $lengthEnable = false;

    // LFSR state
    private int $lfsr = 0x7FFF; // 15-bit LFSR
    private int $frequencyTimer = 0;

    private bool $enabled = false;
    private bool $dacEnabled = false;

    /**
     * Write to NR41 (0xFF20): Length timer
     */
    public function writeNR41(int $value): void
    {
        $this->lengthLoad = $value & 0x3F;
        $this->lengthCounter = 64 - $this->lengthLoad;
    }

    /**
     * Read from NR41 (0xFF20): Length timer (write-only)
     */
    public function readNR41(): int
    {
        return 0xFF;
    }

    /**
     * Write to NR42 (0xFF21): Volume envelope
     */
    public function writeNR42(int $value): void
    {
        $this->initialVolume = ($value >> 4) & 0x0F;
        $this->envelopeAdd = (bool) ($value & 0x08);
        $this->envelopePeriod = $value & 0x07;

        // DAC is enabled if top 5 bits are not all 0
        $this->dacEnabled = ($value & 0xF8) !== 0;
        if (!$this->dacEnabled) {
            $this->enabled = false;
        }
    }

    /**
     * Read from NR42 (0xFF21): Volume envelope
     */
    public function readNR42(): int
    {
        return ($this->initialVolume << 4) | ($this->envelopeAdd ? 0x08 : 0) | $this->envelopePeriod;
    }

    /**
     * Write to NR43 (0xFF22): Frequency & randomness
     */
    public function writeNR43(int $value): void
    {
        $this->clockShift = ($value >> 4) & 0x0F;
        $this->widthMode = (bool) ($value & 0x08);
        $this->divisorCode = $value & 0x07;
    }

    /**
     * Read from NR43 (0xFF22): Frequency & randomness
     */
    public function readNR43(): int
    {
        return ($this->clockShift << 4) | ($this->widthMode ? 0x08 : 0) | $this->divisorCode;
    }

    /**
     * Write to NR44 (0xFF23): Control
     */
    public function writeNR44(int $value): void
    {
        $this->lengthEnable = (bool) ($value & 0x40);

        // Trigger
        if ($value & 0x80) {
            $this->trigger();
        }
    }

    /**
     * Read from NR44 (0xFF23): Control
     */
    public function readNR44(): int
    {
        return 0xBF | ($this->lengthEnable ? 0x40 : 0);
    }

    /**
     * Trigger the channel
     */
    private function trigger(): void
    {
        $this->enabled = true;

        // If length counter is 0, set it to 64
        if ($this->lengthCounter === 0) {
            $this->lengthCounter = 64;
        }

        // Reload frequency timer
        $this->frequencyTimer = $this->calculatePeriod();

        // Reset envelope
        $this->envelopeTimer = $this->envelopePeriod;
        $this->currentVolume = $this->initialVolume;

        // Reset LFSR to all 1s
        $this->lfsr = 0x7FFF;

        // If DAC is off, channel is disabled
        if (!$this->dacEnabled) {
            $this->enabled = false;
        }
    }

    /**
     * Calculate the period for the frequency timer.
     */
    private function calculatePeriod(): int
    {
        $divisor = self::DIVISORS[$this->divisorCode];
        return $divisor << $this->clockShift;
    }

    public function getSample(): float
    {
        if (!$this->enabled || !$this->dacEnabled) {
            return 0.0;
        }

        // Output is bit 0 of LFSR
        $output = ($this->lfsr & 0x01) === 0 ? 1 : 0;

        // Scale by current volume
        return $output === 1 ? ($this->currentVolume / 15.0) * 2.0 - 1.0 : -1.0;
    }

    public function step(): void
    {
        if (!$this->enabled) {
            return;
        }

        // Clock frequency timer
        $this->frequencyTimer--;
        if ($this->frequencyTimer <= 0) {
            $this->frequencyTimer = $this->calculatePeriod();

            // Clock LFSR
            $bit0 = $this->lfsr & 0x01;
            $bit1 = ($this->lfsr >> 1) & 0x01;
            $xorResult = $bit0 ^ $bit1;

            // Shift LFSR right
            $this->lfsr >>= 1;

            // Set bit 14 to XOR result
            $this->lfsr |= $xorResult << 14;

            // If width mode is 1 (7-bit), also set bit 6
            if ($this->widthMode) {
                $this->lfsr &= ~0x40; // Clear bit 6
                $this->lfsr |= $xorResult << 6;
            }

            // Keep LFSR to 15 bits
            $this->lfsr &= 0x7FFF;
        }
    }

    public function clockLength(): void
    {
        if ($this->lengthEnable && $this->lengthCounter > 0) {
            $this->lengthCounter--;
            if ($this->lengthCounter === 0) {
                $this->enabled = false;
            }
        }
    }

    public function clockEnvelope(): void
    {
        if ($this->envelopePeriod === 0) {
            return;
        }

        $this->envelopeTimer--;
        if ($this->envelopeTimer <= 0) {
            $this->envelopeTimer = $this->envelopePeriod;

            if ($this->envelopeAdd && $this->currentVolume < 15) {
                $this->currentVolume++;
            } elseif (!$this->envelopeAdd && $this->currentVolume > 0) {
                $this->currentVolume--;
            }
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }
}
