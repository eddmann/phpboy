<?php

declare(strict_types=1);

namespace Tests\Unit\Ppu;

use Gb\Interrupts\InterruptController;
use Gb\Interrupts\InterruptType;
use Gb\Memory\Vram;
use Gb\Ppu\ArrayFramebuffer;
use Gb\Ppu\Oam;
use Gb\Ppu\Ppu;
use PHPUnit\Framework\TestCase;

final class PpuTest extends TestCase
{
    private Ppu $ppu;
    private Vram $vram;
    private Oam $oam;
    private ArrayFramebuffer $framebuffer;
    private InterruptController $interruptController;

    protected function setUp(): void
    {
        $this->vram = new Vram();
        $this->oam = new Oam();
        $this->framebuffer = new ArrayFramebuffer();
        $this->interruptController = new InterruptController();
        $this->ppu = new Ppu($this->vram, $this->oam, $this->framebuffer, $this->interruptController);
    }

    public function testInitialState(): void
    {
        // LCDC should be initialized with LCD enabled
        $lcdc = $this->ppu->readByte(0xFF40);
        $this->assertNotEquals(0, $lcdc & 0x80, 'LCD should be enabled by default');

        // LY should start at 0
        $this->assertEquals(0, $this->ppu->readByte(0xFF44), 'LY should start at 0');

        // STAT should indicate mode 2 (OAM Search)
        $stat = $this->ppu->readByte(0xFF41);
        $this->assertEquals(2, $stat & 0x03, 'Initial mode should be OAM Search (mode 2)');
    }

    public function testModeTransitionOamSearchToPixelTransfer(): void
    {
        // Step through OAM search (80 dots)
        $this->ppu->step(80);

        // Should now be in pixel transfer mode (mode 3)
        $stat = $this->ppu->readByte(0xFF41);
        $this->assertEquals(3, $stat & 0x03, 'Should transition to Pixel Transfer (mode 3)');
    }

    public function testModeTransitionPixelTransferToHBlank(): void
    {
        // Step through OAM search (80 dots) and pixel transfer (172 dots)
        $this->ppu->step(80 + 172);

        // Should now be in H-Blank mode (mode 0)
        $stat = $this->ppu->readByte(0xFF41);
        $this->assertEquals(0, $stat & 0x03, 'Should transition to H-Blank (mode 0)');
    }

    public function testScanlineIncrement(): void
    {
        $this->assertEquals(0, $this->ppu->readByte(0xFF44), 'LY should start at 0');

        // Complete one scanline (456 dots)
        $this->ppu->step(456);

        $this->assertEquals(1, $this->ppu->readByte(0xFF44), 'LY should increment after scanline');
    }

    public function testVBlankEntry(): void
    {
        // Step through 144 scanlines to reach V-Blank
        for ($i = 0; $i < 144; $i++) {
            $this->ppu->step(456);
        }

        // Should now be in V-Blank mode (mode 1)
        $stat = $this->ppu->readByte(0xFF41);
        $this->assertEquals(1, $stat & 0x03, 'Should be in V-Blank (mode 1)');

        // LY should be 144
        $this->assertEquals(144, $this->ppu->readByte(0xFF44), 'LY should be 144 at V-Blank start');

        // V-Blank interrupt should be requested
        $this->assertNotNull(
            $this->interruptController->getPendingInterrupt(),
            'V-Blank interrupt should be requested'
        );
        $this->assertEquals(
            InterruptType::VBlank,
            $this->interruptController->getPendingInterrupt(),
            'Pending interrupt should be VBlank'
        );
    }

    public function testVBlankDuration(): void
    {
        // Step to V-Blank
        $this->ppu->step(144 * 456);

        $this->assertEquals(144, $this->ppu->readByte(0xFF44));

        // Step through V-Blank (10 scanlines)
        for ($i = 0; $i < 10; $i++) {
            $this->ppu->step(456);
            $expectedLY = 144 + $i + 1;
            if ($expectedLY >= 154) {
                $expectedLY = 0; // Wraps back to 0
            }
            $this->assertEquals($expectedLY, $this->ppu->readByte(0xFF44));
        }

        // After V-Blank, should return to mode 2 (OAM Search)
        $stat = $this->ppu->readByte(0xFF41);
        $this->assertEquals(2, $stat & 0x03, 'Should return to OAM Search after V-Blank');
    }

    public function testLycCoincidence(): void
    {
        // Set LYC to 5
        $this->ppu->writeByte(0xFF45, 5);

        // Step to scanline 5
        $this->ppu->step(5 * 456);

        $this->assertEquals(5, $this->ppu->readByte(0xFF44), 'LY should be 5');

        // STAT bit 2 should be set (LYC=LY)
        $stat = $this->ppu->readByte(0xFF41);
        $this->assertNotEquals(0, $stat & 0x04, 'STAT LYC=LY flag should be set');
    }

    public function testLycCoincidenceInterrupt(): void
    {
        // Enable LYC=LY interrupt
        $this->ppu->writeByte(0xFF41, 0x40); // Set bit 6

        // Set LYC to 10
        $this->ppu->writeByte(0xFF45, 10);

        // Step to scanline 10
        $this->ppu->step(10 * 456);

        // STAT interrupt should be requested
        $this->assertNotNull(
            $this->interruptController->getPendingInterrupt(),
            'STAT interrupt should be requested'
        );
    }

    public function testStatMode0Interrupt(): void
    {
        // Enable Mode 0 (H-Blank) interrupt
        $this->ppu->writeByte(0xFF41, 0x08); // Set bit 3

        // Step to H-Blank
        $this->ppu->step(80 + 172);

        // STAT interrupt should be requested
        $this->assertNotNull(
            $this->interruptController->getPendingInterrupt(),
            'STAT interrupt should be requested for Mode 0'
        );
    }

    public function testStatMode1Interrupt(): void
    {
        // Enable Mode 1 (V-Blank) interrupt
        $this->ppu->writeByte(0xFF41, 0x10); // Set bit 4

        // Step to V-Blank
        $this->ppu->step(144 * 456);

        // STAT interrupt should be requested (in addition to V-Blank interrupt)
        $this->assertNotNull(
            $this->interruptController->getPendingInterrupt(),
            'STAT interrupt should be requested for Mode 1'
        );
    }

    public function testStatMode2Interrupt(): void
    {
        // Clear any pending interrupts first by completing a scanline
        $this->ppu->step(456);

        // Enable Mode 2 (OAM Search) interrupt
        $this->ppu->writeByte(0xFF41, 0x20); // Set bit 5

        // Step to next scanline (should trigger Mode 2 again)
        $this->ppu->step(456);

        // STAT interrupt should be requested
        $this->assertNotNull(
            $this->interruptController->getPendingInterrupt(),
            'STAT interrupt should be requested for Mode 2'
        );
    }

    public function testLcdDisable(): void
    {
        // Disable LCD
        $this->ppu->writeByte(0xFF40, 0x00);

        // Step some cycles
        $this->ppu->step(1000);

        // LY should remain 0 when LCD is disabled
        $this->assertEquals(0, $this->ppu->readByte(0xFF44), 'LY should not advance when LCD is disabled');
    }

    public function testScrollRegisters(): void
    {
        $this->ppu->writeByte(0xFF42, 0x12); // SCY
        $this->ppu->writeByte(0xFF43, 0x34); // SCX

        $this->assertEquals(0x12, $this->ppu->readByte(0xFF42), 'SCY should be readable');
        $this->assertEquals(0x34, $this->ppu->readByte(0xFF43), 'SCX should be readable');
    }

    public function testWindowRegisters(): void
    {
        $this->ppu->writeByte(0xFF4A, 0x56); // WY
        $this->ppu->writeByte(0xFF4B, 0x78); // WX

        $this->assertEquals(0x56, $this->ppu->readByte(0xFF4A), 'WY should be readable');
        $this->assertEquals(0x78, $this->ppu->readByte(0xFF4B), 'WX should be readable');
    }

    public function testPaletteRegisters(): void
    {
        $this->ppu->writeByte(0xFF47, 0xE4); // BGP
        $this->ppu->writeByte(0xFF48, 0xD2); // OBP0
        $this->ppu->writeByte(0xFF49, 0xA9); // OBP1

        $this->assertEquals(0xE4, $this->ppu->readByte(0xFF47), 'BGP should be readable');
        $this->assertEquals(0xD2, $this->ppu->readByte(0xFF48), 'OBP0 should be readable');
        $this->assertEquals(0xA9, $this->ppu->readByte(0xFF49), 'OBP1 should be readable');
    }

    public function testLyIsReadOnly(): void
    {
        // Try to write to LY (should be ignored)
        $this->ppu->writeByte(0xFF44, 0xFF);

        // LY should remain at its current value (0)
        $this->assertEquals(0, $this->ppu->readByte(0xFF44), 'LY should be read-only');
    }

    public function testStatBit7AlwaysSet(): void
    {
        // Bit 7 of STAT is always set when reading
        $this->ppu->writeByte(0xFF41, 0x00);

        $stat = $this->ppu->readByte(0xFF41);
        $this->assertNotEquals(0, $stat & 0x80, 'STAT bit 7 should always be set');
    }

    public function testFullFrameTiming(): void
    {
        // One full frame: 154 scanlines × 456 dots = 70224 dots
        $this->ppu->step(70224);

        // Should be back at LY=0, mode 2
        $this->assertEquals(0, $this->ppu->readByte(0xFF44), 'LY should wrap back to 0');

        $stat = $this->ppu->readByte(0xFF41);
        $this->assertEquals(2, $stat & 0x03, 'Should be back in OAM Search mode');
    }

    public function testBackgroundRendering(): void
    {
        // Set up a simple tile in VRAM (all white pixels = color 0)
        $vramData = $this->vram->getData();
        for ($i = 0; $i < 16; $i += 2) {
            $this->vram->writeByte($i, 0x00);     // Low bits
            $this->vram->writeByte($i + 1, 0x00); // High bits
        }

        // Set tile map entry 0 to use tile 0
        $this->vram->writeByte(0x1800, 0x00);

        // Enable BG
        $this->ppu->writeByte(0xFF40, 0x81); // LCD on, BG on

        // Render first scanline
        $this->ppu->step(456);

        // Framebuffer should have pixels (not testing exact colors, just that rendering happened)
        $buffer = $this->framebuffer->getFramebuffer();
        $this->assertNotEmpty($buffer, 'Framebuffer should not be empty');
        $this->assertCount(144, $buffer, 'Framebuffer should have 144 rows');
        $this->assertCount(160, $buffer[0], 'Each row should have 160 pixels');
    }
}
