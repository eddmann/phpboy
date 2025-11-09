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
    private string $displayMode = 'ansi-color'; // 'none', 'ascii', 'ansi-color'
    private bool $cursorHidden = false;

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
        if (!$this->enabled || $this->displayMode === 'none') {
            return;
        }

        $this->frameCount++;

        // Only display every Nth frame to reduce flicker
        if ($this->frameCount % $this->displayInterval !== 0) {
            return;
        }

        if ($this->displayMode === 'ansi-color') {
            // Render full-color terminal output
            $this->clearScreen();
            if (!$this->cursorHidden) {
                $this->hideCursor();
            }
            echo $this->toAnsiColor(2); // Scale 2x (80x72 chars)
            echo sprintf("\nFrame: %d (%.1fs) | Press Ctrl+C to exit", $this->frameCount, $this->frameCount / 60.0);
        } elseif ($this->displayMode === 'ascii') {
            // Render ASCII art representation
            $this->clearScreen();
            echo $this->toAscii(4); // Scale 4x (40x36 chars)
            echo sprintf("\nFrame: %d (%.1fs)", $this->frameCount, $this->frameCount / 60.0);
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

    /**
     * Render full-color ANSI representation using Unicode half-blocks.
     *
     * Uses Unicode half-block characters (▀▄█) to achieve 2x vertical resolution.
     * Each terminal character represents 2 vertical pixels:
     * - Top half: background color
     * - Bottom half: foreground color (using ▀ or ▄)
     *
     * @param int $scale Horizontal downscale factor (1 = full width, 2 = half width)
     * @return string ANSI color representation
     */
    public function toAnsiColor(int $scale = 2): string
    {
        $output = '';

        // Process 2 rows at a time (top and bottom half-blocks)
        for ($y = 0; $y < self::HEIGHT; $y += 2) {
            for ($x = 0; $x < self::WIDTH; $x += $scale) {
                // Get colors for top and bottom pixels
                $topColor = $this->pixels[$y][$x];
                $bottomColor = ($y + 1 < self::HEIGHT) ? $this->pixels[$y + 1][$x] : $topColor;

                // Check if colors are identical
                if ($topColor->r === $bottomColor->r &&
                    $topColor->g === $bottomColor->g &&
                    $topColor->b === $bottomColor->b) {
                    // Same color - use full block with background color
                    $output .= sprintf(
                        "\e[48;2;%d;%d;%dm ",
                        $topColor->r,
                        $topColor->g,
                        $topColor->b
                    );
                } else {
                    // Different colors - use upper half block
                    // Foreground = top color, Background = bottom color
                    $output .= sprintf(
                        "\e[38;2;%d;%d;%dm\e[48;2;%d;%d;%dm▀",
                        $topColor->r,
                        $topColor->g,
                        $topColor->b,
                        $bottomColor->r,
                        $bottomColor->g,
                        $bottomColor->b
                    );
                }
            }
            // Reset colors at end of line
            $output .= "\e[0m\n";
        }

        return $output;
    }

    /**
     * Set the display mode.
     *
     * @param string $mode Display mode: 'none', 'ascii', 'ansi-color'
     */
    public function setDisplayMode(string $mode): void
    {
        if (!in_array($mode, ['none', 'ascii', 'ansi-color'], true)) {
            throw new \InvalidArgumentException("Invalid display mode: $mode");
        }
        $this->displayMode = $mode;
    }

    /**
     * Get the current display mode.
     */
    public function getDisplayMode(): string
    {
        return $this->displayMode;
    }

    /**
     * Clear the terminal screen and move cursor to top-left.
     */
    private function clearScreen(): void
    {
        echo "\e[2J\e[H";
    }

    /**
     * Hide the terminal cursor.
     */
    private function hideCursor(): void
    {
        echo "\e[?25l";
        $this->cursorHidden = true;
    }

    /**
     * Show the terminal cursor.
     */
    public function showCursor(): void
    {
        echo "\e[?25h";
        $this->cursorHidden = false;
    }

    /**
     * Destructor - ensure cursor is restored.
     */
    public function __destruct()
    {
        if ($this->cursorHidden) {
            $this->showCursor();
        }
    }
}
