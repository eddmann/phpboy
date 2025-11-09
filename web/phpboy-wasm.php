<?php

declare(strict_types=1);

/**
 * PHPBoy WASM Entry Point
 *
 * This script initializes the PHPBoy emulator for use in the browser via php-wasm.
 * It sets up the emulator with WASM-compatible I/O implementations and loads
 * the ROM from the virtual filesystem.
 *
 * Expected to be called from JavaScript after a ROM has been written to /rom.gb
 */

use Gb\Emulator;
use Gb\Frontend\Wasm\WasmAudioSink;
use Gb\Frontend\Wasm\WasmFramebuffer;
use Gb\Frontend\Wasm\WasmInput;

// Global emulator instance
// This must be global so JavaScript can access it across multiple php.run() calls
global $emulator;

if (!isset($emulator)) {
    // Create emulator instance
    $emulator = new Emulator();

    // Set up WASM I/O implementations
    $framebuffer = new WasmFramebuffer();
    $audioSink = new WasmAudioSink();
    $input = new WasmInput();

    $emulator->setFramebuffer($framebuffer);
    $emulator->setAudioSink($audioSink);
    $emulator->setInput($input);

    // Load ROM from virtual filesystem
    $romPath = '/rom.gb';
    if (file_exists($romPath)) {
        $emulator->loadRom($romPath);
        echo "Emulator initialized successfully\n";
    } else {
        throw new \RuntimeException("ROM file not found at {$romPath}");
    }
}

// Helper function to get framebuffer pixels as RGBA array
function getFramebufferPixels(): array
{
    global $emulator;
    $framebuffer = $emulator->getFramebuffer();

    if ($framebuffer instanceof WasmFramebuffer) {
        return $framebuffer->getPixelsRGBA();
    }

    return [];
}

// Helper function to get audio samples
function getAudioSamples(): array
{
    global $emulator;
    $audioSink = $emulator->getAudioSink();

    if ($audioSink instanceof WasmAudioSink) {
        return $audioSink->getSamplesFlat();
    }

    return [];
}

// Helper function to set button state
function setButtonState(int $buttonCode, bool $pressed): void
{
    global $emulator;
    $input = $emulator->getInput();

    if ($input instanceof WasmInput) {
        $input->setButtonState($buttonCode, $pressed);
    }
}
