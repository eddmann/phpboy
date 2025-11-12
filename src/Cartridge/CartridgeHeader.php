<?php

declare(strict_types=1);

namespace Gb\Cartridge;

/**
 * Game Boy Cartridge Header Parser
 *
 * Parses the cartridge header (0x0100-0x014F) to extract metadata and perform validation.
 *
 * Header Layout:
 * - 0x0100-0x0103: Entry point (4 bytes)
 * - 0x0104-0x0133: Nintendo logo (48 bytes, must match for valid ROM)
 * - 0x0134-0x0143: Title (up to 16 bytes, null-terminated)
 * - 0x0143: CGB flag (0x80 = CGB enhanced, 0xC0 = CGB only)
 * - 0x0144-0x0145: New licensee code (2 bytes)
 * - 0x0146: SGB flag (0x03 = SGB enhanced, 0x00 = no SGB)
 * - 0x0147: Cartridge type (MBC type)
 * - 0x0148: ROM size
 * - 0x0149: RAM size
 * - 0x014A: Destination code (0x00 = Japan, 0x01 = Overseas)
 * - 0x014B: Old licensee code (0x33 = use new licensee code)
 * - 0x014C: Mask ROM version number
 * - 0x014D: Header checksum (8-bit checksum of 0x0134-0x014C)
 * - 0x014E-0x014F: Global checksum (16-bit checksum of entire ROM, not verified by hardware)
 *
 * Reference: Pan Docs - The Cartridge Header
 */
final readonly class CartridgeHeader
{
    /** Nintendo logo data (48 bytes at 0x0104-0x0133) */
    private const NINTENDO_LOGO = [
        0xCE, 0xED, 0x66, 0x66, 0xCC, 0x0D, 0x00, 0x0B,
        0x03, 0x73, 0x00, 0x83, 0x00, 0x0C, 0x00, 0x0D,
        0x00, 0x08, 0x11, 0x1F, 0x88, 0x89, 0x00, 0x0E,
        0xDC, 0xCC, 0x6E, 0xE6, 0xDD, 0xDD, 0xD9, 0x99,
        0xBB, 0xBB, 0x67, 0x63, 0x6E, 0x0E, 0xEC, 0xCC,
        0xDD, 0xDC, 0x99, 0x9F, 0xBB, 0xB9, 0x33, 0x3E,
    ];

    /**
     * @param array<int, int> $entryPoint Entry point code (0x0100-0x0103)
     * @param array<int, int> $nintendoLogo Nintendo logo data (0x0104-0x0133)
     * @param string $title Game title (0x0134-0x0143)
     * @param array<int, int> $titleBytes Raw title bytes (0x0134-0x0143, 16 bytes for checksum calculation)
     * @param int $cgbFlag CGB compatibility flag (0x0143)
     * @param int $newLicenseeCode New licensee code (0x0144-0x0145, as 16-bit value)
     * @param int $sgbFlag SGB compatibility flag (0x0146)
     * @param CartridgeType $cartridgeType Cartridge type (0x0147)
     * @param int $romSizeCode ROM size code (0x0148)
     * @param int $ramSizeCode RAM size code (0x0149)
     * @param int $destinationCode Destination code (0x014A)
     * @param int $oldLicenseeCode Old licensee code (0x014B)
     * @param int $maskRomVersion Mask ROM version (0x014C)
     * @param int $headerChecksum Header checksum (0x014D)
     * @param int $globalChecksum Global checksum (0x014E-0x014F)
     * @param bool $isLogoValid True if Nintendo logo matches expected data
     * @param bool $isHeaderChecksumValid True if header checksum is correct
     */
    public function __construct(
        public array $entryPoint,
        public array $nintendoLogo,
        public string $title,
        public array $titleBytes,
        public int $cgbFlag,
        public int $newLicenseeCode,
        public int $sgbFlag,
        public CartridgeType $cartridgeType,
        public int $romSizeCode,
        public int $ramSizeCode,
        public int $destinationCode,
        public int $oldLicenseeCode,
        public int $maskRomVersion,
        public int $headerChecksum,
        public int $globalChecksum,
        public bool $isLogoValid,
        public bool $isHeaderChecksumValid,
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
        // Entry point (0x0100-0x0103)
        $entryPoint = array_slice($rom, 0x0100, 4);

        // Nintendo logo (0x0104-0x0133)
        $nintendoLogo = array_slice($rom, 0x0104, 48);

        // Validate Nintendo logo
        $isLogoValid = $nintendoLogo === self::NINTENDO_LOGO;

        // Extract title (0x0134-0x0143, up to 16 bytes, null-terminated)
        // Note: In CGB mode, bytes 0x013F-0x0142 may be manufacturer code,
        // and 0x0143 is the CGB flag, so title may be shorter

        // Store raw title bytes (0x0134-0x0143) for checksum calculation
        $titleBytesRaw = [];
        for ($i = 0x0134; $i <= 0x0143; $i++) {
            $titleBytesRaw[] = $rom[$i] ?? 0x00;
        }

        // Extract printable title string
        $titleChars = [];
        for ($i = 0x0134; $i < 0x0143; $i++) {
            $byte = $rom[$i] ?? 0x00;
            if ($byte === 0x00) {
                break;
            }
            $titleChars[] = chr($byte);
        }
        $title = implode('', $titleChars);

        // CGB flag (0x0143)
        $cgbFlag = $rom[0x0143] ?? 0x00;

        // New licensee code (0x0144-0x0145)
        $newLicenseeCode = (($rom[0x0144] ?? 0x00) << 8) | ($rom[0x0145] ?? 0x00);

        // SGB flag (0x0146)
        $sgbFlag = $rom[0x0146] ?? 0x00;

        // Cartridge type (0x0147)
        $cartridgeTypeValue = $rom[0x0147] ?? 0x00;
        $cartridgeType = CartridgeType::tryFrom($cartridgeTypeValue) ?? CartridgeType::ROM_ONLY;

        // ROM size (0x0148)
        $romSizeCode = $rom[0x0148] ?? 0x00;

        // RAM size (0x0149)
        $ramSizeCode = $rom[0x0149] ?? 0x00;

        // Destination code (0x014A)
        $destinationCode = $rom[0x014A] ?? 0x00;

        // Old licensee code (0x014B)
        $oldLicenseeCode = $rom[0x014B] ?? 0x00;

        // Mask ROM version (0x014C)
        $maskRomVersion = $rom[0x014C] ?? 0x00;

        // Header checksum (0x014D)
        $headerChecksum = $rom[0x014D] ?? 0x00;

        // Global checksum (0x014E-0x014F)
        $globalChecksum = (($rom[0x014E] ?? 0x00) << 8) | ($rom[0x014F] ?? 0x00);

        // Validate header checksum
        // Sum of bytes 0x0134-0x014C should equal -0x014D - 1 (mod 256)
        $sum = 0;
        for ($i = 0x0134; $i <= 0x014C; $i++) {
            $sum = ($sum + ($rom[$i] ?? 0x00)) & 0xFF;
        }
        $expectedChecksum = (0x100 - $sum - 1) & 0xFF;
        $isHeaderChecksumValid = $headerChecksum === $expectedChecksum;

        return new self(
            entryPoint: $entryPoint,
            nintendoLogo: $nintendoLogo,
            title: $title,
            titleBytes: $titleBytesRaw,
            cgbFlag: $cgbFlag,
            newLicenseeCode: $newLicenseeCode,
            sgbFlag: $sgbFlag,
            cartridgeType: $cartridgeType,
            romSizeCode: $romSizeCode,
            ramSizeCode: $ramSizeCode,
            destinationCode: $destinationCode,
            oldLicenseeCode: $oldLicenseeCode,
            maskRomVersion: $maskRomVersion,
            headerChecksum: $headerChecksum,
            globalChecksum: $globalChecksum,
            isLogoValid: $isLogoValid,
            isHeaderChecksumValid: $isHeaderChecksumValid,
        );
    }

    /**
     * Get ROM size in bytes.
     *
     * Formula: 32 KiB << romSizeCode (for codes 0x00-0x08)
     *
     * @return int ROM size in bytes
     */
    public function getRomSize(): int
    {
        return match ($this->romSizeCode) {
            0x00 => 32 * 1024,       // 32 KiB (2 banks)
            0x01 => 64 * 1024,       // 64 KiB (4 banks)
            0x02 => 128 * 1024,      // 128 KiB (8 banks)
            0x03 => 256 * 1024,      // 256 KiB (16 banks)
            0x04 => 512 * 1024,      // 512 KiB (32 banks)
            0x05 => 1024 * 1024,     // 1 MiB (64 banks)
            0x06 => 2 * 1024 * 1024, // 2 MiB (128 banks)
            0x07 => 4 * 1024 * 1024, // 4 MiB (256 banks)
            0x08 => 8 * 1024 * 1024, // 8 MiB (512 banks)
            0x52 => 1152 * 1024,     // 1.1 MiB (72 banks)
            0x53 => 1280 * 1024,     // 1.25 MiB (80 banks)
            0x54 => 1536 * 1024,     // 1.5 MiB (96 banks)
            default => 32 * 1024,    // Default to 32 KiB
        };
    }

    /**
     * Get RAM size in bytes.
     *
     * @return int RAM size in bytes
     */
    public function getRamSize(): int
    {
        // MBC2 has built-in 512x4 bits RAM (regardless of RAM size code)
        if ($this->cartridgeType === CartridgeType::MBC2 || $this->cartridgeType === CartridgeType::MBC2_BATTERY) {
            return 512; // 512 x 4 bits = 2048 bits = 256 bytes effective (each nibble stored in a byte)
        }

        return match ($this->ramSizeCode) {
            0x00 => 0,           // No RAM
            0x01 => 2 * 1024,    // 2 KiB (unofficial, not used)
            0x02 => 8 * 1024,    // 8 KiB (1 bank)
            0x03 => 32 * 1024,   // 32 KiB (4 banks of 8 KiB)
            0x04 => 128 * 1024,  // 128 KiB (16 banks of 8 KiB)
            0x05 => 64 * 1024,   // 64 KiB (8 banks of 8 KiB)
            default => 0,        // Unknown, assume no RAM
        };
    }

    /**
     * Get number of ROM banks.
     *
     * @return int Number of ROM banks (each bank is 16 KiB)
     */
    public function getRomBankCount(): int
    {
        return $this->getRomSize() / (16 * 1024);
    }

    /**
     * Get number of RAM banks.
     *
     * @return int Number of RAM banks (each bank is 8 KiB, except MBC2)
     */
    public function getRamBankCount(): int
    {
        // MBC2 has no RAM banks (built-in RAM)
        if ($this->cartridgeType === CartridgeType::MBC2 || $this->cartridgeType === CartridgeType::MBC2_BATTERY) {
            return 1;
        }

        $ramSize = $this->getRamSize();
        if ($ramSize === 0) {
            return 0;
        }

        return (int)($ramSize / (8 * 1024));
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

    /**
     * Check if cartridge supports SGB features.
     *
     * @return bool True if SGB enhanced
     */
    public function isSgbSupported(): bool
    {
        return $this->sgbFlag === 0x03;
    }

    /**
     * Check if cartridge is Japanese version.
     *
     * @return bool True if destination is Japan
     */
    public function isJapanese(): bool
    {
        return $this->destinationCode === 0x00;
    }

    /**
     * Get raw title bytes for checksum calculation.
     *
     * Returns 16 bytes from 0x0134-0x0143 used for CGB colorization detection.
     *
     * @return array<int, int> Raw title bytes (16 bytes)
     */
    public function getTitleBytes(): array
    {
        return $this->titleBytes;
    }

    /**
     * Get a summary of the cartridge header for debugging.
     *
     * @return array<string, mixed> Header summary
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'cartridgeType' => $this->cartridgeType->getDescription(),
            'romSize' => sprintf('%d KiB (%d banks)', $this->getRomSize() / 1024, $this->getRomBankCount()),
            'ramSize' => sprintf('%d KiB (%d banks)', $this->getRamSize() / 1024, $this->getRamBankCount()),
            'cgbMode' => $this->isCgbOnly() ? 'CGB Only' : ($this->isCgbSupported() ? 'CGB Enhanced' : 'DMG Only'),
            'sgbSupport' => $this->isSgbSupported() ? 'Yes' : 'No',
            'destination' => $this->isJapanese() ? 'Japan' : 'Overseas',
            'version' => $this->maskRomVersion,
            'logoValid' => $this->isLogoValid ? 'Yes' : 'No',
            'headerChecksumValid' => $this->isHeaderChecksumValid ? 'Yes' : 'No',
        ];
    }
}
