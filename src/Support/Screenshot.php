<?php

declare(strict_types=1);

namespace Gb\Support;

use Gb\Ppu\FramebufferInterface;

/**
 * Screenshot Utility
 *
 * Saves framebuffer to image files.
 * Supports PPM (simple, uncompressed, widely supported) format.
 */
final class Screenshot
{
    /**
     * Save framebuffer to a PPM file.
     *
     * PPM is a simple, uncompressed format that can be viewed with most image viewers
     * and easily converted to other formats with ImageMagick or similar tools.
     *
     * @param FramebufferInterface $framebuffer Source framebuffer
     * @param string $path Output file path (.ppm extension recommended)
     * @throws \RuntimeException If file cannot be written
     */
    public static function savePPM(FramebufferInterface $framebuffer, string $path): void
    {
        $pixels = $framebuffer->getFramebuffer();

        // PPM header: P3 (ASCII), width, height, max color value
        $ppm = "P3\n160 144\n255\n";

        // Write pixel data (RGB triplets)
        for ($y = 0; $y < 144; $y++) {
            for ($x = 0; $x < 160; $x++) {
                $color = $pixels[$y][$x] ?? new \Gb\Ppu\Color(255, 255, 255);
                $ppm .= sprintf("%d %d %d ", $color->r, $color->g, $color->b);
            }
            $ppm .= "\n";
        }

        if (file_put_contents($path, $ppm) === false) {
            throw new \RuntimeException("Failed to save screenshot to: {$path}");
        }
    }

    /**
     * Save framebuffer to a binary PPM file (more compact).
     *
     * Binary PPM (P6) is more space-efficient than ASCII PPM (P3).
     *
     * @param FramebufferInterface $framebuffer Source framebuffer
     * @param string $path Output file path (.ppm extension recommended)
     * @throws \RuntimeException If file cannot be written
     */
    public static function savePPMBinary(FramebufferInterface $framebuffer, string $path): void
    {
        $pixels = $framebuffer->getFramebuffer();

        // PPM header: P6 (binary), width, height, max color value
        $ppm = "P6\n160 144\n255\n";

        // Write pixel data as binary
        for ($y = 0; $y < 144; $y++) {
            for ($x = 0; $x < 160; $x++) {
                $color = $pixels[$y][$x] ?? new \Gb\Ppu\Color(255, 255, 255);
                $ppm .= chr($color->r) . chr($color->g) . chr($color->b);
            }
        }

        if (file_put_contents($path, $ppm) === false) {
            throw new \RuntimeException("Failed to save screenshot to: {$path}");
        }
    }

    /**
     * Save framebuffer to a text file (ASCII art).
     *
     * Converts grayscale values to ASCII characters for terminal viewing.
     *
     * @param FramebufferInterface $framebuffer Source framebuffer
     * @param string $path Output file path (.txt extension recommended)
     * @throws \RuntimeException If file cannot be written
     */
    public static function saveText(FramebufferInterface $framebuffer, string $path): void
    {
        $pixels = $framebuffer->getFramebuffer();

        // ASCII gradient from dark to light
        $gradient = ' .:-=+*#%@';
        $gradientLen = strlen($gradient);

        $text = '';
        for ($y = 0; $y < 144; $y++) {
            for ($x = 0; $x < 160; $x++) {
                $color = $pixels[$y][$x] ?? new \Gb\Ppu\Color(255, 255, 255);

                // Convert to grayscale
                $gray = (int)(($color->r + $color->g + $color->b) / 3);

                // Map to ASCII character
                $index = (int)(($gray / 255) * ($gradientLen - 1));
                $text .= $gradient[$index];
            }
            $text .= "\n";
        }

        if (file_put_contents($path, $text) === false) {
            throw new \RuntimeException("Failed to save screenshot to: {$path}");
        }
    }
}
