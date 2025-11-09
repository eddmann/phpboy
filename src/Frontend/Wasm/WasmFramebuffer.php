<?php

declare(strict_types=1);

namespace Gb\Frontend\Wasm;

use Gb\Ppu\Color;
use Gb\Ppu\FramebufferInterface;

/**
 * WebAssembly framebuffer implementation for PHPBoy in the browser.
 *
 * Stores pixel data in a simple array format that can be easily
 * transferred to JavaScript for Canvas rendering.
 *
 * The framebuffer maintains a 160×144 pixel array. JavaScript can
 * retrieve this data and render it to an HTML5 Canvas element.
 *
 * Integration with JavaScript:
 * ```javascript
 * // Get pixel data from PHP
 * const pixels = phpInstance.getFramebufferPixels();
 * // pixels is a flat array: [r,g,b,a, r,g,b,a, ...]
 *
 * // Render to canvas
 * const imageData = new ImageData(new Uint8ClampedArray(pixels), 160, 144);
 * ctx.putImageData(imageData, 0, 0);
 * ```
 */
final class WasmFramebuffer implements FramebufferInterface
{
    /** Game Boy screen width */
    private const WIDTH = 160;

    /** Game Boy screen height */
    private const HEIGHT = 144;

    /**
     * Framebuffer storage: 2D array [y][x] of Color objects
     * @var array<int, array<int, Color>>
     */
    private array $buffer = [];

    public function __construct()
    {
        $this->clear();
    }

    /**
     * Set a pixel at the given coordinates.
     */
    public function setPixel(int $x, int $y, Color $color): void
    {
        if ($x < 0 || $x >= self::WIDTH || $y < 0 || $y >= self::HEIGHT) {
            return; // Ignore out-of-bounds writes
        }

        $this->buffer[$y][$x] = $color;
    }

    /**
     * Get the entire framebuffer as a 2D array of colors.
     *
     * @return array<int, array<int, Color>> 2D array [y][x] of Color objects
     */
    public function getFramebuffer(): array
    {
        return $this->buffer;
    }

    /**
     * Clear the framebuffer to white.
     */
    public function clear(): void
    {
        $white = new Color(255, 255, 255);

        for ($y = 0; $y < self::HEIGHT; $y++) {
            for ($x = 0; $x < self::WIDTH; $x++) {
                $this->buffer[$y][$x] = $white;
            }
        }
    }

    /**
     * Get pixel data as a flat RGBA array for JavaScript.
     *
     * Returns a flat array of pixel data in RGBA format suitable for
     * HTML5 Canvas ImageData: [r,g,b,a, r,g,b,a, ...]
     *
     * This method is designed to be called from JavaScript to retrieve
     * the current frame for rendering.
     *
     * @return int[] Flat array of RGBA values (0-255)
     */
    public function getPixelsRGBA(): array
    {
        $pixels = [];

        for ($y = 0; $y < self::HEIGHT; $y++) {
            for ($x = 0; $x < self::WIDTH; $x++) {
                $color = $this->buffer[$y][$x];
                $pixels[] = $color->r;
                $pixels[] = $color->g;
                $pixels[] = $color->b;
                $pixels[] = 255; // Alpha channel (fully opaque)
            }
        }

        return $pixels;
    }

    /**
     * Get width of the framebuffer.
     */
    public function getWidth(): int
    {
        return self::WIDTH;
    }

    /**
     * Get height of the framebuffer.
     */
    public function getHeight(): int
    {
        return self::HEIGHT;
    }
}
