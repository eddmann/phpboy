<?php

declare(strict_types=1);

namespace Gb\Apu\Channel;

/**
 * Channel 1: Square Wave with Sweep
 *
 * NR10 (0xFF10): Sweep register
 * NR11 (0xFF11): Length timer & duty cycle
 * NR12 (0xFF12): Volume & envelope
 * NR13 (0xFF13): Period low
 * NR14 (0xFF14): Period high & control
 *
 * Reference: Pan Docs - Sound Controller
 */
final class Channel1 implements ChannelInterface
{
    // Duty cycle patterns (fraction of cycle that is high)
    private const DUTY_PATTERNS = [
        [0, 0, 0, 0, 0, 0, 0, 1], // 12.5%
        [1, 0, 0, 0, 0, 0, 0, 1], // 25%
        [1, 0, 0, 0, 0, 1, 1, 1], // 50%
        [0, 1, 1, 1, 1, 1, 1, 0], // 75%
    ];

    // NR10: Sweep
    private int $sweepPeriod = 0;      // Bits 6-4
    private bool $sweepNegate = false; // Bit 3
    private int $sweepShift = 0;       // Bits 2-0
    private int $sweepTimer = 0;
    private int $sweepShadow = 0;
    private bool $sweepEnabled = false;

    // NR11: Length timer & duty
    private int $duty = 0;             // Bits 7-6
    private int $lengthLoad = 0;       // Bits 5-0
    private int $lengthCounter = 0;

    // NR12: Volume envelope
    private int $initialVolume = 0;    // Bits 7-4
    private bool $envelopeAdd = false; // Bit 3
    private int $envelopePeriod = 0;   // Bits 2-0
    private int $currentVolume = 0;
    private int $envelopeTimer = 0;

    // NR13/NR14: Frequency
    private int $frequency = 0;        // 11-bit frequency (NR13 + NR14 bits 2-0)
    private bool $lengthEnable = false; // NR14 bit 6
    private int $frequencyTimer = 0;
    private int $dutyPosition = 0;

    private bool $enabled = false;
    private bool $dacEnabled = false;

    /**
     * Write to NR10 (0xFF10): Sweep register
     */
    public function writeNR10(int $value): void
    {
        $this->sweepPeriod = ($value >> 4) & 0x07;
        $this->sweepNegate = (bool) ($value & 0x08);
        $this->sweepShift = $value & 0x07;
    }

    /**
     * Read from NR10 (0xFF10): Sweep register
     */
    public function readNR10(): int
    {
        return 0x80 | ($this->sweepPeriod << 4) | ($this->sweepNegate ? 0x08 : 0) | $this->sweepShift;
    }

    /**
     * Write to NR11 (0xFF11): Length timer & duty
     */
    public function writeNR11(int $value): void
    {
        $this->duty = ($value >> 6) & 0x03;
        $this->lengthLoad = $value & 0x3F;
        $this->lengthCounter = 64 - $this->lengthLoad;
    }

    /**
     * Read from NR11 (0xFF11): Length timer & duty
     */
    public function readNR11(): int
    {
        return 0x3F | ($this->duty << 6);
    }

    /**
     * Write to NR12 (0xFF12): Volume envelope
     */
    public function writeNR12(int $value): void
    {
        $this->initialVolume = ($value >> 4) & 0x0F;
        $this->envelopeAdd = (bool) ($value & 0x08);
        $this->envelopePeriod = $value & 0x07;

        // DAC is enabled if top 5 bits of NR12 are not all 0
        $this->dacEnabled = ($value & 0xF8) !== 0;
        if (!$this->dacEnabled) {
            $this->enabled = false;
        }
    }

    /**
     * Read from NR12 (0xFF12): Volume envelope
     */
    public function readNR12(): int
    {
        return ($this->initialVolume << 4) | ($this->envelopeAdd ? 0x08 : 0) | $this->envelopePeriod;
    }

    /**
     * Write to NR13 (0xFF13): Frequency low
     */
    public function writeNR13(int $value): void
    {
        $this->frequency = ($this->frequency & 0x0700) | $value;
    }

    /**
     * Read from NR13 (0xFF13): Frequency low (write-only)
     */
    public function readNR13(): int
    {
        return 0xFF;
    }

    /**
     * Write to NR14 (0xFF14): Frequency high & control
     */
    public function writeNR14(int $value): void
    {
        $this->frequency = ($this->frequency & 0x00FF) | (($value & 0x07) << 8);
        $this->lengthEnable = (bool) ($value & 0x40);

        // Trigger
        if ($value & 0x80) {
            $this->trigger();
        }
    }

    /**
     * Read from NR14 (0xFF14): Frequency high & control
     */
    public function readNR14(): int
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
        $this->frequencyTimer = (2048 - $this->frequency) * 4;

        // Reset envelope
        $this->envelopeTimer = $this->envelopePeriod;
        $this->currentVolume = $this->initialVolume;

        // Reset sweep
        $this->sweepShadow = $this->frequency;
        $this->sweepTimer = $this->sweepPeriod > 0 ? $this->sweepPeriod : 8;
        $this->sweepEnabled = $this->sweepPeriod > 0 || $this->sweepShift > 0;

        // Perform sweep calculation overflow check
        if ($this->sweepShift > 0) {
            $this->calculateSweep();
        }

        // If DAC is off, channel is disabled
        if (!$this->dacEnabled) {
            $this->enabled = false;
        }
    }

    public function getSample(): float
    {
        if (!$this->enabled || !$this->dacEnabled) {
            return 0.0;
        }

        $pattern = self::DUTY_PATTERNS[$this->duty];
        $dutySample = $pattern[$this->dutyPosition];

        // Output is current volume * duty cycle
        return $dutySample === 1 ? ($this->currentVolume / 15.0) * 2.0 - 1.0 : -1.0;
    }

    public function step(): void
    {
        if (!$this->enabled) {
            return;
        }

        // Clock frequency timer
        $this->frequencyTimer--;
        if ($this->frequencyTimer <= 0) {
            $this->frequencyTimer = (2048 - $this->frequency) * 4;
            $this->dutyPosition = ($this->dutyPosition + 1) & 0x07;
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

    /**
     * Clock the sweep unit (called by frame sequencer at 128 Hz).
     */
    public function clockSweep(): void
    {
        $this->sweepTimer--;
        if ($this->sweepTimer <= 0) {
            $this->sweepTimer = $this->sweepPeriod > 0 ? $this->sweepPeriod : 8;

            if ($this->sweepEnabled && $this->sweepPeriod > 0) {
                $newFreq = $this->calculateSweep();
                if ($newFreq <= 2047 && $this->sweepShift > 0) {
                    $this->frequency = $newFreq;
                    $this->sweepShadow = $newFreq;

                    // Perform overflow check again
                    $this->calculateSweep();
                }
            }
        }
    }

    /**
     * Calculate new frequency from sweep.
     * Disables channel if overflow occurs.
     *
     * @return int New frequency
     */
    private function calculateSweep(): int
    {
        $offset = $this->sweepShadow >> $this->sweepShift;
        $newFreq = $this->sweepNegate
            ? $this->sweepShadow - $offset
            : $this->sweepShadow + $offset;

        // Check for overflow
        if ($newFreq > 2047) {
            $this->enabled = false;
        }

        return $newFreq;
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
