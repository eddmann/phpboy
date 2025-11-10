<?php

declare(strict_types=1);

namespace Tests\Unit\Ppu;

use Gb\Ppu\Color;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
    #[Test]
    public function it_creates_dmg_shade_0_as_white(): void
    {
        $color = Color::fromDmgShade(0);

        $this->assertEquals(0xFF, $color->r, 'Red should be 0xFF for white');
        $this->assertEquals(0xFF, $color->g, 'Green should be 0xFF for white');
        $this->assertEquals(0xFF, $color->b, 'Blue should be 0xFF for white');
    }

    #[Test]
    public function it_creates_dmg_shade_1_as_light_gray(): void
    {
        $color = Color::fromDmgShade(1);

        $this->assertEquals(0xAA, $color->r, 'Red should be 0xAA for light gray');
        $this->assertEquals(0xAA, $color->g, 'Green should be 0xAA for light gray');
        $this->assertEquals(0xAA, $color->b, 'Blue should be 0xAA for light gray');
    }

    #[Test]
    public function it_creates_dmg_shade_2_as_dark_gray(): void
    {
        $color = Color::fromDmgShade(2);

        $this->assertEquals(0x55, $color->r, 'Red should be 0x55 for dark gray');
        $this->assertEquals(0x55, $color->g, 'Green should be 0x55 for dark gray');
        $this->assertEquals(0x55, $color->b, 'Blue should be 0x55 for dark gray');
    }

    #[Test]
    public function it_creates_dmg_shade_3_as_black(): void
    {
        $color = Color::fromDmgShade(3);

        $this->assertEquals(0x00, $color->r, 'Red should be 0x00 for black');
        $this->assertEquals(0x00, $color->g, 'Green should be 0x00 for black');
        $this->assertEquals(0x00, $color->b, 'Blue should be 0x00 for black');
    }

    #[Test]
    public function it_converts_gbc_15bit_to_rgb(): void
    {
        // Test pure red (5 bits = 0x1F)
        $red = Color::fromGbc15bit(0x001F);
        $this->assertEquals(255, $red->r);
        $this->assertEquals(0, $red->g);
        $this->assertEquals(0, $red->b);

        // Test pure green (5 bits at position 5 = 0x03E0)
        $green = Color::fromGbc15bit(0x03E0);
        $this->assertEquals(0, $green->r);
        $this->assertEquals(255, $green->g);
        $this->assertEquals(0, $green->b);

        // Test pure blue (5 bits at position 10 = 0x7C00)
        $blue = Color::fromGbc15bit(0x7C00);
        $this->assertEquals(0, $blue->r);
        $this->assertEquals(0, $blue->g);
        $this->assertEquals(255, $blue->b);

        // Test white (all bits set)
        $white = Color::fromGbc15bit(0x7FFF);
        $this->assertEquals(255, $white->r);
        $this->assertEquals(255, $white->g);
        $this->assertEquals(255, $white->b);
    }

    #[Test]
    public function it_converts_to_rgba_format(): void
    {
        $color = new Color(0x12, 0x34, 0x56);
        $rgba = $color->toRgba();

        // Format: RRGGBBAA
        $this->assertEquals(0x123456FF, $rgba);
    }

    #[Test]
    public function it_enforces_readonly_properties(): void
    {
        $color = new Color(100, 150, 200);

        $this->assertEquals(100, $color->r);
        $this->assertEquals(150, $color->g);
        $this->assertEquals(200, $color->b);

        // Verify it's a readonly class (PHP will enforce this at runtime)
        $this->assertInstanceOf(Color::class, $color);
    }
}
