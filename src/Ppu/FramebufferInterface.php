<?php

declare(strict_types=1);

namespace Gb\Ppu;

/**
 * Framebuffer Interface
 *
 * Defines the contract for rendering output from the PPU.
 * Implementations might render to PNG, terminal ASCII art, or HTML5 canvas.
 *
 * Game Boy screen dimensions: 160×144 pixels
 */
interface FramebufferInterface
{
    /**
     * Set a pixel at the given coordinates.
     *
     * @param int $x X coordinate (0-159)
     * @param int $y Y coordinate (0-143)
     * @param Color $color RGB color value
     */
    public function setPixel(int $x, int $y, Color $color): void;

    /**
     * Get the entire framebuffer as a 2D array of colors.
     *
     * @return array<int, array<int, Color>> 2D array [y][x] of Color objects
     */
    public function getFramebuffer(): array;

    /**
     * Clear the framebuffer (typically to white).
     */
    public function clear(): void;
}
