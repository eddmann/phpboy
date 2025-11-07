<?php

declare(strict_types=1);

namespace Gb\Apu;

use Gb\Apu\Channel\Channel1;
use Gb\Apu\Channel\Channel2;
use Gb\Apu\Channel\Channel3;
use Gb\Apu\Channel\Channel4;
use Gb\Bus\DeviceInterface;

/**
 * Audio Processing Unit (APU)
 *
 * Manages 4 audio channels:
 * - Channel 1: Square wave with sweep
 * - Channel 2: Square wave
 * - Channel 3: Wave output
 * - Channel 4: Noise
 *
 * Frame Sequencer runs at 512 Hz (every 8192 M-cycles):
 * Step 0: Length
 * Step 1: -
 * Step 2: Length + Sweep
 * Step 3: -
 * Step 4: Length
 * Step 5: -
 * Step 6: Length + Sweep
 * Step 7: Envelope
 *
 * Reference: Pan Docs - Sound Controller
 */
final class Apu implements DeviceInterface
{
    // Frame sequencer timing
    private const FRAME_SEQUENCER_RATE = 8192; // M-cycles per step (512 Hz)
    private const SAMPLE_RATE = 44100; // Output sample rate
    private const CPU_CLOCK_SPEED = 4194304; // CPU clock speed in Hz
    private const CYCLES_PER_SAMPLE = self::CPU_CLOCK_SPEED / self::SAMPLE_RATE; // ~95.1 cycles

    // Register addresses
    private const NR10 = 0xFF10; // Channel 1 sweep
    private const NR11 = 0xFF11; // Channel 1 sound length/wave pattern
    private const NR12 = 0xFF12; // Channel 1 volume envelope
    private const NR13 = 0xFF13; // Channel 1 frequency low
    private const NR14 = 0xFF14; // Channel 1 frequency high/control

    private const NR21 = 0xFF16; // Channel 2 sound length/wave pattern
    private const NR22 = 0xFF17; // Channel 2 volume envelope
    private const NR23 = 0xFF18; // Channel 2 frequency low
    private const NR24 = 0xFF19; // Channel 2 frequency high/control

    private const NR30 = 0xFF1A; // Channel 3 DAC enable
    private const NR31 = 0xFF1B; // Channel 3 sound length
    private const NR32 = 0xFF1C; // Channel 3 output level
    private const NR33 = 0xFF1D; // Channel 3 frequency low
    private const NR34 = 0xFF1E; // Channel 3 frequency high/control

    private const NR41 = 0xFF20; // Channel 4 sound length
    private const NR42 = 0xFF21; // Channel 4 volume envelope
    private const NR43 = 0xFF22; // Channel 4 polynomial counter
    private const NR44 = 0xFF23; // Channel 4 counter/control

    private const NR50 = 0xFF24; // Master volume & VIN panning
    private const NR51 = 0xFF25; // Sound panning
    private const NR52 = 0xFF26; // Sound on/off

    private const WAVE_RAM_START = 0xFF30;
    private const WAVE_RAM_END = 0xFF3F;

    private Channel1 $channel1;
    private Channel2 $channel2;
    private Channel3 $channel3;
    private Channel4 $channel4;

    // Master control
    private bool $enabled = false;

    // NR50: Master volume
    private int $leftVolume = 0;  // Bits 6-4
    private int $rightVolume = 0; // Bits 2-0
    private bool $vinLeft = false;  // Bit 7
    private bool $vinRight = false; // Bit 3

    // NR51: Panning
    private int $panning = 0; // 8-bit register for channel panning

    // Frame sequencer
    private int $frameSequencerCycles = 0;
    private int $frameSequencerStep = 0;

    // Sample generation
    private float $sampleCycles = 0.0;

    public function __construct(
        private readonly AudioSinkInterface $audioSink,
    ) {
        $this->channel1 = new Channel1();
        $this->channel2 = new Channel2();
        $this->channel3 = new Channel3();
        $this->channel4 = new Channel4();
    }

    /**
     * Advance the APU by the given number of M-cycles.
     */
    public function step(int $cycles): void
    {
        if (!$this->enabled) {
            return;
        }

        // Step each channel
        for ($i = 0; $i < $cycles; $i++) {
            $this->channel1->step();
            $this->channel2->step();
            $this->channel3->step();
            $this->channel4->step();

            // Clock frame sequencer
            $this->frameSequencerCycles++;
            if ($this->frameSequencerCycles >= self::FRAME_SEQUENCER_RATE) {
                $this->frameSequencerCycles = 0;
                $this->clockFrameSequencer();
            }

            // Generate samples
            $this->sampleCycles += 1.0;
            if ($this->sampleCycles >= self::CYCLES_PER_SAMPLE) {
                $this->sampleCycles -= self::CYCLES_PER_SAMPLE;
                $this->generateSample();
            }
        }
    }

    /**
     * Clock the frame sequencer (called every 8192 M-cycles / 512 Hz).
     */
    private function clockFrameSequencer(): void
    {
        // Length counter: steps 0, 2, 4, 6 (256 Hz)
        if (($this->frameSequencerStep & 0x01) === 0) {
            $this->channel1->clockLength();
            $this->channel2->clockLength();
            $this->channel3->clockLength();
            $this->channel4->clockLength();
        }

        // Sweep: steps 2, 6 (128 Hz)
        if ($this->frameSequencerStep === 2 || $this->frameSequencerStep === 6) {
            $this->channel1->clockSweep();
        }

        // Envelope: step 7 (64 Hz)
        if ($this->frameSequencerStep === 7) {
            $this->channel1->clockEnvelope();
            $this->channel2->clockEnvelope();
            $this->channel4->clockEnvelope();
        }

        $this->frameSequencerStep = ($this->frameSequencerStep + 1) & 0x07;
    }

    /**
     * Generate and push a stereo sample to the audio sink.
     */
    private function generateSample(): void
    {
        // Get samples from each channel
        $ch1 = $this->channel1->getSample();
        $ch2 = $this->channel2->getSample();
        $ch3 = $this->channel3->getSample();
        $ch4 = $this->channel4->getSample();

        // Mix left channel based on panning (NR51 bits 4-7)
        $left = 0.0;
        if ($this->panning & 0x10) {
            $left += $ch1;
        }
        if ($this->panning & 0x20) {
            $left += $ch2;
        }
        if ($this->panning & 0x40) {
            $left += $ch3;
        }
        if ($this->panning & 0x80) {
            $left += $ch4;
        }

        // Mix right channel based on panning (NR51 bits 0-3)
        $right = 0.0;
        if ($this->panning & 0x01) {
            $right += $ch1;
        }
        if ($this->panning & 0x02) {
            $right += $ch2;
        }
        if ($this->panning & 0x04) {
            $right += $ch3;
        }
        if ($this->panning & 0x08) {
            $right += $ch4;
        }

        // Apply master volume (NR50)
        $left *= ($this->leftVolume + 1) / 8.0;
        $right *= ($this->rightVolume + 1) / 8.0;

        // Normalize (average of up to 4 channels)
        $left /= 4.0;
        $right /= 4.0;

        // Push to audio sink
        $this->audioSink->pushSample($left, $right);
    }

    /**
     * Get mixed audio sample (for testing/debugging).
     */
    public function getSample(): float
    {
        $ch1 = $this->channel1->getSample();
        $ch2 = $this->channel2->getSample();
        $ch3 = $this->channel3->getSample();
        $ch4 = $this->channel4->getSample();

        return ($ch1 + $ch2 + $ch3 + $ch4) / 4.0;
    }

    public function readByte(int $address): int
    {
        return match ($address) {
            self::NR10 => $this->channel1->readNR10(),
            self::NR11 => $this->channel1->readNR11(),
            self::NR12 => $this->channel1->readNR12(),
            self::NR13 => $this->channel1->readNR13(),
            self::NR14 => $this->channel1->readNR14(),

            self::NR21 => $this->channel2->readNR21(),
            self::NR22 => $this->channel2->readNR22(),
            self::NR23 => $this->channel2->readNR23(),
            self::NR24 => $this->channel2->readNR24(),

            self::NR30 => $this->channel3->readNR30(),
            self::NR31 => $this->channel3->readNR31(),
            self::NR32 => $this->channel3->readNR32(),
            self::NR33 => $this->channel3->readNR33(),
            self::NR34 => $this->channel3->readNR34(),

            self::NR41 => $this->channel4->readNR41(),
            self::NR42 => $this->channel4->readNR42(),
            self::NR43 => $this->channel4->readNR43(),
            self::NR44 => $this->channel4->readNR44(),

            self::NR50 => $this->readNR50(),
            self::NR51 => $this->panning,
            self::NR52 => $this->readNR52(),

            default => match (true) {
                $address >= self::WAVE_RAM_START && $address <= self::WAVE_RAM_END
                    => $this->channel3->readWaveRam($address - self::WAVE_RAM_START),
                default => 0xFF,
            },
        };
    }

    public function writeByte(int $address, int $value): void
    {
        // If APU is disabled, only NR52 can be written
        if (!$this->enabled && $address !== self::NR52) {
            // Wave RAM can still be accessed when APU is off
            if ($address >= self::WAVE_RAM_START && $address <= self::WAVE_RAM_END) {
                $this->channel3->writeWaveRam($address - self::WAVE_RAM_START, $value);
            }
            return;
        }

        match ($address) {
            self::NR10 => $this->channel1->writeNR10($value),
            self::NR11 => $this->channel1->writeNR11($value),
            self::NR12 => $this->channel1->writeNR12($value),
            self::NR13 => $this->channel1->writeNR13($value),
            self::NR14 => $this->channel1->writeNR14($value),

            self::NR21 => $this->channel2->writeNR21($value),
            self::NR22 => $this->channel2->writeNR22($value),
            self::NR23 => $this->channel2->writeNR23($value),
            self::NR24 => $this->channel2->writeNR24($value),

            self::NR30 => $this->channel3->writeNR30($value),
            self::NR31 => $this->channel3->writeNR31($value),
            self::NR32 => $this->channel3->writeNR32($value),
            self::NR33 => $this->channel3->writeNR33($value),
            self::NR34 => $this->channel3->writeNR34($value),

            self::NR41 => $this->channel4->writeNR41($value),
            self::NR42 => $this->channel4->writeNR42($value),
            self::NR43 => $this->channel4->writeNR43($value),
            self::NR44 => $this->channel4->writeNR44($value),

            self::NR50 => $this->writeNR50($value),
            self::NR51 => $this->panning = $value,
            self::NR52 => $this->writeNR52($value),

            default => match (true) {
                $address >= self::WAVE_RAM_START && $address <= self::WAVE_RAM_END
                    => $this->channel3->writeWaveRam($address - self::WAVE_RAM_START, $value),
                default => null,
            },
        };
    }

    /**
     * Read NR50 (0xFF24): Master volume
     */
    private function readNR50(): int
    {
        return ($this->vinLeft ? 0x80 : 0)
            | ($this->leftVolume << 4)
            | ($this->vinRight ? 0x08 : 0)
            | $this->rightVolume;
    }

    /**
     * Write NR50 (0xFF24): Master volume
     */
    private function writeNR50(int $value): void
    {
        $this->vinLeft = (bool) ($value & 0x80);
        $this->leftVolume = ($value >> 4) & 0x07;
        $this->vinRight = (bool) ($value & 0x08);
        $this->rightVolume = $value & 0x07;
    }

    /**
     * Read NR52 (0xFF26): Sound on/off
     */
    private function readNR52(): int
    {
        return 0x70
            | ($this->enabled ? 0x80 : 0)
            | ($this->channel1->isEnabled() ? 0x01 : 0)
            | ($this->channel2->isEnabled() ? 0x02 : 0)
            | ($this->channel3->isEnabled() ? 0x04 : 0)
            | ($this->channel4->isEnabled() ? 0x08 : 0);
    }

    /**
     * Write NR52 (0xFF26): Sound on/off
     */
    private function writeNR52(int $value): void
    {
        $wasEnabled = $this->enabled;
        $this->enabled = (bool) ($value & 0x80);

        // If APU is disabled, clear all registers and disable all channels
        if ($wasEnabled && !$this->enabled) {
            $this->channel1->disable();
            $this->channel2->disable();
            $this->channel3->disable();
            $this->channel4->disable();

            $this->frameSequencerCycles = 0;
            $this->frameSequencerStep = 0;
        }
    }
}
