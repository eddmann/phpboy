<?php

declare(strict_types=1);

namespace Gb\Ppu;

use Gb\Bus\DeviceInterface;
use Gb\Interrupts\InterruptController;
use Gb\Interrupts\InterruptType;
use Gb\Memory\Vram;

/**
 * Pixel Processing Unit (PPU)
 *
 * The PPU is responsible for rendering graphics to the screen.
 * It operates in four modes that cycle through each scanline:
 * - Mode 2 (OAM Search): 80 dots
 * - Mode 3 (Pixel Transfer): 168-291 dots (simplified to 172 dots)
 * - Mode 0 (H-Blank): Remaining dots to reach 456 total
 * - Mode 1 (V-Blank): 10 scanlines (LY 144-153)
 *
 * Screen: 160×144 pixels
 * Scanline: 456 dots (cycles)
 * Frame: 154 scanlines × 456 dots = 70224 dots ≈ 59.7 Hz
 *
 * Reference: Pan Docs - PPU
 */
final class Ppu implements DeviceInterface
{
    // PPU timing constants (in dots/cycles)
    private const DOTS_PER_SCANLINE = 456;
    private const OAM_SEARCH_DOTS = 80;
    private const PIXEL_TRANSFER_DOTS = 172; // Simplified (actual: 168-291)
    private const SCANLINES_PER_FRAME = 154;
    private const VBLANK_SCANLINE = 144;

    // Register addresses
    private const LCDC = 0xFF40; // LCD Control
    private const STAT = 0xFF41; // LCD Status
    private const SCY = 0xFF42;  // Scroll Y
    private const SCX = 0xFF43;  // Scroll X
    private const LY = 0xFF44;   // LCD Y coordinate (scanline)
    private const LYC = 0xFF45;  // LY Compare
    private const BGP = 0xFF47;  // Background Palette (DMG)
    private const OBP0 = 0xFF48; // Object Palette 0 (DMG)
    private const OBP1 = 0xFF49; // Object Palette 1 (DMG)
    private const WY = 0xFF4A;   // Window Y
    private const WX = 0xFF4B;   // Window X + 7
    private const BCPS = 0xFF68; // Background Color Palette Specification (CGB)
    private const BCPD = 0xFF69; // Background Color Palette Data (CGB)
    private const OCPS = 0xFF6A; // Object Color Palette Specification (CGB)
    private const OCPD = 0xFF6B; // Object Color Palette Data (CGB)

    // LCDC bits
    private const LCDC_LCD_ENABLE = 0x80;
    private const LCDC_WINDOW_TILEMAP = 0x40;
    private const LCDC_WINDOW_ENABLE = 0x20;
    private const LCDC_TILE_DATA = 0x10;
    private const LCDC_BG_TILEMAP = 0x08;
    private const LCDC_OBJ_SIZE = 0x04;
    private const LCDC_OBJ_ENABLE = 0x02;
    private const LCDC_BG_WINDOW_ENABLE = 0x01;

    // STAT bits
    private const STAT_LYC_INT = 0x40;      // LYC=LY interrupt enable
    private const STAT_MODE2_INT = 0x20;    // Mode 2 OAM interrupt enable
    private const STAT_MODE1_INT = 0x10;    // Mode 1 V-Blank interrupt enable
    private const STAT_MODE0_INT = 0x08;    // Mode 0 H-Blank interrupt enable
    private const STAT_LYC_EQUALS = 0x04;   // LYC=LY coincidence flag
    private const STAT_MODE_MASK = 0x03;    // Mode bits (0-1)

    // PPU state
    private PpuMode $mode = PpuMode::OamSearch;
    private int $dots = 0; // Dots elapsed in current mode
    private int $ly = 0;   // Current scanline (0-153)

    // Registers
    private int $lcdc = 0x91; // LCD enabled, BG on
    private int $stat = 0x00;
    private int $scy = 0x00;
    private int $scx = 0x00;
    private int $lyc = 0x00;
    private int $bgp = 0xFC; // Default palette: 11 11 10 01 00
    private int $obp0 = 0xFF;
    private int $obp1 = 0xFF;
    private int $wy = 0x00;
    private int $wx = 0x00;

    // Window internal line counter
    private int $windowLineCounter = 0;

    // Scanline buffer for current rendering
    /** @var array<int, Color> */
    private array $scanlineBuffer = [];

    // Background color buffer for sprite priority (stores raw color 0-3)
    /** @var array<int, int> */
    private array $bgColorBuffer = [];

    // Background priority buffer (stores BG-to-OAM Priority flag, bit 7 of BG attributes)
    /** @var array<int, bool> */
    private array $bgPriorityBuffer = [];

    /** @var ColorPalette Color palette system (CGB) */
    private readonly ColorPalette $colorPalette;

    /** @var bool CGB mode enabled */
    private bool $cgbMode = false;

    public function __construct(
        private readonly Vram $vram,
        private readonly Oam $oam,
        private readonly FramebufferInterface $framebuffer,
        private readonly InterruptController $interruptController,
    ) {
        // Initialize STAT register with initial mode bits
        $this->stat = $this->mode->getStatBits();

        // Initialize color palette system
        $this->colorPalette = new ColorPalette();
    }

    /**
     * Step the PPU by the specified number of cycles.
     *
     * @param int $cycles Number of cycles to advance
     */
    public function step(int $cycles): void
    {
        // If LCD is disabled, do nothing
        if (!$this->isLcdEnabled()) {
            return;
        }

        $this->dots += $cycles;

        // Keep processing state transitions until all cycles are consumed
        while ($this->dots > 0) {
            $dotsBeforeStep = $this->dots;

            // State machine for PPU modes
            match ($this->mode) {
                PpuMode::OamSearch => $this->stepOamSearch(),
                PpuMode::PixelTransfer => $this->stepPixelTransfer(),
                PpuMode::HBlank => $this->stepHBlank(),
                PpuMode::VBlank => $this->stepVBlank(),
            };

            // If no dots were consumed, break to avoid infinite loop
            if ($this->dots === $dotsBeforeStep) {
                break;
            }
        }
    }

    private function stepOamSearch(): void
    {
        if ($this->dots >= self::OAM_SEARCH_DOTS) {
            $this->dots -= self::OAM_SEARCH_DOTS;
            $this->setMode(PpuMode::PixelTransfer);
        }
    }

    private function stepPixelTransfer(): void
    {
        if ($this->dots >= self::PIXEL_TRANSFER_DOTS) {
            $this->dots -= self::PIXEL_TRANSFER_DOTS;
            $this->renderScanline();
            $this->setMode(PpuMode::HBlank);
        }
    }

    private function stepHBlank(): void
    {
        $hblankDots = self::DOTS_PER_SCANLINE - self::OAM_SEARCH_DOTS - self::PIXEL_TRANSFER_DOTS;
        if ($this->dots >= $hblankDots) {
            $this->dots -= $hblankDots;
            $this->ly++;
            $this->updateLycCoincidence();

            if ($this->ly >= self::VBLANK_SCANLINE) {
                $this->setMode(PpuMode::VBlank);
                $this->interruptController->requestInterrupt(InterruptType::VBlank);
                $this->windowLineCounter = 0;
            } else {
                $this->setMode(PpuMode::OamSearch);
            }
        }
    }

    private function stepVBlank(): void
    {
        if ($this->dots >= self::DOTS_PER_SCANLINE) {
            $this->dots -= self::DOTS_PER_SCANLINE;
            $this->ly++;
            $this->updateLycCoincidence();

            if ($this->ly >= self::SCANLINES_PER_FRAME) {
                $this->ly = 0;
                $this->updateLycCoincidence();
                $this->setMode(PpuMode::OamSearch);

                // Frame complete - present the framebuffer
                $this->framebuffer->present();
            }
        }
    }

    private function setMode(PpuMode $mode): void
    {
        $this->mode = $mode;

        // Update STAT register mode bits
        $this->stat = ($this->stat & ~self::STAT_MODE_MASK) | $mode->getStatBits();

        // Trigger STAT interrupt if enabled for this mode
        $statInterrupt = match ($mode) {
            PpuMode::HBlank => ($this->stat & self::STAT_MODE0_INT) !== 0,
            PpuMode::VBlank => ($this->stat & self::STAT_MODE1_INT) !== 0,
            PpuMode::OamSearch => ($this->stat & self::STAT_MODE2_INT) !== 0,
            PpuMode::PixelTransfer => false,
        };

        if ($statInterrupt) {
            $this->interruptController->requestInterrupt(InterruptType::LcdStat);
        }
    }

    private function updateLycCoincidence(): void
    {
        $coincidence = ($this->ly === $this->lyc);

        if ($coincidence) {
            $this->stat |= self::STAT_LYC_EQUALS;
            if (($this->stat & self::STAT_LYC_INT) !== 0) {
                $this->interruptController->requestInterrupt(InterruptType::LcdStat);
            }
        } else {
            $this->stat &= ~self::STAT_LYC_EQUALS;
        }
    }

    private function renderScanline(): void
    {
        // Initialize scanline buffer and BG color buffer
        $this->scanlineBuffer = array_fill(0, ArrayFramebuffer::WIDTH, Color::fromDmgShade(0));
        $this->bgColorBuffer = array_fill(0, ArrayFramebuffer::WIDTH, 0);
        $this->bgPriorityBuffer = array_fill(0, ArrayFramebuffer::WIDTH, false);

        // Render layers
        if (($this->lcdc & self::LCDC_BG_WINDOW_ENABLE) !== 0) {
            $this->renderBackground();
        }

        if (($this->lcdc & self::LCDC_WINDOW_ENABLE) !== 0) {
            $this->renderWindow();
        }

        if (($this->lcdc & self::LCDC_OBJ_ENABLE) !== 0) {
            $this->renderSprites();
        }

        // Copy scanline buffer to framebuffer
        for ($x = 0; $x < ArrayFramebuffer::WIDTH; $x++) {
            $this->framebuffer->setPixel($x, $this->ly, $this->scanlineBuffer[$x]);
        }
    }

    private function renderBackground(): void
    {
        $vramBank0 = $this->vram->getData(0);
        $vramBank1 = $this->vram->getData(1);
        $tileMapBase = (($this->lcdc & self::LCDC_BG_TILEMAP) !== 0) ? 0x1C00 : 0x1800;
        $tileDataMode = (($this->lcdc & self::LCDC_TILE_DATA) !== 0) ? 0 : 1; // 0=unsigned, 1=signed

        $y = ($this->ly + $this->scy) & 0xFF;
        $tileRow = $y >> 3; // Divide by 8
        $tileY = $y & 7;    // Modulo 8

        for ($x = 0; $x < ArrayFramebuffer::WIDTH; $x++) {
            $bgX = ($x + $this->scx) & 0xFF;
            $tileCol = $bgX >> 3;
            $tileX = $bgX & 7;

            // Get tile index from tile map (bank 0)
            $tileMapAddr = $tileMapBase + ($tileRow * 32) + $tileCol;
            $tileIndex = $vramBank0[$tileMapAddr];

            // Get tile attributes from bank 1 (CGB only)
            $attributes = $this->cgbMode ? $vramBank1[$tileMapAddr] : 0x00;
            $paletteNum = $attributes & 0x07; // Bits 0-2: palette number
            $vramBank = ($attributes & 0x08) !== 0 ? 1 : 0; // Bit 3: VRAM bank
            $xFlip = ($attributes & 0x20) !== 0; // Bit 5: horizontal flip
            $yFlip = ($attributes & 0x40) !== 0; // Bit 6: vertical flip
            $bgPriority = ($attributes & 0x80) !== 0; // Bit 7: BG-to-OAM Priority

            // Apply flips
            $finalTileY = $yFlip ? (7 - $tileY) : $tileY;
            $finalTileX = $xFlip ? (7 - $tileX) : $tileX;

            // Get tile data from appropriate bank
            $vramData = $this->cgbMode ? $this->vram->getData($vramBank) : $vramBank0;
            $tileDataAddr = $this->getTileDataAddress($tileIndex, $tileDataMode);

            // Get pixel color
            $color = $this->getTilePixel($vramData, $tileDataAddr, $finalTileX, $finalTileY);

            // Store raw color and priority flag for sprite priority checking
            $this->bgColorBuffer[$x] = $color;
            $this->bgPriorityBuffer[$x] = $bgPriority;

            // Apply palette
            if ($this->cgbMode) {
                $this->scanlineBuffer[$x] = $this->colorPalette->getBgColor($paletteNum, $color);
            } else {
                $this->scanlineBuffer[$x] = $this->applyPalette($color, $this->bgp);
            }
        }
    }

    private function renderWindow(): void
    {
        // Window is only rendered if WY <= LY and WX < 167
        if ($this->wy > $this->ly || $this->wx >= 167) {
            return;
        }

        $vramBank0 = $this->vram->getData(0);
        $vramBank1 = $this->vram->getData(1);
        $tileMapBase = (($this->lcdc & self::LCDC_WINDOW_TILEMAP) !== 0) ? 0x1C00 : 0x1800;
        $tileDataMode = (($this->lcdc & self::LCDC_TILE_DATA) !== 0) ? 0 : 1;

        $tileRow = $this->windowLineCounter >> 3;
        $tileY = $this->windowLineCounter & 7;

        $windowX = $this->wx - 7; // WX is offset by 7

        for ($x = 0; $x < ArrayFramebuffer::WIDTH; $x++) {
            if ($x < $windowX) {
                continue; // Window hasn't started yet
            }

            $windowPixelX = $x - $windowX;
            $tileCol = $windowPixelX >> 3;
            $tileX = $windowPixelX & 7;

            $tileMapAddr = $tileMapBase + ($tileRow * 32) + $tileCol;
            $tileIndex = $vramBank0[$tileMapAddr];

            // Get tile attributes from bank 1 (CGB only)
            $attributes = $this->cgbMode ? $vramBank1[$tileMapAddr] : 0x00;
            $paletteNum = $attributes & 0x07; // Bits 0-2: palette number
            $vramBank = ($attributes & 0x08) !== 0 ? 1 : 0; // Bit 3: VRAM bank
            $xFlip = ($attributes & 0x20) !== 0; // Bit 5: horizontal flip
            $yFlip = ($attributes & 0x40) !== 0; // Bit 6: vertical flip
            $bgPriority = ($attributes & 0x80) !== 0; // Bit 7: BG-to-OAM Priority

            // Apply flips
            $finalTileY = $yFlip ? (7 - $tileY) : $tileY;
            $finalTileX = $xFlip ? (7 - $tileX) : $tileX;

            // Get tile data from appropriate bank
            $vramData = $this->cgbMode ? $this->vram->getData($vramBank) : $vramBank0;
            $tileDataAddr = $this->getTileDataAddress($tileIndex, $tileDataMode);

            $color = $this->getTilePixel($vramData, $tileDataAddr, $finalTileX, $finalTileY);

            // Store raw color and priority flag for sprite priority checking
            $this->bgColorBuffer[$x] = $color;
            $this->bgPriorityBuffer[$x] = $bgPriority;

            // Apply palette
            if ($this->cgbMode) {
                $this->scanlineBuffer[$x] = $this->colorPalette->getBgColor($paletteNum, $color);
            } else {
                $this->scanlineBuffer[$x] = $this->applyPalette($color, $this->bgp);
            }
        }

        $this->windowLineCounter++;
    }

    private function renderSprites(): void
    {
        $oamData = $this->oam->getData();
        $spriteHeight = (($this->lcdc & self::LCDC_OBJ_SIZE) !== 0) ? 16 : 8;
        $vramData = $this->vram->getData();

        // Sprite attributes stored as [Y, X, Tile, Flags]
        $spritesOnLine = [];

        // OAM search: Find up to 10 sprites on current scanline
        for ($i = 0; $i < 40; $i++) {
            $spriteY = $oamData[$i * 4] - 16; // Y position is offset by 16
            $spriteX = $oamData[$i * 4 + 1] - 8; // X position is offset by 8

            if ($this->ly >= $spriteY && $this->ly < $spriteY + $spriteHeight) {
                $spritesOnLine[] = [
                    'y' => $spriteY,
                    'x' => $spriteX,
                    'tile' => $oamData[$i * 4 + 2],
                    'flags' => $oamData[$i * 4 + 3],
                    'oamIndex' => $i,
                ];

                if (count($spritesOnLine) >= 10) {
                    break;
                }
            }
        }

        // Sort sprites by X coordinate (lower X = higher priority)
        usort($spritesOnLine, fn($a, $b) => $a['x'] <=> $b['x'] ?: $a['oamIndex'] <=> $b['oamIndex']);

        // Render sprites
        foreach ($spritesOnLine as $sprite) {
            $this->renderSprite($sprite, $spriteHeight, $vramData);
        }
    }

    /**
     * @param array{y: int, x: int, tile: int, flags: int, oamIndex: int} $sprite
     * @param array<int, int> $vramData
     */
    private function renderSprite(array $sprite, int $spriteHeight, array $vramData): void
    {
        $spriteY = $sprite['y'];
        $spriteX = $sprite['x'];
        $tileIndex = $sprite['tile'];
        $flags = $sprite['flags'];

        $yFlip = ($flags & 0x40) !== 0;
        $xFlip = ($flags & 0x20) !== 0;
        $behindBg = ($flags & 0x80) !== 0;

        // CGB: bits 0-2 are palette number, bit 3 is VRAM bank
        // DMG: bit 4 selects OBP0 or OBP1
        if ($this->cgbMode) {
            $paletteNum = $flags & 0x07;
            $vramBank = ($flags & 0x08) !== 0 ? 1 : 0;
            $vramData = $this->vram->getData($vramBank);
        } else {
            $palette = ($flags & 0x10) !== 0 ? $this->obp1 : $this->obp0;
        }

        $line = $this->ly - $spriteY;

        // Apply Y-flip first (for 8x16, this swaps tiles AND flips within each)
        if ($yFlip) {
            $line = $spriteHeight - 1 - $line;
        }

        // For 8x16 sprites, use tile index & 0xFE for top half, | 0x01 for bottom half
        // Bit 0 of tile index is ignored for 8x16 objects
        if ($spriteHeight === 16) {
            if ($line >= 8) {
                $tileIndex = ($tileIndex & 0xFE) | 0x01;
                $line -= 8;
            } else {
                $tileIndex = $tileIndex & 0xFE;
            }
        }

        $tileDataAddr = $tileIndex * 16;

        for ($x = 0; $x < 8; $x++) {
            $pixelX = $spriteX + $x;
            if ($pixelX < 0 || $pixelX >= ArrayFramebuffer::WIDTH) {
                continue;
            }

            $tileX = $xFlip ? (7 - $x) : $x;
            $color = $this->getTilePixel($vramData, $tileDataAddr, $tileX, $line);

            // Color 0 is transparent for sprites
            if ($color === 0) {
                continue;
            }

            // Priority handling for CGB mode
            // See Pan Docs and cgb-acid2 README for priority logic
            if ($this->cgbMode && $this->bgColorBuffer[$pixelX] !== 0) {
                // Both sprite and BG have non-zero colors, need to check priority
                $masterPriorityEnabled = ($this->lcdc & self::LCDC_BG_WINDOW_ENABLE) !== 0;

                if (!$masterPriorityEnabled) {
                    // Master Priority disabled: sprites always on top
                    // (used by cgb-acid2 nose section)
                } elseif ($this->bgPriorityBuffer[$pixelX]) {
                    // BG-to-OAM Priority set: BG has priority over sprites
                    continue;
                } elseif ($behindBg) {
                    // OBJ-to-BG Priority set: sprite is behind BG
                    continue;
                }
                // Otherwise: sprite is drawn on top
            } elseif (!$this->cgbMode && $behindBg && $this->bgColorBuffer[$pixelX] !== 0) {
                // DMG mode: simple priority check
                // If sprite has OBJ-to-BG Priority set and BG is not color 0, hide sprite
                continue;
            }

            if ($this->cgbMode) {
                $this->scanlineBuffer[$pixelX] = $this->colorPalette->getObjColor($paletteNum, $color);
            } else {
                $this->scanlineBuffer[$pixelX] = $this->applyPalette($color, $palette);
            }
        }
    }

    private function getTileDataAddress(int $tileIndex, int $mode): int
    {
        if ($mode === 0) {
            // Unsigned mode: tiles 0-255 at 0x8000-0x8FFF
            return $tileIndex * 16;
        } else {
            // Signed mode: tiles -128 to 127 at 0x9000-0x8800
            $signed = ($tileIndex < 128) ? $tileIndex : $tileIndex - 256;
            return 0x1000 + ($signed * 16);
        }
    }

    /**
     * Get a pixel color (0-3) from tile data.
     *
     * @param array<int, int> $vramData
     */
    private function getTilePixel(array $vramData, int $tileDataAddr, int $x, int $y): int
    {
        $byteOffset = $tileDataAddr + ($y * 2);
        $byte1 = $vramData[$byteOffset];
        $byte2 = $vramData[$byteOffset + 1];

        $bitPos = 7 - $x;
        $lowBit = ($byte1 >> $bitPos) & 1;
        $highBit = ($byte2 >> $bitPos) & 1;

        return ($highBit << 1) | $lowBit;
    }

    private function applyPalette(int $color, int $palette): Color
    {
        $shade = ($palette >> ($color * 2)) & 0x03;
        return Color::fromDmgShade($shade);
    }

    private function isLcdEnabled(): bool
    {
        return ($this->lcdc & self::LCDC_LCD_ENABLE) !== 0;
    }

    /**
     * Enable CGB mode (called during system initialization).
     */
    public function enableCgbMode(bool $enabled): void
    {
        $this->cgbMode = $enabled;
    }

    /**
     * Check if CGB mode is enabled.
     */
    public function isCgbMode(): bool
    {
        return $this->cgbMode;
    }

    // DeviceInterface implementation for I/O registers
    public function readByte(int $address): int
    {
        return match ($address) {
            self::LCDC => $this->lcdc,
            self::STAT => $this->stat | 0x80, // Bit 7 always set
            self::SCY => $this->scy,
            self::SCX => $this->scx,
            self::LY => $this->ly,
            self::LYC => $this->lyc,
            self::BGP => $this->bgp,
            self::OBP0 => $this->obp0,
            self::OBP1 => $this->obp1,
            self::WY => $this->wy,
            self::WX => $this->wx,
            self::BCPS => $this->colorPalette->readBgIndex(),
            self::BCPD => $this->colorPalette->readBgData(),
            self::OCPS => $this->colorPalette->readObjIndex(),
            self::OCPD => $this->colorPalette->readObjData(),
            default => 0xFF,
        };
    }

    public function writeByte(int $address, int $value): void
    {
        match ($address) {
            self::LCDC => $this->lcdc = $value,
            self::STAT => $this->stat = ($value & 0xF8) | ($this->stat & 0x07), // Bits 0-2 read-only
            self::SCY => $this->scy = $value,
            self::SCX => $this->scx = $value,
            self::LY => null, // Read-only
            self::LYC => $this->lyc = $value,
            self::BGP => $this->bgp = $value,
            self::OBP0 => $this->obp0 = $value,
            self::OBP1 => $this->obp1 = $value,
            self::WY => $this->wy = $value,
            self::WX => $this->wx = $value,
            self::BCPS => $this->colorPalette->writeBgIndex($value),
            self::BCPD => $this->colorPalette->writeBgData($value),
            self::OCPS => $this->colorPalette->writeObjIndex($value),
            self::OCPD => $this->colorPalette->writeObjData($value),
            default => null,
        };

        // Re-check LYC coincidence after LYC write
        if ($address === self::LYC) {
            $this->updateLycCoincidence();
        }
    }

    // Savestate support methods

    public function getMode(): PpuMode
    {
        return $this->mode;
    }

    public function restoreMode(PpuMode $mode): void
    {
        $this->mode = $mode;
    }

    public function getModeClock(): int
    {
        return $this->dots;
    }

    public function setModeClock(int $dots): void
    {
        $this->dots = $dots;
    }

    public function getLY(): int
    {
        return $this->ly;
    }

    public function setLY(int $ly): void
    {
        $this->ly = $ly;
    }

    public function getLYC(): int
    {
        return $this->lyc;
    }

    public function setLYC(int $lyc): void
    {
        $this->lyc = $lyc;
    }

    public function getSCX(): int
    {
        return $this->scx;
    }

    public function setSCX(int $scx): void
    {
        $this->scx = $scx;
    }

    public function getSCY(): int
    {
        return $this->scy;
    }

    public function setSCY(int $scy): void
    {
        $this->scy = $scy;
    }

    public function getWX(): int
    {
        return $this->wx;
    }

    public function setWX(int $wx): void
    {
        $this->wx = $wx;
    }

    public function getWY(): int
    {
        return $this->wy;
    }

    public function setWY(int $wy): void
    {
        $this->wy = $wy;
    }

    public function getLCDC(): int
    {
        return $this->lcdc;
    }

    public function setLCDC(int $lcdc): void
    {
        $this->lcdc = $lcdc;
    }

    public function getSTAT(): int
    {
        return $this->stat;
    }

    public function setSTAT(int $stat): void
    {
        $this->stat = $stat;
    }

    public function getBGP(): int
    {
        return $this->bgp;
    }

    public function setBGP(int $bgp): void
    {
        $this->bgp = $bgp;
    }

    public function getOBP0(): int
    {
        return $this->obp0;
    }

    public function setOBP0(int $obp0): void
    {
        $this->obp0 = $obp0;
    }

    public function getOBP1(): int
    {
        return $this->obp1;
    }

    public function setOBP1(int $obp1): void
    {
        $this->obp1 = $obp1;
    }
}
