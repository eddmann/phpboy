<?php

declare(strict_types=1);

namespace Gb\Frontend\Wasm;

use Gb\Apu\AudioSinkInterface;

/**
 * WebAssembly audio sink implementation for PHPBoy in the browser.
 *
 * Buffers audio samples in memory so JavaScript can retrieve them
 * and queue them to the Web Audio API.
 *
 * The Game Boy APU produces stereo samples at ~32768 Hz.
 * This sink accumulates samples in a ring buffer and provides
 * methods for JavaScript to consume them.
 *
 * Integration with JavaScript:
 * ```javascript
 * // Get audio samples from PHP
 * const samples = phpInstance.getAudioSamples();
 * // samples is array: [[left, right], [left, right], ...]
 *
 * // Queue to Web Audio API
 * audioWorklet.port.postMessage({ samples });
 * ```
 */
final class WasmAudioSink implements AudioSinkInterface
{
    /**
     * Maximum buffer size (number of stereo sample pairs)
     * At 32768 Hz, this is ~0.5 seconds of audio
     */
    private const MAX_BUFFER_SIZE = 16384;

    /**
     * Audio sample buffer: [[left, right], [left, right], ...]
     * @var array<int, array{float, float}>
     */
    private array $buffer = [];

    /**
     * Total samples pushed (for statistics)
     */
    private int $totalSamples = 0;

    /**
     * Push a stereo audio sample to the buffer.
     *
     * @param float $left Left channel sample (-1.0 to 1.0)
     * @param float $right Right channel sample (-1.0 to 1.0)
     */
    public function pushSample(float $left, float $right): void
    {
        // Clamp samples to valid range
        $left = max(-1.0, min(1.0, $left));
        $right = max(-1.0, min(1.0, $right));

        // Add to buffer
        $this->buffer[] = [$left, $right];
        $this->totalSamples++;

        // Prevent buffer overflow by dropping oldest samples if needed
        if (count($this->buffer) > self::MAX_BUFFER_SIZE) {
            array_shift($this->buffer);
        }
    }

    /**
     * Flush buffered audio data.
     * For WASM, this is a no-op since JavaScript polls the buffer.
     */
    public function flush(): void
    {
        // No-op for WASM implementation
        // JavaScript will poll getSamples() to retrieve data
    }

    /**
     * Get all buffered audio samples and clear the buffer.
     *
     * This method should be called from JavaScript after each frame
     * to retrieve audio samples for playback via Web Audio API.
     *
     * @return array<int, array{float, float}> Array of stereo samples [[L,R], [L,R], ...]
     */
    public function getSamples(): array
    {
        $samples = $this->buffer;
        $this->buffer = []; // Clear buffer after retrieval
        return $samples;
    }

    /**
     * Get flattened audio samples as a single array.
     *
     * Returns samples in the format: [L, R, L, R, L, R, ...]
     * This format is more convenient for some Web Audio APIs.
     *
     * @return float[] Flat array of interleaved stereo samples
     */
    public function getSamplesFlat(): array
    {
        $flat = [];

        foreach ($this->buffer as [$left, $right]) {
            $flat[] = $left;
            $flat[] = $right;
        }

        $this->buffer = []; // Clear buffer after retrieval
        return $flat;
    }

    /**
     * Get the number of buffered samples.
     */
    public function getBufferSize(): int
    {
        return count($this->buffer);
    }

    /**
     * Clear the audio buffer.
     */
    public function clear(): void
    {
        $this->buffer = [];
    }

    /**
     * Get total number of samples pushed since creation.
     */
    public function getTotalSamples(): int
    {
        return $this->totalSamples;
    }
}
