<?php

declare(strict_types=1);

namespace Gb\Apu;

/**
 * Audio Sink Interface
 *
 * Defines the contract for audio output destinations.
 * Implementations can write to files (WAV), memory buffers (for WASM), or discard audio (NullSink).
 */
interface AudioSinkInterface
{
    /**
     * Push a stereo audio sample to the sink.
     *
     * @param float $left Left channel sample (-1.0 to 1.0)
     * @param float $right Right channel sample (-1.0 to 1.0)
     */
    public function pushSample(float $left, float $right): void;

    /**
     * Flush any buffered audio data.
     * Called when audio stream is complete or needs to be written to disk.
     */
    public function flush(): void;
}
