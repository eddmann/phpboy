<?php

declare(strict_types=1);

namespace Gb\Frontend\Sdl;

use Gb\Apu\AudioSinkInterface;
use SDL;

/**
 * SDL2 audio sink implementation for real-time audio playback.
 *
 * This sink uses SDL2's audio subsystem to queue audio samples
 * directly to the sound card with low latency.
 *
 * Features:
 * - Hardware-accelerated audio playback
 * - Low latency buffering
 * - Automatic sample rate conversion
 * - Stereo output
 *
 * The Game Boy APU produces stereo samples at a configurable rate
 * (typically 44100 Hz or 48000 Hz). This sink queues samples to
 * SDL2's audio device for immediate playback.
 */
final class SdlAudioSink implements AudioSinkInterface
{
    /**
     * SDL audio device ID (null if unavailable)
     */
    private ?int $audioDevice = null;

    /**
     * Sample rate (Hz)
     */
    private int $sampleRate;

    /**
     * Audio sample buffer (interleaved stereo: L, R, L, R, ...)
     * @var array<float>
     */
    private array $buffer = [];

    /**
     * Target buffer size before flushing (in sample pairs)
     * Smaller = lower latency, larger = more stable
     */
    private int $bufferSize = 512;

    /**
     * Total samples pushed (for statistics)
     */
    private int $totalSamples = 0;

    /**
     * Number of samples dropped due to buffer overflow
     */
    private int $droppedSamples = 0;

    /**
     * Maximum buffer size to prevent memory overflow
     */
    private const MAX_BUFFER_SIZE = 8192;

    /**
     * Initialize SDL2 audio sink.
     *
     * @param int $sampleRate Sample rate in Hz (default: 44100)
     */
    public function __construct(int $sampleRate = 44100)
    {
        $this->sampleRate = $sampleRate;
        $this->openAudioDevice();
    }

    /**
     * Clean up audio device on destruction.
     */
    public function __destruct()
    {
        $this->closeAudioDevice();
    }

    /**
     * Push a stereo audio sample to the buffer.
     *
     * Samples are buffered and flushed periodically to reduce overhead.
     *
     * @param float $left Left channel sample (-1.0 to 1.0)
     * @param float $right Right channel sample (-1.0 to 1.0)
     */
    public function pushSample(float $left, float $right): void
    {
        if ($this->audioDevice === null) {
            return;
        }

        // Clamp samples to valid range [-1.0, 1.0]
        $left = max(-1.0, min(1.0, $left));
        $right = max(-1.0, min(1.0, $right));

        // Add to interleaved buffer
        $this->buffer[] = $left;
        $this->buffer[] = $right;
        $this->totalSamples++;

        // Prevent buffer overflow by dropping oldest samples
        $maxBufferElements = self::MAX_BUFFER_SIZE * 2; // *2 for stereo
        if (count($this->buffer) > $maxBufferElements) {
            // Drop oldest samples
            $toDrop = count($this->buffer) - $maxBufferElements;
            $this->buffer = array_slice($this->buffer, $toDrop);
            $this->droppedSamples += (int)($toDrop / 2); // Count sample pairs
        }

        // Auto-flush when buffer reaches target size
        if (count($this->buffer) >= $this->bufferSize * 2) {
            $this->flush();
        }
    }

    /**
     * Flush buffered audio data to SDL2 audio device.
     *
     * Converts float samples to 16-bit signed integers and queues
     * them to the SDL2 audio device for playback.
     */
    public function flush(): void
    {
        if ($this->audioDevice === null || count($this->buffer) === 0) {
            return;
        }

        // Convert float samples to 16-bit signed integers
        $int16Samples = [];
        foreach ($this->buffer as $sample) {
            // Convert [-1.0, 1.0] to [-32768, 32767]
            $int16 = (int)($sample * 32767.0);
            $int16 = max(-32768, min(32767, $int16));
            $int16Samples[] = $int16;
        }

        // Pack as little-endian 16-bit signed integers
        $audioData = pack('s*', ...$int16Samples);

        // Queue audio to SDL2 device
        // Note: SDL_QueueAudio may not be available in all PHP-SDL bindings
        // We'll use a compatibility approach
        if (function_exists('SDL_QueueAudio')) {
            SDL_QueueAudio($this->audioDevice, $audioData);
        } elseif (method_exists('SDL', 'QueueAudio')) {
            SDL::QueueAudio($this->audioDevice, $audioData);
        } else {
            // Fallback: try direct function call if available
            @sdl_queue_audio($this->audioDevice, $audioData);
        }

        // Clear buffer after queuing
        $this->buffer = [];
    }

    /**
     * Check if SDL2 audio is available and working.
     *
     * @return bool True if audio device is open and ready
     */
    public function isAvailable(): bool
    {
        return $this->audioDevice !== null;
    }

    /**
     * Get the sample rate.
     *
     * @return int Sample rate in Hz
     */
    public function getSampleRate(): int
    {
        return $this->sampleRate;
    }

    /**
     * Set buffer size (in sample pairs).
     *
     * Smaller values = lower latency but more CPU overhead.
     * Larger values = higher latency but more stable.
     *
     * @param int $size Buffer size (128-4096 recommended)
     */
    public function setBufferSize(int $size): void
    {
        $this->bufferSize = max(128, min(self::MAX_BUFFER_SIZE, $size));
    }

    /**
     * Get current buffer size.
     *
     * @return int Buffer size in sample pairs
     */
    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    /**
     * Get number of dropped samples due to overflow.
     *
     * @return int Number of sample pairs dropped
     */
    public function getDroppedSamples(): int
    {
        return $this->droppedSamples;
    }

    /**
     * Get total samples processed.
     *
     * @return int Total number of sample pairs pushed
     */
    public function getTotalSamples(): int
    {
        return $this->totalSamples;
    }

    /**
     * Get the number of buffered samples waiting to be flushed.
     *
     * @return int Number of sample pairs in buffer
     */
    public function getBufferedSamples(): int
    {
        return (int)(count($this->buffer) / 2);
    }

    /**
     * Open the SDL2 audio device.
     *
     * Initializes SDL audio subsystem and opens an audio device
     * with the specified sample rate and stereo output.
     */
    private function openAudioDevice(): void
    {
        // Check if SDL extension is loaded
        if (!extension_loaded('sdl')) {
            error_log('SDL2 Audio: PHP SDL extension not loaded');
            error_log('Install SDL extension: pecl install sdl-beta');
            return;
        }

        // Initialize SDL audio subsystem
        try {
            // SDL_INIT_AUDIO = 0x00000010
            $initResult = SDL_Init(SDL_INIT_AUDIO);
            if ($initResult !== 0) {
                error_log('SDL2 Audio: Failed to initialize SDL audio subsystem');
                error_log('SDL Error: ' . SDL_GetError());
                return;
            }
        } catch (\Throwable $e) {
            error_log('SDL2 Audio: Exception during SDL_Init: ' . $e->getMessage());
            return;
        }

        // Configure audio specification
        $desiredSpec = [
            'freq' => $this->sampleRate,      // Sample rate
            'format' => AUDIO_S16LSB,          // 16-bit signed little-endian
            'channels' => 2,                   // Stereo
            'samples' => 2048,                 // Buffer size (power of 2)
        ];

        // Open audio device
        try {
            // Try SDL_OpenAudioDevice (SDL 2.0+)
            if (function_exists('SDL_OpenAudioDevice')) {
                $deviceId = SDL_OpenAudioDevice(
                    null,           // Default device
                    0,              // Not capture (playback)
                    $desiredSpec,
                    null,           // No obtained spec needed
                    0               // No allowed changes
                );

                if ($deviceId === 0 || $deviceId === false) {
                    error_log('SDL2 Audio: Failed to open audio device');
                    error_log('SDL Error: ' . SDL_GetError());
                    return;
                }

                $this->audioDevice = (int)$deviceId;

                // Unpause audio device to start playback
                SDL_PauseAudioDevice($this->audioDevice, 0);
            } else {
                error_log('SDL2 Audio: SDL_OpenAudioDevice not available in this SDL binding');
                error_log('Audio playback will not work');
                return;
            }
        } catch (\Throwable $e) {
            error_log('SDL2 Audio: Exception opening audio device: ' . $e->getMessage());
            return;
        }
    }

    /**
     * Close the SDL2 audio device and clean up.
     */
    private function closeAudioDevice(): void
    {
        if ($this->audioDevice === null) {
            return;
        }

        // Flush any remaining samples
        $this->flush();

        // Close audio device
        try {
            if (function_exists('SDL_CloseAudioDevice')) {
                SDL_CloseAudioDevice($this->audioDevice);
            }
        } catch (\Throwable $e) {
            // Ignore errors during cleanup
        }

        $this->audioDevice = null;

        // Quit SDL audio subsystem
        try {
            SDL_QuitSubSystem(SDL_INIT_AUDIO);
        } catch (\Throwable $e) {
            // Ignore errors during cleanup
        }
    }
}
