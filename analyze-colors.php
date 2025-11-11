#!/usr/bin/env php
<?php

declare(strict_types=1);

// Read the PPM file and analyze colors
$ppmPath = __DIR__ . '/cgb-acid2-output.ppm';
$data = file_get_contents($ppmPath);

if ($data === false) {
    die("Failed to read PPM file\n");
}

// Parse PPM header
$lines = explode("\n", substr($data, 0, 100));
$magic = $lines[0];  // P6
$dimensions = explode(' ', $lines[1]); // "160 144"
$maxval = (int)$lines[2]; // 255

echo "PPM Format: $magic\n";
echo "Dimensions: {$dimensions[0]}x{$dimensions[1]}\n";
echo "Max Value: $maxval\n\n";

// Find where pixel data starts
$headerEnd = strpos($data, "255\n") + 4;
$pixelData = substr($data, $headerEnd);

// Count unique colors
$colors = [];
$totalPixels = 160 * 144;

for ($i = 0; $i < $totalPixels * 3; $i += 3) {
    if ($i + 2 >= strlen($pixelData)) {
        break;
    }

    $r = ord($pixelData[$i]);
    $g = ord($pixelData[$i + 1]);
    $b = ord($pixelData[$i + 2]);

    $colorKey = sprintf("#%02X%02X%02X", $r, $g, $b);

    if (!isset($colors[$colorKey])) {
        $colors[$colorKey] = [
            'r' => $r,
            'g' => $g,
            'b' => $b,
            'count' => 0
        ];
    }
    $colors[$colorKey]['count']++;
}

echo "Total unique colors found: " . count($colors) . "\n\n";
echo "Top 20 colors by frequency:\n";
echo str_repeat("-", 60) . "\n";
echo sprintf("%-10s %-15s %-12s %s\n", "Color", "RGB", "Count", "Percentage");
echo str_repeat("-", 60) . "\n";

// Sort by count
uasort($colors, fn($a, $b) => $b['count'] <=> $a['count']);

$i = 0;
foreach ($colors as $hex => $info) {
    if ($i++ >= 20) break;

    $percentage = ($info['count'] / $totalPixels) * 100;
    printf(
        "%-10s (%3d,%3d,%3d) %6d px  %5.2f%%\n",
        $hex,
        $info['r'],
        $info['g'],
        $info['b'],
        $info['count'],
        $percentage
    );
}

echo "\n";
echo "Analysis:\n";
echo "- If only 4 or fewer colors: Likely using DMG grayscale\n";
echo "- If many colors (>4): CGB color palettes are active\n";
echo "- Expected CGB colors should not be pure grayscale\n";
