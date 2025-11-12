#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Gb\Emulator;
use Gb\Frontend\Cli\CliInput;
use Gb\Frontend\Cli\CliRenderer;
use Gb\Apu\Sink\WavSink;
use Gb\Apu\Sink\NullSink;
use Gb\Apu\Sink\PipeAudioSink;
use Gb\Debug\Debugger;
use Gb\Debug\Trace;

/**
 * PHPBoy - Game Boy Color Emulator CLI
 *
 * A readable, well-architected Game Boy Color emulator written in PHP.
 *
 * Usage:
 *   php bin/phpboy.php <rom-file> [options]
 *   php bin/phpboy.php --rom=<rom-file> [options]
 *
 * Options:
 *   --debug              Enable debugger mode with interactive shell
 *   --trace              Enable CPU instruction tracing
 *   --headless           Run without display (for testing)
 *   --speed=<factor>     Speed multiplier (1.0 = normal, 2.0 = 2x, etc.)
 *   --save=<path>        Save file location
 *   --audio-out=<path>   WAV file to record audio output
 *   --help               Show this help message
 */

function showHelp(): void
{
    echo <<<HELP
PHPBoy - Game Boy Color Emulator
=================================

Usage:
  php bin/phpboy.php <rom-file> [options]
  php bin/phpboy.php --rom=<rom-file> [options]

Options:
  --rom=<path>           ROM file to load (can also be first positional argument)
  --debug                Enable debugger mode with interactive shell
  --trace                Enable CPU instruction tracing
  --headless             Run without display (for testing)
  --display-mode=<mode>  Display mode: 'ansi-color', 'ascii', 'none' (default: ansi-color)
  --speed=<factor>       Speed multiplier (1.0 = normal, 2.0 = 2x speed, 0.5 = half speed)
  --save=<path>          Save file location (default: <rom>.sav)
  --audio                Enable real-time audio playback (requires aplay/ffplay)
  --audio-out=<path>     WAV file to record audio output
  --palette=<name>       DMG colorization palette (for DMG games on CGB hardware)
                         Options: green, brown, blue, grayscale, pokemon_red, pokemon_blue,
                                  red_yellow, pastel, inverted, or any button combo (e.g., left_b)
  --frames=<n>           Number of frames to run in headless mode (default: 60)
  --benchmark            Enable benchmark mode with FPS measurement (requires --headless)
  --memory-profile       Enable memory profiling (requires --headless)
  --config=<path>        Load configuration from INI file
  --savestate-save=<path>  Save emulator state after running
  --savestate-load=<path>  Load emulator state before running
  --enable-rewind        Enable rewind buffer (60 seconds default)
  --rewind-buffer=<sec>  Rewind buffer size in seconds (default: 60)
  --record=<path>        Record TAS input to JSON file
  --playback=<path>      Playback TAS input from JSON file
  --help                 Show this help message

Examples:
  php bin/phpboy.php tetris.gb
  php bin/phpboy.php --rom=tetris.gb --speed=2.0
  php bin/phpboy.php tetris.gb --display-mode=ansi-color
  php bin/phpboy.php tetris.gb --palette=grayscale
  php bin/phpboy.php pokemon_red.gb --palette=pokemon_red
  php bin/phpboy.php tetris.gb --audio
  php bin/phpboy.php tetris.gb --debug
  php bin/phpboy.php tetris.gb --savestate-load=save.state
  php bin/phpboy.php tetris.gb --enable-rewind
  php bin/phpboy.php tetris.gb --record=speedrun.json
  php bin/phpboy.php tetris.gb --playback=speedrun.json
  php bin/phpboy.php tetris.gb --headless --frames=3600 --benchmark

HELP;
}

/**
 * @param array<int, string> $argv
 * @return array{rom: string|null, debug: bool, trace: bool, headless: bool, display_mode: string, speed: float, save: string|null, audio: bool, audio_out: string|null, help: bool, frames: int|null, benchmark: bool, memory_profile: bool, config: string|null, savestate_save: string|null, savestate_load: string|null, enable_rewind: bool, rewind_buffer: int, record: string|null, playback: string|null, palette: string|null}
 */
function parseArguments(array $argv): array
{
    $options = [
        'rom' => null,
        'debug' => false,
        'trace' => false,
        'headless' => false,
        'display_mode' => 'ansi-color',
        'speed' => 1.0,
        'save' => null,
        'audio' => false,
        'audio_out' => null,
        'help' => false,
        'frames' => null,
        'benchmark' => false,
        'memory_profile' => false,
        'config' => null,
        'savestate_save' => null,
        'savestate_load' => null,
        'enable_rewind' => false,
        'rewind_buffer' => 60,
        'record' => null,
        'playback' => null,
        'palette' => null,
    ];

    // Parse arguments
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        if ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
        } elseif ($arg === '--debug') {
            $options['debug'] = true;
        } elseif ($arg === '--trace') {
            $options['trace'] = true;
        } elseif ($arg === '--headless') {
            $options['headless'] = true;
        } elseif (str_starts_with($arg, '--rom=')) {
            $options['rom'] = substr($arg, 6);
        } elseif (str_starts_with($arg, '--display-mode=')) {
            $mode = substr($arg, 15);
            if (!in_array($mode, ['ansi-color', 'ascii', 'none'], true)) {
                fwrite(STDERR, "Invalid display mode: $mode (must be: ansi-color, ascii, or none)\n");
                exit(1);
            }
            $options['display_mode'] = $mode;
        } elseif (str_starts_with($arg, '--speed=')) {
            $options['speed'] = (float)substr($arg, 8);
        } elseif (str_starts_with($arg, '--save=')) {
            $options['save'] = substr($arg, 7);
        } elseif ($arg === '--audio') {
            $options['audio'] = true;
        } elseif (str_starts_with($arg, '--audio-out=')) {
            $options['audio_out'] = substr($arg, 12);
        } elseif (str_starts_with($arg, '--frames=')) {
            $options['frames'] = (int)substr($arg, 9);
        } elseif ($arg === '--benchmark') {
            $options['benchmark'] = true;
        } elseif ($arg === '--memory-profile') {
            $options['memory_profile'] = true;
        } elseif (str_starts_with($arg, '--config=')) {
            $options['config'] = substr($arg, 9);
        } elseif (str_starts_with($arg, '--savestate-save=')) {
            $options['savestate_save'] = substr($arg, 17);
        } elseif (str_starts_with($arg, '--savestate-load=')) {
            $options['savestate_load'] = substr($arg, 17);
        } elseif ($arg === '--enable-rewind') {
            $options['enable_rewind'] = true;
        } elseif (str_starts_with($arg, '--rewind-buffer=')) {
            $options['rewind_buffer'] = (int)substr($arg, 16);
            $options['enable_rewind'] = true;
        } elseif (str_starts_with($arg, '--record=')) {
            $options['record'] = substr($arg, 9);
        } elseif (str_starts_with($arg, '--playback=')) {
            $options['playback'] = substr($arg, 11);
        } elseif (str_starts_with($arg, '--palette=')) {
            $options['palette'] = substr($arg, 10);
        } elseif (!str_starts_with($arg, '--')) {
            // Positional argument (ROM file)
            if ($options['rom'] === null) {
                $options['rom'] = $arg;
            }
        } else {
            fwrite(STDERR, "Unknown option: $arg\n");
            exit(1);
        }
    }

    return $options;
}

// Main execution
try {
    /** @var array<int, string> $argv */
    $argv = $_SERVER['argv'] ?? [];
    $options = parseArguments($argv);

    if ($options['help']) {
        showHelp();
        exit(0);
    }

    if ($options['rom'] === null) {
        fwrite(STDERR, "Error: No ROM file specified\n\n");
        showHelp();
        exit(1);
    }

    if (!file_exists($options['rom'])) {
        fwrite(STDERR, "Error: ROM file not found: {$options['rom']}\n");
        exit(1);
    }

    echo "PHPBoy - Game Boy Color Emulator\n";
    echo "================================\n\n";
    echo "ROM: {$options['rom']}\n";
    echo "Size: " . filesize($options['rom']) . " bytes\n";

    if ($options['debug']) {
        echo "Mode: Debug (interactive shell)\n";
    } elseif ($options['trace']) {
        echo "Mode: Trace (CPU instruction logging)\n";
    } elseif ($options['headless']) {
        echo "Mode: Headless (no display)\n";
    } else {
        echo "Mode: Normal\n";
    }

    if (!$options['headless']) {
        echo "Display: {$options['display_mode']}\n";
    }

    if ($options['speed'] !== 1.0) {
        echo "Speed: {$options['speed']}x\n";
    }

    echo "\n";

    // Load configuration
    if ($options['config'] !== null) {
        $config = new \Gb\Config\Config();
        $config->loadFromFile($options['config']);
        echo "Config: Loaded from {$options['config']}\n";
    } else {
        $config = new \Gb\Config\Config();
        if ($config->loadFromDefaultLocations()) {
            echo "Config: Loaded from default location\n";
        }
    }

    // Create emulator
    $emulator = new Emulator();

    // Set DMG palette if specified (before loading ROM)
    if ($options['palette'] !== null) {
        if (!\Gb\Ppu\DmgPalettes::isValid($options['palette'])) {
            fwrite(STDERR, "Error: Invalid palette '{$options['palette']}'\n");
            fwrite(STDERR, "Available palettes: " . implode(', ', \Gb\Ppu\DmgPalettes::getAllPaletteNames()) . "\n");
            fwrite(STDERR, "Available button combos: up, up_a, up_b, left, left_a, left_b, down, down_a, down_b, right, right_a, right_b\n");
            exit(1);
        }
        $emulator->setDmgPalette($options['palette']);
        echo "DMG Palette: {$options['palette']}\n";
    }

    $emulator->loadRom($options['rom']);

    // Set speed multiplier
    if ($options['speed'] !== 1.0) {
        $emulator->setSpeed($options['speed']);
    } elseif ($config !== null) {
        $speed = $config->get('emulation', 'speed', 1.0);
        if ($speed !== 1.0) {
            $emulator->setSpeed((float) $speed);
        }
    }

    // Set up audio output
    if ($options['audio'] && $options['audio_out'] !== null) {
        echo "Warning: Both --audio and --audio-out specified. Using real-time playback only.\n";
        echo "         To record audio, use --audio-out without --audio.\n";
    }

    if ($options['audio']) {
        // Real-time audio playback via pipe to external player
        $audioSink = new PipeAudioSink(48000);
        $emulator->setAudioSink($audioSink);

        if ($audioSink->isAvailable()) {
            echo "Audio: Enabled (using {$audioSink->getPlayerName()})\n";
        } else {
            echo "Audio: Failed to start (install aplay or ffplay for audio support)\n";
        }
    } elseif ($options['audio_out'] !== null) {
        // WAV file recording
        $audioSink = new WavSink($options['audio_out']);
        $emulator->setAudioSink($audioSink);
        echo "Audio: Recording to {$options['audio_out']}\n";
    }

    // Set up input
    if (!$options['headless']) {
        $input = new CliInput();
        $emulator->setInput($input);
    }

    // Set up renderer
    $renderer = new CliRenderer();
    if ($options['headless']) {
        // Headless mode - disable display
        $renderer->setDisplayMode('none');
    } else {
        // Use the specified display mode
        $renderer->setDisplayMode($options['display_mode']);
    }
    $emulator->setFramebuffer($renderer);

    // Set up tracing
    $trace = null;
    if ($options['trace']) {
        $trace = new Trace($emulator);
        $trace->enable();
        echo "CPU tracing enabled\n\n";
    }

    // Load savestate if specified
    if ($options['savestate_load'] !== null) {
        if (!file_exists($options['savestate_load'])) {
            fwrite(STDERR, "Error: Savestate file not found: {$options['savestate_load']}\n");
            exit(1);
        }
        $emulator->loadState($options['savestate_load']);
        echo "Loaded savestate from {$options['savestate_load']}\n";
    }

    // Set up rewind buffer if enabled
    $rewindBuffer = null;
    if ($options['enable_rewind']) {
        $rewindBuffer = new \Gb\Rewind\RewindBuffer($emulator, $options['rewind_buffer']);
        echo "Rewind: Enabled ({$options['rewind_buffer']} seconds)\n";
    }

    // Set up TAS recording/playback
    $inputRecorder = null;
    if ($options['record'] !== null) {
        $inputRecorder = new \Gb\Tas\InputRecorder();
        $inputRecorder->startRecording();
        echo "TAS: Recording to {$options['record']}\n";
    } elseif ($options['playback'] !== null) {
        if (!file_exists($options['playback'])) {
            fwrite(STDERR, "Error: TAS file not found: {$options['playback']}\n");
            exit(1);
        }
        $inputRecorder = new \Gb\Tas\InputRecorder();
        $inputRecorder->loadRecording($options['playback']);
        $inputRecorder->startPlayback();
        echo "TAS: Playing back from {$options['playback']}\n";
    }

    // Run in appropriate mode
    if ($options['debug']) {
        // Run debugger
        $debugger = new Debugger($emulator);
        $debugger->run();
    } elseif ($options['headless']) {
        // Run headless for a fixed number of frames (for testing)
        $frames = $options['frames'] ?? 60;

        // Benchmark mode: track timing and FPS
        if ($options['benchmark']) {
            echo "Running benchmark for $frames frames...\n";
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);

            for ($i = 0; $i < $frames; $i++) {
                $emulator->step();

                // Record for rewind buffer
                if ($rewindBuffer !== null) {
                    $rewindBuffer->recordFrame();
                }

                // Record for TAS
                if ($inputRecorder !== null && $inputRecorder->isRecording()) {
                    $inputRecorder->recordFrame([]);
                }

                // Progress indicator every 600 frames (10 seconds at 60 FPS)
                if (($i + 1) % 600 === 0 && !$options['memory_profile']) {
                    $elapsed = microtime(true) - $startTime;
                    $currentFps = ($i + 1) / $elapsed;
                    echo sprintf("Progress: %d/%d frames (%.1f FPS)\n", $i + 1, $frames, $currentFps);
                }
            }

            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            $duration = $endTime - $startTime;
            $fps = $frames / $duration;
            $peakMemory = memory_get_peak_usage(true);

            echo "\n";
            echo "========================================\n";
            echo "Benchmark Results\n";
            echo "========================================\n";
            echo sprintf("Frames:       %d\n", $frames);
            echo sprintf("Duration:     %.2f seconds\n", $duration);
            echo sprintf("Average FPS:  %.2f\n", $fps);
            echo sprintf("Target FPS:   60.0\n");
            echo sprintf("Performance:  %.1f%% of target speed\n", ($fps / 60.0) * 100);
            echo sprintf("Memory Start: %.2f MB\n", $startMemory / 1024 / 1024);
            echo sprintf("Memory End:   %.2f MB\n", $endMemory / 1024 / 1024);
            echo sprintf("Memory Peak:  %.2f MB\n", $peakMemory / 1024 / 1024);
            echo sprintf("Memory Delta: %.2f MB\n", ($endMemory - $startMemory) / 1024 / 1024);
            echo "========================================\n";
        } elseif ($options['memory_profile']) {
            echo "Running memory profiling for $frames frames...\n";
            $measurements = [];

            for ($i = 0; $i < $frames; $i++) {
                $emulator->step();

                // Record for rewind buffer
                if ($rewindBuffer !== null) {
                    $rewindBuffer->recordFrame();
                }

                // Record for TAS
                if ($inputRecorder !== null && $inputRecorder->isRecording()) {
                    $inputRecorder->recordFrame([]);
                }

                // Measure memory every 60 frames (1 second at 60 FPS)
                if ($i % 60 === 0 || $i === $frames - 1) {
                    $measurements[] = [
                        'frame' => $i,
                        'memory' => memory_get_usage(true),
                        'peak' => memory_get_peak_usage(true),
                    ];
                }
            }

            echo "\n";
            echo "========================================\n";
            echo "Memory Profile\n";
            echo "========================================\n";
            echo sprintf("%-10s %-15s %-15s\n", "Frame", "Memory (MB)", "Peak (MB)");
            echo "----------------------------------------\n";

            foreach ($measurements as $m) {
                echo sprintf(
                    "%-10d %-15.2f %-15.2f\n",
                    $m['frame'],
                    $m['memory'] / 1024 / 1024,
                    $m['peak'] / 1024 / 1024
                );
            }

            $first = $measurements[0];
            $last = $measurements[count($measurements) - 1];
            $delta = $last['memory'] - $first['memory'];

            echo "----------------------------------------\n";
            echo sprintf("Memory Growth: %.2f MB over %d frames\n", $delta / 1024 / 1024, $frames);
            echo sprintf("Final Peak:    %.2f MB\n", $last['peak'] / 1024 / 1024);

            if ($delta > 0) {
                $perFrame = $delta / $frames;
                echo sprintf("Growth Rate:   %.2f KB/frame\n", $perFrame / 1024);
                if ($perFrame > 100) { // More than 100 bytes per frame
                    echo "WARNING: Possible memory leak detected!\n";
                }
            }
            echo "========================================\n";
        } else {
            echo "Running headless for $frames frames...\n";
            for ($i = 0; $i < $frames; $i++) {
                $emulator->step();

                // Record for rewind buffer
                if ($rewindBuffer !== null) {
                    $rewindBuffer->recordFrame();
                }

                // Record for TAS
                if ($inputRecorder !== null && $inputRecorder->isRecording()) {
                    $inputRecorder->recordFrame([]);
                }
            }
            echo "Completed successfully\n";
        }
    } else {
        // Run normal emulation
        echo "Starting emulation...\n";
        echo "Press Ctrl+C to stop\n\n";
        echo "Controls:\n";
        echo "  Arrow Keys / WASD: D-pad\n";
        echo "  Z: A button\n";
        echo "  X: B button\n";
        echo "  Enter: Start\n";
        echo "  Space: Select\n\n";

        // Set up signal handler for graceful shutdown
        if (function_exists('pcntl_signal')) {
            // Enable async signals so handlers are invoked during execution
            pcntl_async_signals(true);

            pcntl_signal(SIGINT, function () use ($emulator) {
                $emulator->stop();
            });
        }

        $emulator->run();
    }

    echo "\nEmulation stopped.\n";

    // Save savestate if specified
    if ($options['savestate_save'] !== null) {
        $emulator->saveState($options['savestate_save']);
        echo "Saved savestate to {$options['savestate_save']}\n";
    }

    // Save TAS recording if recording was enabled
    if ($inputRecorder !== null && $inputRecorder->isRecording() && $options['record'] !== null) {
        $inputRecorder->stopRecording();
        $inputRecorder->saveRecording($options['record']);
        echo "Saved TAS recording to {$options['record']}\n";
    }

    exit(0);

} catch (\Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    if (getenv('DEBUG')) {
        fwrite(STDERR, $e->getTraceAsString() . "\n");
    }
    exit(1);
}
