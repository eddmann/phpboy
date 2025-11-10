<?php

declare(strict_types=1);

namespace Gb\Tas;

use Gb\Input\Button;

/**
 * Input Recorder for Tool-Assisted Speedrun (TAS) support
 *
 * Records and plays back input sequences frame-by-frame for deterministic replay.
 *
 * Format: JSON with frame-by-frame button states
 * Example:
 * {
 *   "version": "1.0",
 *   "frames": 1000,
 *   "inputs": [
 *     {"frame": 0, "buttons": ["A", "Start"]},
 *     {"frame": 10, "buttons": ["Right"]},
 *     {"frame": 20, "buttons": []}
 *   ]
 * }
 *
 * Usage:
 *   $recorder = new InputRecorder();
 *   $recorder->startRecording();
 *   // Each frame:
 *   $recorder->recordFrame($pressedButtons);
 *   // Save:
 *   $recorder->saveRecording('/path/to/recording.json');
 *
 *   // Playback:
 *   $recorder->loadRecording('/path/to/recording.json');
 *   $buttons = $recorder->getInputsForFrame($frameNumber);
 */
final class InputRecorder
{
    private const VERSION = '1.0';

    /** @var array<int, array<string>> Frame-by-frame input log */
    private array $inputs = [];

    /** @var bool Whether recording is active */
    private bool $recording = false;

    /** @var int Current frame number */
    private int $currentFrame = 0;

    /** @var array<string> Previous frame's button state for change detection */
    private array $previousButtons = [];

    /** @var bool Whether playback is active */
    private bool $playing = false;

    /** @var array<int, array<string>>|null Loaded playback inputs */
    private ?array $playbackInputs = null;

    /** @var int Playback frame counter */
    private int $playbackFrame = 0;

    /** @var int Total frames in loaded recording */
    private int $totalFrames = 0;

    /** @var array<string> Current button state during playback */
    private array $currentPlaybackButtons = [];

    /**
     * Start recording inputs.
     */
    public function startRecording(): void
    {
        $this->recording = true;
        $this->inputs = [];
        $this->currentFrame = 0;
        $this->previousButtons = [];
    }

    /**
     * Stop recording inputs.
     */
    public function stopRecording(): void
    {
        $this->recording = false;
    }

    /**
     * Check if currently recording.
     */
    public function isRecording(): bool
    {
        return $this->recording;
    }

    /**
     * Record inputs for the current frame.
     *
     * @param array<Button> $pressedButtons Buttons pressed this frame
     */
    public function recordFrame(array $pressedButtons): void
    {
        if (!$this->recording) {
            return;
        }

        // Convert Button enums to strings and sort for consistent comparison
        $buttonNames = array_map(fn(Button $btn) => $btn->name, $pressedButtons);
        sort($buttonNames);

        // Only record frames with input changes to save space
        // Record frame 0 or when button state changes
        if ($this->currentFrame === 0 || $buttonNames !== $this->previousButtons) {
            $this->inputs[$this->currentFrame] = $buttonNames;
            $this->previousButtons = $buttonNames;
        }

        $this->currentFrame++;
    }

    /**
     * Save the current recording to a JSON file.
     *
     * @param string $path Path to save the recording
     * @throws \RuntimeException If recording cannot be saved
     */
    public function saveRecording(string $path): void
    {
        if ($this->recording) {
            throw new \RuntimeException("Cannot save while recording is active. Stop recording first.");
        }

        if (empty($this->inputs)) {
            throw new \RuntimeException("No input data to save");
        }

        // Convert inputs array to compact format
        $compactInputs = [];
        foreach ($this->inputs as $frame => $buttons) {
            $compactInputs[] = [
                'frame' => $frame,
                'buttons' => $buttons,
            ];
        }

        $data = [
            'version' => self::VERSION,
            'frames' => $this->currentFrame,
            'inputs' => $compactInputs,
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        if (file_put_contents($path, $json) === false) {
            throw new \RuntimeException("Failed to save recording to: {$path}");
        }
    }

    /**
     * Load a recording from a JSON file for playback.
     *
     * @param string $path Path to the recording file
     * @throws \RuntimeException If recording cannot be loaded
     */
    public function loadRecording(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Recording file not found: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Failed to read recording file: {$path}");
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Invalid recording format: " . $e->getMessage());
        }

        if (!is_array($data)) {
            throw new \RuntimeException("Invalid recording format: expected array");
        }

        if (!isset($data['version']) || $data['version'] !== self::VERSION) {
            throw new \RuntimeException("Incompatible recording version");
        }

        if (!isset($data['inputs']) || !is_array($data['inputs'])) {
            throw new \RuntimeException("Invalid recording format: missing or invalid inputs");
        }

        if (!isset($data['frames']) || !is_int($data['frames'])) {
            throw new \RuntimeException("Invalid recording format: missing or invalid frames count");
        }

        // Convert compact format back to frame-indexed array
        $this->playbackInputs = [];
        foreach ($data['inputs'] as $input) {
            if (!is_array($input) || !isset($input['frame']) || !isset($input['buttons'])) {
                continue;
            }
            if (!is_int($input['frame']) || !is_array($input['buttons'])) {
                continue;
            }
            $this->playbackInputs[$input['frame']] = $input['buttons'];
        }

        $this->totalFrames = $data['frames'];
        $this->playbackFrame = 0;
    }

    /**
     * Start playback of a loaded recording.
     *
     * @throws \RuntimeException If no recording is loaded
     */
    public function startPlayback(): void
    {
        if ($this->playbackInputs === null) {
            throw new \RuntimeException("No recording loaded for playback");
        }

        $this->playing = true;
        $this->playbackFrame = 0;
        $this->currentPlaybackButtons = [];
    }

    /**
     * Stop playback.
     */
    public function stopPlayback(): void
    {
        $this->playing = false;
    }

    /**
     * Check if currently playing back.
     */
    public function isPlaying(): bool
    {
        return $this->playing;
    }

    /**
     * Get the inputs for the current playback frame.
     *
     * @return array<Button> Buttons that should be pressed this frame
     */
    public function getPlaybackInputs(): array
    {
        if (!$this->playing || $this->playbackInputs === null) {
            return [];
        }

        // Check if playback has finished
        if ($this->playbackFrame >= $this->totalFrames) {
            $this->stopPlayback();
            return [];
        }

        // Check if there's a state change for this frame
        if (isset($this->playbackInputs[$this->playbackFrame])) {
            // Update current button state
            $this->currentPlaybackButtons = $this->playbackInputs[$this->playbackFrame];
        }

        // Convert button names back to Button enums
        $buttons = array_map(
            fn(string $name) => Button::fromName($name),
            $this->currentPlaybackButtons
        );

        $this->playbackFrame++;

        return $buttons;
    }

    /**
     * Get inputs for a specific frame number.
     *
     * @param int $frameNumber Frame number to query
     * @return array<string> Button names for that frame
     */
    public function getInputsForFrame(int $frameNumber): array
    {
        return $this->playbackInputs[$frameNumber] ?? [];
    }

    /**
     * Get the current playback frame number.
     */
    public function getPlaybackFrame(): int
    {
        return $this->playbackFrame;
    }

    /**
     * Get the total number of frames in the loaded recording.
     */
    public function getTotalFrames(): int
    {
        return $this->totalFrames;
    }

    /**
     * Check if playback has finished.
     */
    public function isPlaybackFinished(): bool
    {
        return $this->playing && $this->playbackFrame >= $this->totalFrames;
    }
}
