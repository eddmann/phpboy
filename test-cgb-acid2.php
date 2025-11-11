#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Gb\Emulator;

// Load the cgb-acid2 ROM
$emulator = new Emulator();
$emulator->loadRom(__DIR__ . '/third_party/roms/cgb-acid2-master/cgb-acid2.gbc');

// Run for 120 frames to let the test complete
echo "Running cgb-acid2 test ROM...\n";
for ($i = 0; $i < 120; $i++) {
    $emulator->step();
    if ($i % 20 === 0) {
        echo "Frame {$i}/120\n";
    }
}

// Take a screenshot
echo "Taking screenshot...\n";
$emulator->screenshot(__DIR__ . '/cgb-acid2-output.ppm', 'ppm-binary');
echo "Screenshot saved to cgb-acid2-output.ppm\n";

// Also save as text for inspection
$emulator->screenshot(__DIR__ . '/cgb-acid2-output.txt', 'text');
echo "Text version saved to cgb-acid2-output.txt\n";

// Print cartridge info
$cartridge = $emulator->getCartridge();
if ($cartridge !== null) {
    $header = $cartridge->getHeader();
    echo "\nCartridge Information:\n";
    echo "Title: {$header->title}\n";
    echo "CGB Mode: " . ($header->isCgbSupported() ? "YES" : "NO") . "\n";
    echo "CGB Only: " . ($header->isCgbOnly() ? "YES" : "NO") . "\n";

    // Check if PPU is in CGB mode
    $ppu = $emulator->getPpu();
    if ($ppu !== null) {
        echo "PPU CGB Mode: " . ($ppu->isCgbMode() ? "YES" : "NO") . "\n";
    }

    // Check CPU registers
    $cpu = $emulator->getCpu();
    if ($cpu !== null) {
        echo "\nCPU Registers:\n";
        echo sprintf("AF: 0x%04X\n", $cpu->getAF());
        echo sprintf("BC: 0x%04X\n", $cpu->getBC());
        echo sprintf("DE: 0x%04X\n", $cpu->getDE());
        echo sprintf("HL: 0x%04X\n", $cpu->getHL());
    }
}

echo "\nDone!\n";
