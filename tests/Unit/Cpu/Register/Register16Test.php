<?php

declare(strict_types=1);

namespace Tests\Unit\Cpu\Register;

use Gb\Cpu\Register\Register16;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Register16Test extends TestCase
{
    #[Test]
    public function it_initializes_with_default_value(): void
    {
        $register = new Register16();
        $this->assertSame(0x0000, $register->get());
    }

    #[Test]
    public function it_initializes_with_specified_value(): void
    {
        $register = new Register16(0xABCD);
        $this->assertSame(0xABCD, $register->get());
    }

    #[Test]
    public function it_masks_initialization_to_16_bits(): void
    {
        $register = new Register16(0x1FFFF);
        $this->assertSame(0xFFFF, $register->get());
    }

    #[Test]
    public function it_stores_value_with_set(): void
    {
        $register = new Register16();
        $register->set(0x1234);
        $this->assertSame(0x1234, $register->get());
    }

    #[Test]
    public function it_masks_set_value_to_16_bits(): void
    {
        $register = new Register16();
        $register->set(0x1ABCD);
        $this->assertSame(0xABCD, $register->get());
    }

    #[Test]
    public function it_returns_upper_byte_with_get_high(): void
    {
        $register = new Register16(0xABCD);
        $this->assertSame(0xAB, $register->getHigh());
    }

    #[Test]
    public function it_returns_lower_byte_with_get_low(): void
    {
        $register = new Register16(0xABCD);
        $this->assertSame(0xCD, $register->getLow());
    }

    #[Test]
    public function it_updates_upper_byte_with_set_high(): void
    {
        $register = new Register16(0x00CD);
        $register->setHigh(0xAB);
        $this->assertSame(0xABCD, $register->get());
    }

    #[Test]
    public function it_updates_lower_byte_with_set_low(): void
    {
        $register = new Register16(0xAB00);
        $register->setLow(0xCD);
        $this->assertSame(0xABCD, $register->get());
    }

    #[Test]
    public function it_masks_set_high_to_8_bits(): void
    {
        $register = new Register16(0x0000);
        $register->setHigh(0x1AB);
        $this->assertSame(0xAB00, $register->get());
    }

    #[Test]
    public function it_masks_set_low_to_8_bits(): void
    {
        $register = new Register16(0x0000);
        $register->setLow(0x1CD);
        $this->assertSame(0x00CD, $register->get());
    }

    #[Test]
    public function it_increments_by_one(): void
    {
        $register = new Register16(0x1234);
        $register->increment();
        $this->assertSame(0x1235, $register->get());
    }

    #[Test]
    public function it_wraps_increment_at_boundary(): void
    {
        $register = new Register16(0xFFFF);
        $register->increment();
        $this->assertSame(0x0000, $register->get());
    }

    #[Test]
    public function it_decrements_by_one(): void
    {
        $register = new Register16(0x1234);
        $register->decrement();
        $this->assertSame(0x1233, $register->get());
    }

    #[Test]
    public function it_wraps_decrement_at_boundary(): void
    {
        $register = new Register16(0x0000);
        $register->decrement();
        $this->assertSame(0xFFFF, $register->get());
    }
}
