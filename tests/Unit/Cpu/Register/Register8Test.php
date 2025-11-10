<?php

declare(strict_types=1);

namespace Tests\Unit\Cpu\Register;

use Gb\Cpu\Register\Register8;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Register8Test extends TestCase
{
    #[Test]
    public function it_initializes_with_default_value(): void
    {
        $register = new Register8();
        $this->assertSame(0x00, $register->get());
    }

    #[Test]
    public function it_initializes_with_specified_value(): void
    {
        $register = new Register8(0xAB);
        $this->assertSame(0xAB, $register->get());
    }

    #[Test]
    public function it_masks_initialization_to_8_bits(): void
    {
        $register = new Register8(0x1FF);
        $this->assertSame(0xFF, $register->get());
    }

    #[Test]
    public function it_stores_value_with_set(): void
    {
        $register = new Register8();
        $register->set(0x42);
        $this->assertSame(0x42, $register->get());
    }

    #[Test]
    public function it_masks_set_value_to_8_bits(): void
    {
        $register = new Register8();
        $register->set(0x1AB);
        $this->assertSame(0xAB, $register->get());
    }

    #[Test]
    public function it_increments_by_one(): void
    {
        $register = new Register8(0x05);
        $register->increment();
        $this->assertSame(0x06, $register->get());
    }

    #[Test]
    public function it_wraps_increment_at_boundary(): void
    {
        $register = new Register8(0xFF);
        $register->increment();
        $this->assertSame(0x00, $register->get());
    }

    #[Test]
    public function it_decrements_by_one(): void
    {
        $register = new Register8(0x05);
        $register->decrement();
        $this->assertSame(0x04, $register->get());
    }

    #[Test]
    public function it_wraps_decrement_at_boundary(): void
    {
        $register = new Register8(0x00);
        $register->decrement();
        $this->assertSame(0xFF, $register->get());
    }
}
