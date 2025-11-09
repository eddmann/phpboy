<?php

declare(strict_types=1);

namespace Gb\Apu\Sink;

use Gb\Apu\AudioSinkInterface;

/**
 * Pipe Audio Sink - Real-time audio playback via external player
 *
 * Streams audio samples to an external audio player (aplay, ffplay, paplay)
 * for real-time audio output in CLI mode.
 *
 * Supports multiple audio backends:
 * - aplay (ALSA - Linux)
 * - ffplay (FFmpeg - cross-platform)
 * - paplay (PulseAudio - Linux)
 * - afplay (macOS - limited format support)
 *
 * Audio format: 48000 Hz stereo, 32-bit float, little-endian
 */
final class PipeAudioSink implements AudioSinkInterface
{
    /** @var resource|null Pipe to audio player process */
    private $pipe = null;

    /** @var string Name of the audio player being used */
    private string $playerName = 'none';

    /** @var int Sample rate in Hz */
    private int $sampleRate;

    /** @var float[] Buffer for left channel samples */
    private array $leftBuffer = [];

    /** @var float[] Buffer for right channel samples */
    private array $rightBuffer = [];

    /** @var int Number of samples to buffer before flushing */
    private int $bufferSize = 2048;

    /**
     * @param int $sampleRate Sample rate in Hz (default: 48000)
     */
    public function __construct(int $sampleRate = 48000)
    {
        $this->sampleRate = $sampleRate;
        $this->openPipe();
    }

    public function __destruct()
    {
        $this->closePipe();
    }

    public function pushSample(float $left, float $right): void
    {
        if ($this->pipe === null) {
            return; // No audio player available
        }

        $this->leftBuffer[] = $left;
        $this->rightBuffer[] = $right;

        // Flush when buffer is full
        if (count($this->leftBuffer) >= $this->bufferSize) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->pipe === null || count($this->leftBuffer) === 0) {
            return;
        }

        // Interleave stereo samples: L R L R L R ...
        $interleavedData = '';
        for ($i = 0; $i < count($this->leftBuffer); $i++) {
            // Clamp samples to [-1.0, 1.0]
            $left = max(-1.0, min(1.0, $this->leftBuffer[$i]));
            $right = max(-1.0, min(1.0, $this->rightBuffer[$i]));

            // Pack as 32-bit float, little-endian
            $interleavedData .= pack('ff', $left, $right);
        }

        // Write to pipe
        @fwrite($this->pipe, $interleavedData);

        // Clear buffers
        $this->leftBuffer = [];
        $this->rightBuffer = [];
    }

    /**
     * Get the name of the audio player being used.
     */
    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    /**
     * Check if audio playback is available.
     */
    public function isAvailable(): bool
    {
        return $this->pipe !== null;
    }

    /**
     * Open pipe to external audio player.
     */
    private function openPipe(): void
    {
        // Try different audio players in order of preference
        $players = [
            'aplay' => $this->buildAplayCommand(),
            'ffplay' => $this->buildFfplayCommand(),
            'paplay' => $this->buildPaplayCommand(),
            'afplay' => $this->buildAfplayCommand(),
        ];

        foreach ($players as $name => $command) {
            if ($this->tryOpenPlayer($name, $command)) {
                $this->playerName = $name;
                return;
            }
        }

        // No player available
        error_log("Warning: No audio player available (tried: " . implode(', ', array_keys($players)) . ")");
        error_log("Install 'aplay' (ALSA) or 'ffplay' (FFmpeg) for audio playback");
    }

    /**
     * Try to open a specific audio player.
     *
     * @param string $name Player name
     * @param string $command Full command to execute
     * @return bool True if player was successfully opened
     */
    private function tryOpenPlayer(string $name, string $command): bool
    {
        // Check if player exists
        $which = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        $checkCommand = "$which $name 2>/dev/null";
        $result = @shell_exec($checkCommand);

        if (empty($result)) {
            return false; // Player not found
        }

        // Try to open pipe
        $pipe = @popen($command, 'w');

        if ($pipe === false) {
            return false; // Failed to open
        }

        // Set non-blocking mode to prevent deadlocks
        stream_set_blocking($pipe, false);

        $this->pipe = $pipe;
        return true;
    }

    /**
     * Close the audio pipe.
     */
    private function closePipe(): void
    {
        if ($this->pipe === null) {
            return;
        }

        // Flush remaining samples
        $this->flush();

        // Close pipe
        @pclose($this->pipe);
        $this->pipe = null;
    }

    /**
     * Build aplay (ALSA) command.
     */
    private function buildAplayCommand(): string
    {
        return sprintf(
            'aplay -f FLOAT_LE -r %d -c 2 -t raw 2>/dev/null',
            $this->sampleRate
        );
    }

    /**
     * Build ffplay (FFmpeg) command.
     */
    private function buildFfplayCommand(): string
    {
        return sprintf(
            'ffplay -f f32le -ar %d -ac 2 -nodisp -loglevel quiet -i - 2>/dev/null',
            $this->sampleRate
        );
    }

    /**
     * Build paplay (PulseAudio) command.
     */
    private function buildPaplayCommand(): string
    {
        return sprintf(
            'paplay --format=float32le --rate=%d --channels=2 --raw 2>/dev/null',
            $this->sampleRate
        );
    }

    /**
     * Build afplay (macOS) command.
     *
     * Note: afplay doesn't support raw stdin streaming well,
     * so this may not work reliably.
     */
    private function buildAfplayCommand(): string
    {
        // afplay doesn't support stdin streaming for raw audio
        // Return empty to skip this player
        return '';
    }

    /**
     * Set buffer size (number of samples to buffer before flushing).
     *
     * Smaller buffers reduce latency but increase CPU usage.
     * Larger buffers reduce CPU usage but increase latency.
     *
     * @param int $size Buffer size in samples (default: 2048)
     */
    public function setBufferSize(int $size): void
    {
        $this->bufferSize = max(128, $size);
    }
}
