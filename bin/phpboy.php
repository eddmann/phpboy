#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// PHPBoy CLI Entry Point
// This is a placeholder that will be implemented in Step 12

echo "PHPBoy - Game Boy Color Emulator\n";
echo "================================\n\n";

if ($_SERVER['argc'] < 2) {
    echo "Usage: php bin/phpboy.php <rom-file>\n";
    echo "Example: php bin/phpboy.php third_party/roms/cpu_instrs/cpu_instrs.gb\n\n";
    echo "This is a stub implementation. Full emulator coming soon!\n";
    exit(1);
}

$romFile = $_SERVER['argv'][1];

if (!file_exists($romFile)) {
    echo "Error: ROM file not found: $romFile\n";
    exit(1);
}

echo "ROM file: $romFile\n";
echo "Size: " . filesize($romFile) . " bytes\n\n";
echo "Emulator core not yet implemented. See PLAN.md for development roadmap.\n";
