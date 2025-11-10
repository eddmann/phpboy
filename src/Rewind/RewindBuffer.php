<?php

declare(strict_types=1);

namespace Gb\Rewind;

use Gb\Emulator;
use Gb\Savestate\SavestateManager;

/**
 * Rewind Buffer
 *
 * Maintains a circular buffer of savestates to support rewinding gameplay.
 * Stores one savestate per second (configurable) for the last N seconds.
 *
 * Usage:
 *   $buffer = new RewindBuffer($emulator, maxSeconds: 60);
 *   // During gameplay:
 *   $buffer->recordFrame(); // Call once per frame
 *   // To rewind:
 *   $buffer->rewind(10); // Rewind 10 seconds
 */
final class RewindBuffer
{
    private const FRAMES_PER_SECOND = 60; // Game Boy runs at ~59.7 FPS, rounded to 60

    private SavestateManager $savestateManager;

    /** @var array<int, array<string, mixed>> Circular buffer of savestates */
    private array $buffer = [];

    /** @var int Maximum number of savestates to keep */
    private int $maxStates;

    /** @var int Current write position in circular buffer */
    private int $writePos = 0;

    /** @var int Frame counter for recording intervals */
    private int $frameCounter = 0;

    /** @var int Frames per savestate (e.g., 60 frames = 1 second) */
    private int $framesPerSavestate;

    /**
     * @param Emulator $emulator Emulator instance
     * @param int $maxSeconds Maximum seconds of history to keep (default: 60)
     * @param int $framesPerSavestate Frames between savestates (default: 60 = 1 second)
     */
    public function __construct(
        Emulator $emulator,
        int $maxSeconds = 60,
        int $framesPerSavestate = self::FRAMES_PER_SECOND
    ) {
        $this->savestateManager = new SavestateManager($emulator);
        $this->maxStates = $maxSeconds;
        $this->framesPerSavestate = $framesPerSavestate;
    }

    /**
     * Record a frame. Call this once per emulated frame.
     *
     * Automatically creates a savestate at the configured interval.
     */
    public function recordFrame(): void
    {
        $this->frameCounter++;

        // Record a savestate every N frames
        if ($this->frameCounter >= $this->framesPerSavestate) {
            $this->recordSavestate();
            $this->frameCounter = 0;
        }
    }

    /**
     * Record a savestate to the buffer.
     */
    private function recordSavestate(): void
    {
        // Serialize the current state
        $state = $this->savestateManager->serialize();

        // Add timestamp for debugging
        $state['rewind_timestamp'] = microtime(true);

        // Store in circular buffer
        $this->buffer[$this->writePos] = $state;
        $this->writePos = ($this->writePos + 1) % $this->maxStates;
    }

    /**
     * Rewind gameplay by N seconds.
     *
     * @param int $seconds Number of seconds to rewind (1-maxSeconds)
     * @throws \RuntimeException If cannot rewind (insufficient history)
     */
    public function rewind(int $seconds): void
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException("Rewind seconds must be positive");
        }

        if ($seconds > $this->maxStates) {
            $seconds = $this->maxStates;
        }

        // Find the state from N seconds ago
        $stateIndex = $this->getStateIndex($seconds);

        if (!isset($this->buffer[$stateIndex])) {
            throw new \RuntimeException(
                "Insufficient rewind history: only " . $this->getAvailableSeconds() . " seconds available"
            );
        }

        // Restore the state
        $state = $this->buffer[$stateIndex];
        $this->savestateManager->deserialize($state);

        // Clear buffer states after the restored point
        // This prevents "undoing" the rewind
        $this->clearStatesAfter($stateIndex);
    }

    /**
     * Get the buffer index for a state N seconds ago.
     *
     * @param int $seconds Seconds ago
     * @return int Buffer index
     */
    private function getStateIndex(int $seconds): int
    {
        // Calculate how many states back we need to go
        $statesBack = $seconds;

        // Current write position points to the next slot to write
        // So the most recent state is at writePos - 1
        $index = $this->writePos - 1 - $statesBack;

        // Handle wrap-around
        if ($index < 0) {
            $index += $this->maxStates;
        }

        return $index;
    }

    /**
     * Clear states after a given index (when rewinding).
     *
     * @param int $index Index to keep and earlier
     */
    private function clearStatesAfter(int $index): void
    {
        // Set write position to one past the restored state
        $this->writePos = ($index + 1) % $this->maxStates;

        // Clear all states after this point
        for ($i = $this->writePos; $i < $this->maxStates; $i++) {
            unset($this->buffer[$i]);
        }
    }

    /**
     * Get the number of seconds of history available.
     *
     * @return int Seconds of rewind history available
     */
    public function getAvailableSeconds(): int
    {
        return count($this->buffer);
    }

    /**
     * Clear the rewind buffer.
     */
    public function clear(): void
    {
        $this->buffer = [];
        $this->writePos = 0;
        $this->frameCounter = 0;
    }

    /**
     * Check if rewind is available.
     *
     * @return bool True if at least one savestate is available
     */
    public function isAvailable(): bool
    {
        return count($this->buffer) > 0;
    }
}
