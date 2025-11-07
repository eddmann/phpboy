<?php

declare(strict_types=1);

namespace Gb\Cartridge;

/**
 * Game Boy Cartridge Header Parser
 *
 * Parses the cartridge header (0x0100-0x014F) to extract metadata:
 * - Title (0x0134-0x0143)
 * - CGB flag (0x0143): Bit 7 determines CGB compatibility
 * - Cartridge type (0x0147): MBC type
 * - ROM size (0x0148)
 * - RAM size (0x0149)
 *
 * CGB Flag (0x0143):
 * - 0x80: CGB enhanced (supports both DMG and CGB)
 * - 0xC0: CGB only (requires CGB)
 * - Other values: DMG only
 *
 * Reference: Pan Docs - The Cartridge Header
 */
final readonly class CartridgeHeader
{
    public function __construct(
        public string $title,
        public int $cgbFlag,
        public int $cartridgeType,
        public int $romSize,
        public int $ramSize,
    ) {
    }

    /**
     * Parse cartridge header from ROM data.
     *
     * @param array<int, int> $rom ROM data
     * @return self
     */
    public static function fromRom(array $rom): self
    {
        // Extract title (0x0134-0x0143, up to 16 bytes, null-terminated)
        // Note: In CGB mode, byte 0x0143 is the CGB flag, so title is shorter
        $titleBytes = [];
        for ($i = 0x0134; $i < 0x0143; $i++) {
            $byte = $rom[$i] ?? 0x00;
            if ($byte === 0x00) {
                break;
            }
            $titleBytes[] = chr($byte);
        }
        $title = implode('', $titleBytes);

        $cgbFlag = $rom[0x0143] ?? 0x00;
        $cartridgeType = $rom[0x0147] ?? 0x00;
        $romSize = $rom[0x0148] ?? 0x00;
        $ramSize = $rom[0x0149] ?? 0x00;

        return new self(
            title: $title,
            cgbFlag: $cgbFlag,
            cartridgeType: $cartridgeType,
            romSize: $romSize,
            ramSize: $ramSize,
        );
    }

    /**
     * Check if cartridge supports CGB mode.
     *
     * @return bool True if CGB enhanced or CGB only
     */
    public function isCgbSupported(): bool
    {
        return ($this->cgbFlag & 0x80) !== 0;
    }

    /**
     * Check if cartridge requires CGB mode (CGB only).
     *
     * @return bool True if CGB only
     */
    public function isCgbOnly(): bool
    {
        return $this->cgbFlag === 0xC0;
    }

    /**
     * Check if cartridge is DMG only.
     *
     * @return bool True if DMG only
     */
    public function isDmgOnly(): bool
    {
        return !$this->isCgbSupported();
    }
}
