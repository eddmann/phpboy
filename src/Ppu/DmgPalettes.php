<?php

declare(strict_types=1);

namespace Gb\Ppu;

/**
 * DMG Colorization Palettes for Game Boy Color
 *
 * Contains palette definitions from the CGB boot ROM for automatically
 * colorizing original Game Boy games. Palettes are stored in RGB555 format
 * (15-bit color, little-endian).
 *
 * Each palette contains:
 * - bg: 4 colors for background palette 0
 * - obj0: 4 colors for object palette 0
 * - obj1: 4 colors for object palette 1
 *
 * Color 0 is typically white/lightest, Color 3 is typically black/darkest.
 */
class DmgPalettes
{
    /**
     * Named palette definitions with RGB555 color values
     *
     * Color format: 0bXBBBBBGGGGGRRRRR (15-bit RGB, bit 15 unused)
     */
    public const PALETTES = [
        // Default palette - Dark Green (used when no game match found)
        'default' => [
            'name' => 'Dark Green',
            'bg' => [0x7FFF, 0x7E60, 0x7C00, 0x0000],    // White, Lime, Green, Black
            'obj0' => [0x7FFF, 0x7E60, 0x7C00, 0x0000],
            'obj1' => [0x7FFF, 0x7E60, 0x7C00, 0x0000],
        ],

        // GBC - Green (p005) - Classic Game Boy look
        'green' => [
            'name' => 'Green',
            'bg' => [0x7FFF, 0x5294, 0x294A, 0x0000],    // White, Light Green, Green, Black
            'obj0' => [0x7FFF, 0x5294, 0x294A, 0x0000],
            'obj1' => [0x7FFF, 0x5294, 0x294A, 0x0000],
        ],

        // GBC - Brown (p012) - Sepia/vintage look
        'brown' => [
            'name' => 'Brown',
            'bg' => [0x7FFF, 0x6318, 0x4631, 0x0000],    // White, Tan, Brown, Black
            'obj0' => [0x7FFF, 0x6318, 0x4631, 0x0000],
            'obj1' => [0x7FFF, 0x6318, 0x4631, 0x0000],
        ],

        // GBC - Blue (p518) - Cool blue tones
        'blue' => [
            'name' => 'Blue',
            'bg' => [0x7FFF, 0x6B7D, 0x001F, 0x0000],    // White, Light Blue, Blue, Black
            'obj0' => [0x7FFF, 0x6B7D, 0x001F, 0x0000],
            'obj1' => [0x7FFF, 0x6B7D, 0x001F, 0x0000],
        ],

        // Grayscale - Original DMG appearance
        'grayscale' => [
            'name' => 'Grayscale',
            'bg' => [0x7FFF, 0x56B5, 0x294A, 0x0000],    // White, Light Gray, Dark Gray, Black
            'obj0' => [0x7FFF, 0x56B5, 0x294A, 0x0000],
            'obj1' => [0x7FFF, 0x56B5, 0x294A, 0x0000],
        ],

        // Pokemon Red - Red tones with green sprites
        'pokemon_red' => [
            'name' => 'Pokemon Red',
            'bg' => [0x7FFF, 0x3FE6, 0x12A4, 0x0000],    // White, Light Red, Red, Black
            'obj0' => [0x7FFF, 0x3E1F, 0x0140, 0x0000],  // White, Light Green, Green, Black
            'obj1' => [0x7FFF, 0x3FE6, 0x12A4, 0x0000],  // White, Light Red, Red, Black
        ],

        // Pokemon Blue - Blue tones with complementary sprites
        'pokemon_blue' => [
            'name' => 'Pokemon Blue',
            'bg' => [0x7FFF, 0x329F, 0x001F, 0x0000],    // White, Light Blue, Blue, Black
            'obj0' => [0x7FFF, 0x3E1F, 0x0140, 0x0000],  // White, Light Green, Green, Black
            'obj1' => [0x7FFF, 0x329F, 0x001F, 0x0000],  // White, Light Blue, Blue, Black
        ],

        // Pokemon Yellow - Yellow tones
        'pokemon_yellow' => [
            'name' => 'Pokemon Yellow',
            'bg' => [0x7FFF, 0x7FC0, 0x7F00, 0x0000],    // White, Light Yellow, Yellow, Black
            'obj0' => [0x7FFF, 0x3FE6, 0x12A4, 0x0000],  // White, Light Red, Red, Black
            'obj1' => [0x7FFF, 0x001F, 0x0010, 0x0000],  // White, Blue, Dark Blue, Black
        ],

        // Red/Yellow warm palette
        'red_yellow' => [
            'name' => 'Red/Yellow',
            'bg' => [0x7FFF, 0x7FE0, 0x7C00, 0x0000],    // White, Yellow, Orange, Black
            'obj0' => [0x7FFF, 0x3FE6, 0x12A4, 0x0000],  // White, Light Red, Red, Black
            'obj1' => [0x7FFF, 0x7FE0, 0x7C00, 0x0000],
        ],

        // Pastel palette
        'pastel' => [
            'name' => 'Pastel',
            'bg' => [0x7FFF, 0x6F7B, 0x5EF7, 0x0000],    // White, Pastel Pink, Pastel Purple, Black
            'obj0' => [0x7FFF, 0x7FE0, 0x5FE0, 0x0000],  // White, Pastel Yellow, Pastel Green, Black
            'obj1' => [0x7FFF, 0x3F1F, 0x1F1F, 0x0000],  // White, Pastel Cyan, Pastel Blue, Black
        ],

        // Inverted/Negative
        'inverted' => [
            'name' => 'Inverted',
            'bg' => [0x0000, 0x294A, 0x56B5, 0x7FFF],    // Black, Dark Gray, Light Gray, White
            'obj0' => [0x0000, 0x294A, 0x56B5, 0x7FFF],
            'obj1' => [0x0000, 0x294A, 0x56B5, 0x7FFF],
        ],

        // Super Mario Land
        'super_mario_land' => [
            'name' => 'Super Mario Land',
            'bg' => [0x7FFF, 0x7FE0, 0x7C00, 0x0000],    // White, Yellow, Orange-Red, Black
            'obj0' => [0x7FFF, 0x3FE6, 0x12A4, 0x0000],  // White, Light Red, Red, Black
            'obj1' => [0x7FFF, 0x7E60, 0x5E00, 0x0000],  // White, Light Green, Green, Black
        ],

        // Tetris
        'tetris' => [
            'name' => 'Tetris',
            'bg' => [0x7FFF, 0x6F7B, 0x329F, 0x0000],    // White, Pink, Blue, Black
            'obj0' => [0x7FFF, 0x6F7B, 0x329F, 0x0000],
            'obj1' => [0x7FFF, 0x7FE0, 0x5E00, 0x0000],  // White, Yellow, Green, Black
        ],

        // Kirby's Dream Land
        'kirbys_dream_land' => [
            'name' => "Kirby's Dream Land",
            'bg' => [0x7FFF, 0x6F7B, 0x3FE6, 0x0000],    // White, Pink, Light Pink, Black
            'obj0' => [0x7FFF, 0x6F7B, 0x3FE6, 0x0000],
            'obj1' => [0x7FFF, 0x7FE0, 0x7C00, 0x0000],  // White, Yellow, Orange, Black
        ],

        // Zelda: Link's Awakening
        'links_awakening' => [
            'name' => "Zelda: Link's Awakening",
            'bg' => [0x7FFF, 0x5EF7, 0x294A, 0x0000],    // White, Light Green, Green, Black
            'obj0' => [0x7FFF, 0x7FE0, 0x5E00, 0x0000],  // White, Yellow, Green, Black
            'obj1' => [0x7FFF, 0x3FE6, 0x12A4, 0x0000],  // White, Light Red, Red, Black
        ],

        // Metroid II
        'metroid_2' => [
            'name' => 'Metroid II',
            'bg' => [0x7FFF, 0x7FE0, 0x7C00, 0x0000],    // White, Yellow, Orange, Black
            'obj0' => [0x7FFF, 0x3FE6, 0x12A4, 0x0000],  // White, Light Red, Red, Black
            'obj1' => [0x7FFF, 0x329F, 0x001F, 0x0000],  // White, Light Blue, Blue, Black
        ],
    ];

    /**
     * Checksum to palette mapping
     *
     * Maps title checksums (sum of bytes 0x0134-0x0143) to palette names.
     * Based on the CGB boot ROM lookup table.
     */
    public const CHECKSUM_MAP = [
        // Pokemon games
        0x01 => 'pokemon_red',      // POKEMON RED (actual checksum TBD)
        0x58 => 'pokemon_blue',     // POKEMON BLUE (actual checksum TBD)
        0xDB => 'pokemon_yellow',   // POKEMON YELLOW

        // Popular games
        0x27 => 'super_mario_land', // SUPER MARIOLAN
        0x14 => 'tetris',           // TETRIS
        0x88 => 'kirbys_dream_land', // KIRBY DREAM LAN
        0x49 => 'links_awakening',  // ZELDA
        0x52 => 'metroid_2',        // METROID2

        // Add more game checksums as discovered
    ];

    /**
     * Manual palette selection via button combinations
     *
     * Maps button combo strings to palette names.
     */
    public const MANUAL_PALETTES = [
        'up' => 'brown',
        'up_a' => 'red_yellow',
        'up_b' => 'brown',          // Dark brown variant
        'left' => 'blue',
        'left_a' => 'blue',         // Dark blue variant
        'left_b' => 'grayscale',
        'down' => 'pastel',
        'down_a' => 'red_yellow',
        'down_b' => 'red_yellow',   // Yellow/blue/green variant
        'right' => 'green',
        'right_a' => 'green',
        'right_b' => 'inverted',
    ];

    /**
     * Get a palette by name
     *
     * @param string $name Palette name
     * @return array{name: string, bg: array<int>, obj0: array<int>, obj1: array<int>}|null
     */
    public static function getPalette(string $name): ?array
    {
        return self::PALETTES[$name] ?? null;
    }

    /**
     * Get palette name by checksum
     *
     * @param int $checksum Title checksum
     * @return string|null Palette name or null if not found
     */
    public static function getPaletteNameByChecksum(int $checksum): ?string
    {
        return self::CHECKSUM_MAP[$checksum] ?? null;
    }

    /**
     * Get palette name by button combination
     *
     * @param string $buttonCombo Button combination string
     * @return string|null Palette name or null if not found
     */
    public static function getPaletteNameByButtons(string $buttonCombo): ?string
    {
        return self::MANUAL_PALETTES[$buttonCombo] ?? null;
    }

    /**
     * Get all available palette names
     *
     * @return array<string>
     */
    public static function getAllPaletteNames(): array
    {
        return array_keys(self::PALETTES);
    }
}
