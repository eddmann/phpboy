<?php

declare(strict_types=1);

namespace Gb\Ppu;

/**
 * Represents an RGB color.
 *
 * Game Boy DMG palette: 4 shades of gray (0-3)
 * Game Boy Color: Full RGB palette (5 bits per component)
 *
 * This class stores 8-bit RGB values for maximum compatibility
 * with various rendering backends (PNG, terminal, canvas).
 */
final readonly class Color
{
    public function __construct(
        public int $r,
        public int $g,
        public int $b,
    ) {
    }

    /**
     * Create a color from DMG shade (0-3, where 0 is white and 3 is black).
     *
     * @param int $shade Shade value 0-3 (0=white, 1=light gray, 2=dark gray, 3=black)
     * @return self
     */
    public static function fromDmgShade(int $shade): self
    {
        $gray = match ($shade) {
            0 => 0xFF, // White
            1 => 0xAA, // Light gray
            2 => 0x55, // Dark gray
            3 => 0x00, // Black
            default => 0xFF,
        };
        return new self($gray, $gray, $gray);
    }

    /**
     * Create a color from GBC 15-bit RGB (5 bits per component).
     *
     * @param int $rgb15 15-bit RGB value (bits 0-4: red, 5-9: green, 10-14: blue)
     * @return self
     */
    public static function fromGbc15bit(int $rgb15): self
    {
        $r = ($rgb15 & 0x001F);
        $g = ($rgb15 & 0x03E0) >> 5;
        $b = ($rgb15 & 0x7C00) >> 10;

        // Scale 5-bit values (0-31) to 8-bit values (0-255)
        return new self(
            (int) (($r * 255) / 31),
            (int) (($g * 255) / 31),
            (int) (($b * 255) / 31),
        );
    }

    /**
     * Convert to 32-bit RGBA value for rendering.
     *
     * @return int 32-bit RGBA value
     */
    public function toRgba(): int
    {
        return ($this->r << 24) | ($this->g << 16) | ($this->b << 8) | 0xFF;
    }
}
