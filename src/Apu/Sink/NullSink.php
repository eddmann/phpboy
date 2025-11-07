<?php

declare(strict_types=1);

namespace Gb\Apu\Sink;

use Gb\Apu\AudioSinkInterface;

/**
 * Null Audio Sink
 *
 * Discards all audio samples. Useful for headless testing or running the emulator
 * without audio output.
 */
final class NullSink implements AudioSinkInterface
{
    public function pushSample(float $left, float $right): void
    {
        // Intentionally empty - discard all samples
    }

    public function flush(): void
    {
        // Nothing to flush
    }
}
