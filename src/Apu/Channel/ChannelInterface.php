<?php

declare(strict_types=1);

namespace Gb\Apu\Channel;

/**
 * Audio Channel Interface
 *
 * Defines the contract for all APU channels (square waves, wave, noise).
 */
interface ChannelInterface
{
    /**
     * Get the current output sample from this channel.
     *
     * @return float Sample value (-1.0 to 1.0)
     */
    public function getSample(): float;

    /**
     * Advance the channel by one M-cycle (4 T-cycles).
     */
    public function step(): void;

    /**
     * Clock the length counter (called by frame sequencer at 256 Hz).
     */
    public function clockLength(): void;

    /**
     * Clock the volume envelope (called by frame sequencer at 64 Hz).
     */
    public function clockEnvelope(): void;

    /**
     * Check if the channel is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Disable the channel.
     */
    public function disable(): void;
}
