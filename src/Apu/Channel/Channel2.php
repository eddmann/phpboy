<?php

declare(strict_types=1);

namespace Gb\Apu\Channel;

/**
 * Channel 2: Square Wave (without sweep)
 *
 * NR21 (0xFF16): Length timer & duty cycle
 * NR22 (0xFF17): Volume & envelope
 * NR23 (0xFF18): Period low
 * NR24 (0xFF19): Period high & control
 *
 * Reference: Pan Docs - Sound Controller
 */
final class Channel2 implements ChannelInterface
{
    // Duty cycle patterns (fraction of cycle that is high)
    private const DUTY_PATTERNS = [
        [0, 0, 0, 0, 0, 0, 0, 1], // 12.5%
        [1, 0, 0, 0, 0, 0, 0, 1], // 25%
        [1, 0, 0, 0, 0, 1, 1, 1], // 50%
        [0, 1, 1, 1, 1, 1, 1, 0], // 75%
    ];

    // NR21: Length timer & duty
    private int $duty = 0;             // Bits 7-6
    private int $lengthLoad = 0;       // Bits 5-0
    private int $lengthCounter = 0;

    // NR22: Volume envelope
    private int $initialVolume = 0;    // Bits 7-4
    private bool $envelopeAdd = false; // Bit 3
    private int $envelopePeriod = 0;   // Bits 2-0
    private int $currentVolume = 0;
    private int $envelopeTimer = 0;

    // NR23/NR24: Frequency
    private int $frequency = 0;        // 11-bit frequency
    private bool $lengthEnable = false;
    private int $frequencyTimer = 0;
    private int $dutyPosition = 0;

    private bool $enabled = false;
    private bool $dacEnabled = false;

    /**
     * Write to NR21 (0xFF16): Length timer & duty
     */
    public function writeNR21(int $value): void
    {
        $this->duty = ($value >> 6) & 0x03;
        $this->lengthLoad = $value & 0x3F;
        $this->lengthCounter = 64 - $this->lengthLoad;
    }

    /**
     * Read from NR21 (0xFF16): Length timer & duty
     * Lower 6 bits are write-only and return as 1s
     */
    public function readNR21(): int
    {
        return ($this->duty << 6) | 0x3F;
    }

    /**
     * Write to NR22 (0xFF17): Volume envelope
     */
    public function writeNR22(int $value): void
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
     * Read from NR22 (0xFF17): Volume envelope
     */
    public function readNR22(): int
    {
        return ($this->initialVolume << 4) | ($this->envelopeAdd ? 0x08 : 0) | $this->envelopePeriod;
    }

    /**
     * Write to NR23 (0xFF18): Frequency low
     */
    public function writeNR23(int $value): void
    {
        $this->frequency = ($this->frequency & 0x0700) | $value;
    }

    /**
     * Read from NR23 (0xFF18): Frequency low (write-only)
     */
    public function readNR23(): int
    {
        return 0xFF;
    }

    /**
     * Write to NR24 (0xFF19): Frequency high & control
     */
    public function writeNR24(int $value): void
    {
        $this->frequency = ($this->frequency & 0x00FF) | (($value & 0x07) << 8);
        $this->lengthEnable = (bool) ($value & 0x40);

        // Trigger
        if ($value & 0x80) {
            $this->trigger();
        }
    }

    /**
     * Read from NR24 (0xFF19): Frequency high & control
     */
    public function readNR24(): int
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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    // Savestate serialization methods

    public function getLengthCounter(): int
    {
        return $this->lengthCounter;
    }

    public function getCurrentVolume(): int
    {
        return $this->currentVolume;
    }

    public function getEnvelopeTimer(): int
    {
        return $this->envelopeTimer;
    }

    public function getFrequencyTimer(): int
    {
        return $this->frequencyTimer;
    }

    public function getDutyPosition(): int
    {
        return $this->dutyPosition;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function getDacEnabled(): bool
    {
        return $this->dacEnabled;
    }

    public function setLengthCounter(int $value): void
    {
        $this->lengthCounter = $value;
    }

    public function setCurrentVolume(int $value): void
    {
        $this->currentVolume = $value;
    }

    public function setEnvelopeTimer(int $value): void
    {
        $this->envelopeTimer = $value;
    }

    public function setFrequencyTimer(int $value): void
    {
        $this->frequencyTimer = $value;
    }

    public function setDutyPosition(int $value): void
    {
        $this->dutyPosition = $value;
    }

    public function setEnabled(bool $value): void
    {
        $this->enabled = $value;
    }

    public function setDacEnabled(bool $value): void
    {
        $this->dacEnabled = $value;
    }
}
