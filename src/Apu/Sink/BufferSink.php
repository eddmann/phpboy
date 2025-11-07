<?php

declare(strict_types=1);

namespace Gb\Apu\Sink;

use Gb\Apu\AudioSinkInterface;

/**
 * Buffer Audio Sink
 *
 * Stores audio samples in memory buffers for later retrieval.
 * Useful for WebAssembly bridging where audio needs to be passed to JavaScript's AudioWorklet.
 */
final class BufferSink implements AudioSinkInterface
{
    /** @var float[] Left channel samples */
    private array $leftBuffer = [];

    /** @var float[] Right channel samples */
    private array $rightBuffer = [];

    public function pushSample(float $left, float $right): void
    {
        $this->leftBuffer[] = $left;
        $this->rightBuffer[] = $right;
    }

    public function flush(): void
    {
        // Buffers stay in memory for retrieval
    }

    /**
     * Get all left channel samples.
     *
     * @return float[]
     */
    public function getLeftBuffer(): array
    {
        return $this->leftBuffer;
    }

    /**
     * Get all right channel samples.
     *
     * @return float[]
     */
    public function getRightBuffer(): array
    {
        return $this->rightBuffer;
    }

    /**
     * Clear both audio buffers.
     */
    public function clear(): void
    {
        $this->leftBuffer = [];
        $this->rightBuffer = [];
    }

    /**
     * Get the number of samples in the buffer.
     */
    public function getSampleCount(): int
    {
        return count($this->leftBuffer);
    }
}
