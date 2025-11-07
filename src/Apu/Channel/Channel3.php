<?php

declare(strict_types=1);

namespace Gb\Apu\Channel;

/**
 * Channel 3: Wave Channel
 *
 * NR30 (0xFF1A): DAC enable
 * NR31 (0xFF1B): Length timer
 * NR32 (0xFF1C): Output level
 * NR33 (0xFF1D): Period low
 * NR34 (0xFF1E): Period high & control
 * Wave RAM (0xFF30-0xFF3F): 16 bytes = 32 4-bit samples
 *
 * Reference: Pan Docs - Sound Controller
 */
final class Channel3 implements ChannelInterface
{
    // NR30: DAC enable
    private bool $dacEnabled = false;

    // NR31: Length timer
    private int $lengthCounter = 0;

    // NR32: Output level (volume shift)
    private int $outputLevel = 0; // Bits 6-5: 0=mute, 1=100%, 2=50%, 3=25%

    // NR33/NR34: Frequency
    private int $frequency = 0;
    private bool $lengthEnable = false;
    private int $frequencyTimer = 0;
    private int $samplePosition = 0;

    private bool $enabled = false;

    /** @var array<int, int> Wave RAM (16 bytes, each containing two 4-bit samples) */
    private array $waveRam = [];

    public function __construct()
    {
        // Initialize wave RAM to zeros
        for ($i = 0; $i < 16; $i++) {
            $this->waveRam[$i] = 0;
        }
    }

    /**
     * Write to NR30 (0xFF1A): DAC enable
     */
    public function writeNR30(int $value): void
    {
        $this->dacEnabled = (bool) ($value & 0x80);
        if (!$this->dacEnabled) {
            $this->enabled = false;
        }
    }

    /**
     * Read from NR30 (0xFF1A): DAC enable
     */
    public function readNR30(): int
    {
        return 0x7F | ($this->dacEnabled ? 0x80 : 0);
    }

    /**
     * Write to NR31 (0xFF1B): Length timer
     */
    public function writeNR31(int $value): void
    {
        $this->lengthCounter = 256 - $value;
    }

    /**
     * Read from NR31 (0xFF1B): Length timer (write-only)
     */
    public function readNR31(): int
    {
        return 0xFF;
    }

    /**
     * Write to NR32 (0xFF1C): Output level
     */
    public function writeNR32(int $value): void
    {
        $this->outputLevel = ($value >> 5) & 0x03;
    }

    /**
     * Read from NR32 (0xFF1C): Output level
     */
    public function readNR32(): int
    {
        return 0x9F | ($this->outputLevel << 5);
    }

    /**
     * Write to NR33 (0xFF1D): Frequency low
     */
    public function writeNR33(int $value): void
    {
        $this->frequency = ($this->frequency & 0x0700) | $value;
    }

    /**
     * Read from NR33 (0xFF1D): Frequency low (write-only)
     */
    public function readNR33(): int
    {
        return 0xFF;
    }

    /**
     * Write to NR34 (0xFF1E): Frequency high & control
     */
    public function writeNR34(int $value): void
    {
        $this->frequency = ($this->frequency & 0x00FF) | (($value & 0x07) << 8);
        $this->lengthEnable = (bool) ($value & 0x40);

        // Trigger
        if ($value & 0x80) {
            $this->trigger();
        }
    }

    /**
     * Read from NR34 (0xFF1E): Frequency high & control
     */
    public function readNR34(): int
    {
        return 0xBF | ($this->lengthEnable ? 0x40 : 0);
    }

    /**
     * Write to wave RAM (0xFF30-0xFF3F)
     */
    public function writeWaveRam(int $offset, int $value): void
    {
        $this->waveRam[$offset & 0x0F] = $value;
    }

    /**
     * Read from wave RAM (0xFF30-0xFF3F)
     */
    public function readWaveRam(int $offset): int
    {
        return $this->waveRam[$offset & 0x0F];
    }

    /**
     * Trigger the channel
     */
    private function trigger(): void
    {
        $this->enabled = true;

        // If length counter is 0, set it to 256
        if ($this->lengthCounter === 0) {
            $this->lengthCounter = 256;
        }

        // Reload frequency timer
        $this->frequencyTimer = (2048 - $this->frequency) * 2;

        // Reset sample position
        $this->samplePosition = 0;

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

        // Get the current 4-bit sample from wave RAM
        $byteIndex = $this->samplePosition / 2;
        $nibbleHigh = ($this->samplePosition % 2) === 0;
        $byte = $this->waveRam[$byteIndex];
        $sample4bit = $nibbleHigh ? ($byte >> 4) : ($byte & 0x0F);

        // Apply volume shift based on output level
        $shifted = match ($this->outputLevel) {
            0 => 0,              // Mute (0%)
            1 => $sample4bit,    // 100%
            2 => $sample4bit >> 1, // 50%
            3 => $sample4bit >> 2, // 25%
            default => 0,
        };

        // Convert 4-bit sample (0-15) to float (-1.0 to 1.0)
        return ($shifted / 7.5) - 1.0;
    }

    public function step(): void
    {
        if (!$this->enabled) {
            return;
        }

        // Clock frequency timer
        $this->frequencyTimer--;
        if ($this->frequencyTimer <= 0) {
            $this->frequencyTimer = (2048 - $this->frequency) * 2;
            $this->samplePosition = ($this->samplePosition + 1) % 32;
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
        // Wave channel has no envelope
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
