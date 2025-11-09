<?php

declare(strict_types=1);

namespace Gb\Frontend\Wasm;

use Gb\Ppu\Color;
use Gb\Ppu\FramebufferInterface;

/**
 * WebAssembly Framebuffer Bridge for PHPBoy in the browser.
 *
 * Stores pixels in a flat array optimized for JavaScript Canvas ImageData transfer.
 * Game Boy screen: 160×144 pixels = 23,040 pixels = 92,160 bytes (RGBA)
 *
 * JavaScript integration example:
 * ```javascript
 * // PHP side: call getPixelsRgba() to get flat RGBA array
 * const rgbaData = phpboy.getFramebufferData();
 *
 * // JavaScript side: create ImageData from RGBA array
 * const imageData = new ImageData(
 *   new Uint8ClampedArray(rgbaData),
 *   160,  // width
 *   144   // height
 * );
 *
 * // Draw to canvas
 * const ctx = canvas.getContext('2d');
 * ctx.putImageData(imageData, 0, 0);
 * ```
 */
final class WasmFramebuffer implements FramebufferInterface
{
    public const WIDTH = 160;
    public const HEIGHT = 144;
    private const PIXEL_COUNT = self::WIDTH * self::HEIGHT;

    /**
     * Pixel buffer stored as flat array [r, g, b, a, r, g, b, a, ...]
     * Total size: 160 * 144 * 4 = 92,160 bytes
     *
     * @var int[]
     */
    private array $rgbaBuffer;

    public function __construct()
    {
        $this->clear();
    }

    public function setPixel(int $x, int $y, Color $color): void
    {
        if ($x < 0 || $x >= self::WIDTH || $y < 0 || $y >= self::HEIGHT) {
            return;
        }

        // Calculate flat array index: (y * width + x) * 4 channels
        $index = ($y * self::WIDTH + $x) * 4;

        $this->rgbaBuffer[$index + 0] = $color->r;
        $this->rgbaBuffer[$index + 1] = $color->g;
        $this->rgbaBuffer[$index + 2] = $color->b;
        $this->rgbaBuffer[$index + 3] = 255; // Alpha (always opaque)
    }

    public function getFramebuffer(): array
    {
        // Convert flat RGBA buffer back to 2D array of Color objects
        // This is for compatibility with FramebufferInterface, but not efficient for WASM
        $buffer = [];

        for ($y = 0; $y < self::HEIGHT; $y++) {
            $buffer[$y] = [];
            for ($x = 0; $x < self::WIDTH; $x++) {
                $index = ($y * self::WIDTH + $x) * 4;
                $buffer[$y][$x] = new Color(
                    $this->rgbaBuffer[$index + 0],
                    $this->rgbaBuffer[$index + 1],
                    $this->rgbaBuffer[$index + 2],
                );
            }
        }

        return $buffer;
    }

    public function clear(): void
    {
        // Initialize to white (255, 255, 255, 255)
        $this->rgbaBuffer = array_fill(0, self::PIXEL_COUNT * 4, 255);
    }

    /**
     * Get the raw RGBA pixel data as a flat array for JavaScript.
     *
     * Returns array of integers in format: [r, g, b, a, r, g, b, a, ...]
     * Length: 160 * 144 * 4 = 92,160 values
     *
     * This array can be directly transferred to JavaScript's Uint8ClampedArray
     * for use with Canvas ImageData.
     *
     * @return int[] Flat RGBA array
     */
    public function getPixelsRgba(): array
    {
        return $this->rgbaBuffer;
    }

    /**
     * Get pixel data as a packed 32-bit integer array (RGBA format).
     *
     * Each pixel is packed as: (r << 24) | (g << 16) | (b << 8) | a
     * Length: 160 * 144 = 23,040 values
     *
     * Alternative format that may be more efficient for some JavaScript APIs.
     *
     * @return int[] Array of 32-bit RGBA values
     */
    public function getPixelsPacked(): array
    {
        $packed = [];

        for ($i = 0; $i < self::PIXEL_COUNT * 4; $i += 4) {
            $packed[] = ($this->rgbaBuffer[$i] << 24)
                | ($this->rgbaBuffer[$i + 1] << 16)
                | ($this->rgbaBuffer[$i + 2] << 8)
                | $this->rgbaBuffer[$i + 3];
        }

        return $packed;
    }

    /**
     * Get a specific pixel's color.
     *
     * @param int $x X coordinate (0-159)
     * @param int $y Y coordinate (0-143)
     * @return Color|null Color at (x, y) or null if out of bounds
     */
    public function getPixel(int $x, int $y): ?Color
    {
        if ($x < 0 || $x >= self::WIDTH || $y < 0 || $y >= self::HEIGHT) {
            return null;
        }

        $index = ($y * self::WIDTH + $x) * 4;

        return new Color(
            $this->rgbaBuffer[$index + 0],
            $this->rgbaBuffer[$index + 1],
            $this->rgbaBuffer[$index + 2],
        );
    }

    /**
     * Get framebuffer dimensions.
     *
     * @return array{width: int, height: int}
     */
    public function getDimensions(): array
    {
        return [
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
        ];
    }
}
