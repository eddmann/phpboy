#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Gb\Emulator;
use Gb\Frontend\Cli\CliInput;
use Gb\Frontend\Cli\CliRenderer;
use Gb\Apu\Sink\WavSink;
use Gb\Apu\Sink\NullSink;
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
  --rom=<path>         ROM file to load (can also be first positional argument)
  --debug              Enable debugger mode with interactive shell
  --trace              Enable CPU instruction tracing
  --headless           Run without display (for testing)
  --speed=<factor>     Speed multiplier (1.0 = normal, 2.0 = 2x speed, 0.5 = half speed)
  --save=<path>        Save file location (default: <rom>.sav)
  --audio-out=<path>   WAV file to record audio output
  --help               Show this help message

Examples:
  php bin/phpboy.php tetris.gb
  php bin/phpboy.php --rom=tetris.gb --speed=2.0
  php bin/phpboy.php tetris.gb --debug
  php bin/phpboy.php tetris.gb --trace --headless

HELP;
}

/**
 * @param array<int, string> $argv
 * @return array{rom: string|null, debug: bool, trace: bool, headless: bool, speed: float, save: string|null, audio_out: string|null, help: bool}
 */
function parseArguments(array $argv): array
{
    $options = [
        'rom' => null,
        'debug' => false,
        'trace' => false,
        'headless' => false,
        'speed' => 1.0,
        'save' => null,
        'audio_out' => null,
        'help' => false,
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
        } elseif (str_starts_with($arg, '--speed=')) {
            $options['speed'] = (float)substr($arg, 8);
        } elseif (str_starts_with($arg, '--save=')) {
            $options['save'] = substr($arg, 7);
        } elseif (str_starts_with($arg, '--audio-out=')) {
            $options['audio_out'] = substr($arg, 12);
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

    if ($options['speed'] !== 1.0) {
        echo "Speed: {$options['speed']}x\n";
    }

    echo "\n";

    // Create emulator
    $emulator = new Emulator();
    $emulator->loadRom($options['rom']);

    // Set speed multiplier
    if ($options['speed'] !== 1.0) {
        $emulator->setSpeed($options['speed']);
    }

    // Set up audio output
    if ($options['audio_out'] !== null) {
        $audioSink = new WavSink($options['audio_out']);
        $emulator->setAudioSink($audioSink);
        echo "Recording audio to: {$options['audio_out']}\n";
    }

    // Set up input
    if (!$options['headless']) {
        $input = new CliInput();
        $emulator->setInput($input);
    }

    // Set up renderer
    if (!$options['headless']) {
        $renderer = new CliRenderer();
        $emulator->setFramebuffer($renderer);
    }

    // Set up tracing
    $trace = null;
    if ($options['trace']) {
        $trace = new Trace($emulator);
        $trace->enable();
        echo "CPU tracing enabled\n\n";
    }

    // Run in appropriate mode
    if ($options['debug']) {
        // Run debugger
        $debugger = new Debugger($emulator);
        $debugger->run();
    } elseif ($options['headless']) {
        // Run headless for a fixed number of frames (for testing)
        echo "Running headless for 60 frames...\n";
        for ($i = 0; $i < 60; $i++) {
            $emulator->step();
        }
        echo "Completed successfully\n";
    } else {
        // Run normal emulation
        echo "Starting emulation...\n";
        echo "Press Ctrl+C to stop\n\n";

        // Set up signal handler for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () use ($emulator) {
                $emulator->stop();
            });
        }

        $emulator->run();
    }

    echo "\nEmulation stopped.\n";
    exit(0);

} catch (\Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    if (getenv('DEBUG')) {
        fwrite(STDERR, $e->getTraceAsString() . "\n");
    }
    exit(1);
}
