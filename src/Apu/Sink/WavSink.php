<?php

declare(strict_types=1);

namespace Gb\Apu\Sink;

use Gb\Apu\AudioSinkInterface;

/**
 * WAV File Audio Sink
 *
 * Writes audio samples to a WAV file on disk.
 * Supports 16-bit PCM stereo audio at a configurable sample rate.
 */
final class WavSink implements AudioSinkInterface
{
    private const SAMPLE_RATE_DEFAULT = 44100;
    private const BITS_PER_SAMPLE = 16;
    private const NUM_CHANNELS = 2; // Stereo

    /** @var float[] Buffer of interleaved stereo samples (L, R, L, R, ...) */
    private array $samples = [];

    /**
     * @param string $filePath Path to the output WAV file
     * @param int $sampleRate Sample rate in Hz (default: 44100)
     */
    public function __construct(
        private readonly string $filePath,
        private readonly int $sampleRate = self::SAMPLE_RATE_DEFAULT,
    ) {
    }

    public function pushSample(float $left, float $right): void
    {
        $this->samples[] = $left;
        $this->samples[] = $right;
    }

    public function flush(): void
    {
        if (empty($this->samples)) {
            return;
        }

        $this->writeWavFile();
        $this->samples = [];
    }

    /**
     * Write accumulated samples to a WAV file.
     */
    private function writeWavFile(): void
    {
        $numSamples = (int) (count($this->samples) / 2); // Total stereo frames
        $dataSize = $numSamples * self::NUM_CHANNELS * (self::BITS_PER_SAMPLE / 8);
        $fileSize = 36 + $dataSize;

        $byteRate = $this->sampleRate * self::NUM_CHANNELS * (self::BITS_PER_SAMPLE / 8);
        $blockAlign = self::NUM_CHANNELS * (self::BITS_PER_SAMPLE / 8);

        // Build WAV header
        $header = pack(
            'A4VA4A4VvvVVvvA4V',
            'RIFF',
            $fileSize,
            'WAVE',
            'fmt ',
            16, // fmt chunk size
            1,  // PCM format
            self::NUM_CHANNELS,
            $this->sampleRate,
            $byteRate,
            $blockAlign,
            self::BITS_PER_SAMPLE,
            'data',
            $dataSize
        );

        // Convert float samples to 16-bit PCM
        $pcmData = '';
        foreach ($this->samples as $sample) {
            // Clamp to [-1.0, 1.0] and convert to 16-bit signed integer
            $clamped = max(-1.0, min(1.0, $sample));
            $pcm16 = (int) ($clamped * 32767);
            $pcmData .= pack('v', $pcm16 & 0xFFFF);
        }

        // Write to file
        file_put_contents($this->filePath, $header . $pcmData);
    }
}
