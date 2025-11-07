<?php

declare(strict_types=1);

namespace Tests\Unit\Ppu;

use Gb\Ppu\ArrayFramebuffer;
use Gb\Ppu\Color;
use PHPUnit\Framework\TestCase;

final class ArrayFramebufferTest extends TestCase
{
    private ArrayFramebuffer $framebuffer;

    protected function setUp(): void
    {
        $this->framebuffer = new ArrayFramebuffer();
    }

    public function testInitialState(): void
    {
        $buffer = $this->framebuffer->getFramebuffer();

        $this->assertCount(144, $buffer, 'Framebuffer should have 144 rows');
        $this->assertCount(160, $buffer[0], 'Each row should have 160 pixels');

        // All pixels should be white initially
        $white = Color::fromDmgShade(0);
        $this->assertEquals($white, $buffer[0][0]);
    }

    public function testSetPixel(): void
    {
        $testColor = new Color(100, 150, 200);

        $this->framebuffer->setPixel(10, 20, $testColor);

        $buffer = $this->framebuffer->getFramebuffer();
        $this->assertEquals($testColor, $buffer[20][10]);
    }

    public function testSetPixelOutOfBounds(): void
    {
        $testColor = new Color(100, 150, 200);

        // These should not crash or throw exceptions
        $this->framebuffer->setPixel(-1, 0, $testColor);
        $this->framebuffer->setPixel(0, -1, $testColor);
        $this->framebuffer->setPixel(160, 0, $testColor);
        $this->framebuffer->setPixel(0, 144, $testColor);

        // Verify framebuffer is still intact
        $buffer = $this->framebuffer->getFramebuffer();
        $this->assertCount(144, $buffer);
    }

    public function testClear(): void
    {
        // Set a pixel
        $testColor = new Color(100, 150, 200);
        $this->framebuffer->setPixel(10, 20, $testColor);

        // Clear the framebuffer
        $this->framebuffer->clear();

        // All pixels should be white again
        $buffer = $this->framebuffer->getFramebuffer();
        $white = Color::fromDmgShade(0);
        $this->assertEquals($white, $buffer[20][10]);
        $this->assertEquals($white, $buffer[0][0]);
    }

    public function testFullFrameRender(): void
    {
        $colors = [
            Color::fromDmgShade(0),
            Color::fromDmgShade(1),
            Color::fromDmgShade(2),
            Color::fromDmgShade(3),
        ];

        // Fill entire framebuffer with pattern
        for ($y = 0; $y < 144; $y++) {
            for ($x = 0; $x < 160; $x++) {
                $colorIndex = ($x + $y) % 4;
                $this->framebuffer->setPixel($x, $y, $colors[$colorIndex]);
            }
        }

        // Verify pattern
        $buffer = $this->framebuffer->getFramebuffer();
        for ($y = 0; $y < 144; $y++) {
            for ($x = 0; $x < 160; $x++) {
                $colorIndex = ($x + $y) % 4;
                $this->assertEquals($colors[$colorIndex], $buffer[$y][$x]);
            }
        }
    }

    public function testDimensions(): void
    {
        $this->assertEquals(160, ArrayFramebuffer::WIDTH);
        $this->assertEquals(144, ArrayFramebuffer::HEIGHT);
    }
}
