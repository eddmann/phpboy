<?php

declare(strict_types=1);

namespace Gb\Ppu;

/**
 * Array-based Framebuffer Implementation
 *
 * Stores pixels in a 2D PHP array for testing and basic rendering.
 * Game Boy screen: 160×144 pixels
 */
final class ArrayFramebuffer implements FramebufferInterface
{
    public const WIDTH = 160;
    public const HEIGHT = 144;

    /** @var array<int, array<int, Color>> 2D array [y][x] of Color objects */
    private array $buffer;

    public function __construct()
    {
        $this->clear();
    }

    public function setPixel(int $x, int $y, Color $color): void
    {
        if ($x >= 0 && $x < self::WIDTH && $y >= 0 && $y < self::HEIGHT) {
            $this->buffer[$y][$x] = $color;
        }
    }

    public function getFramebuffer(): array
    {
        return $this->buffer;
    }

    public function clear(): void
    {
        $white = Color::fromDmgShade(0); // White
        $this->buffer = [];
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $this->buffer[$y] = array_fill(0, self::WIDTH, $white);
        }
    }

    public function present(): void
    {
        // No-op for array framebuffer (used for testing)
        // Actual rendering implementations (CLI, WASM) should override this
    }
}
