<?php

declare(strict_types=1);

namespace Gb\Frontend\Cli;

use Gb\Ppu\Color;
use Gb\Ppu\FramebufferInterface;

/**
 * CLI Terminal Renderer for PHPBoy.
 *
 * Renders Game Boy screen output to the terminal using ANSI escape codes
 * and block characters for approximate pixel representation.
 *
 * Display modes:
 * - ASCII: Uses ASCII characters for grayscale representation
 * - ANSI: Uses ANSI colors and block characters for better visualization
 * - None: Headless mode (no output)
 *
 * The Game Boy screen is 160x144 pixels, which is downscaled for terminal display.
 */
final class CliRenderer implements FramebufferInterface
{
    private const WIDTH = 160;
    private const HEIGHT = 144;

    /** @var array<int, array<int, Color>> Framebuffer data [y][x] */
    private array $pixels = [];

    private bool $enabled = true;
    private int $frameCount = 0;
    private int $displayInterval = 1; // Display every N frames

    public function __construct()
    {
        // Initialize framebuffer
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $this->pixels[$y] = [];
            for ($x = 0; $x < self::WIDTH; $x++) {
                $this->pixels[$y][$x] = new Color(255, 255, 255); // White
            }
        }
    }

    /**
     * Set a pixel at the given coordinates.
     *
     * @param int $x X coordinate (0-159)
     * @param int $y Y coordinate (0-143)
     * @param Color $color Pixel color
     */
    public function setPixel(int $x, int $y, Color $color): void
    {
        if ($x >= 0 && $x < self::WIDTH && $y >= 0 && $y < self::HEIGHT) {
            $this->pixels[$y][$x] = $color;
        }
    }

    /**
     * Get a pixel at the given coordinates.
     *
     * @param int $x X coordinate (0-159)
     * @param int $y Y coordinate (0-143)
     * @return Color Pixel color
     */
    public function getPixel(int $x, int $y): Color
    {
        if ($x >= 0 && $x < self::WIDTH && $y >= 0 && $y < self::HEIGHT) {
            return $this->pixels[$y][$x];
        }
        return new Color(0, 0, 0); // Black for out of bounds
    }

    /**
     * Clear the framebuffer (fill with white).
     */
    public function clear(): void
    {
        for ($y = 0; $y < self::HEIGHT; $y++) {
            for ($x = 0; $x < self::WIDTH; $x++) {
                $this->pixels[$y][$x] = new Color(255, 255, 255);
            }
        }
    }

    /**
     * Present the framebuffer (render to terminal).
     *
     * This is called after each frame is complete.
     * Due to terminal limitations, we display a scaled-down version.
     */
    public function present(): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->frameCount++;

        // Only display every Nth frame to reduce flicker
        if ($this->frameCount % $this->displayInterval !== 0) {
            return;
        }

        // For CLI rendering, we can either:
        // 1. Print a downscaled ASCII art representation
        // 2. Save to a file for later viewing
        // 3. Do nothing (headless mode)

        // For now, just print a simple status message every 60 frames
        if ($this->frameCount % 60 === 0) {
            $seconds = $this->frameCount / 60;
            echo sprintf("\rFrame: %d (%.1fs)", $this->frameCount, $seconds);
        }
    }

    /**
     * Get the framebuffer width.
     */
    public function getWidth(): int
    {
        return self::WIDTH;
    }

    /**
     * Get the framebuffer height.
     */
    public function getHeight(): int
    {
        return self::HEIGHT;
    }

    /**
     * Get the framebuffer as a 2D array.
     *
     * @return array<int, array<int, Color>> 2D array [y][x] of colors
     */
    public function getFramebuffer(): array
    {
        return $this->pixels;
    }

    /**
     * Get the raw pixel data as a flat array.
     *
     * @return Color[] Array of colors in row-major order
     */
    public function getPixelData(): array
    {
        $data = [];
        for ($y = 0; $y < self::HEIGHT; $y++) {
            for ($x = 0; $x < self::WIDTH; $x++) {
                $data[] = $this->pixels[$y][$x];
            }
        }
        return $data;
    }

    /**
     * Enable or disable rendering output.
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Set display interval (display every N frames).
     */
    public function setDisplayInterval(int $interval): void
    {
        $this->displayInterval = max(1, $interval);
    }

    /**
     * Get current frame count.
     */
    public function getFrameCount(): int
    {
        return $this->frameCount;
    }

    /**
     * Save current framebuffer to a PNG file.
     *
     * Requires GD extension.
     *
     * @param string $filename Output filename
     */
    public function saveToPng(string $filename): void
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException("GD extension is required to save PNG files");
        }

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($image === false) {
            throw new \RuntimeException("Failed to create image");
        }

        for ($y = 0; $y < self::HEIGHT; $y++) {
            for ($x = 0; $x < self::WIDTH; $x++) {
                $color = $this->pixels[$y][$x];
                // Ensure color values are in valid range (0-255)
                $r = max(0, min(255, $color->r));
                $g = max(0, min(255, $color->g));
                $b = max(0, min(255, $color->b));
                $colorIndex = imagecolorallocate($image, $r, $g, $b);
                if ($colorIndex !== false) {
                    imagesetpixel($image, $x, $y, $colorIndex);
                }
            }
        }

        imagepng($image, $filename);
        imagedestroy($image);
    }

    /**
     * Render a downscaled ASCII representation to string.
     *
     * @param int $scale Downscale factor (1 = full size, 2 = half size, etc.)
     * @return string ASCII representation
     */
    public function toAscii(int $scale = 4): string
    {
        $chars = [' ', '.', ':', '-', '=', '+', '*', '#', '%', '@'];
        $output = '';

        for ($y = 0; $y < self::HEIGHT; $y += $scale) {
            for ($x = 0; $x < self::WIDTH; $x += $scale) {
                // Sample the pixel at this position
                $color = $this->pixels[$y][$x];

                // Convert to grayscale (0-255)
                $gray = (int)(($color->r + $color->g + $color->b) / 3);

                // Map to character (darker = more dense)
                $charIndex = (int)((255 - $gray) / 255 * (count($chars) - 1));
                $output .= $chars[$charIndex];
            }
            $output .= "\n";
        }

        return $output;
    }
}
