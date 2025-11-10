<?php

declare(strict_types=1);

namespace Tests\Unit\Cpu\Register;

use Gb\Cpu\Register\FlagRegister;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FlagRegisterTest extends TestCase
{
    #[Test]
    public function it_initializes_with_default_value(): void
    {
        $flags = new FlagRegister();
        $this->assertSame(0x00, $flags->get());
        $this->assertFalse($flags->getZero());
        $this->assertFalse($flags->getSubtract());
        $this->assertFalse($flags->getHalfCarry());
        $this->assertFalse($flags->getCarry());
    }

    #[Test]
    public function it_initializes_with_specified_value(): void
    {
        $flags = new FlagRegister(0xF0);
        $this->assertSame(0xF0, $flags->get());
    }

    #[Test]
    public function it_masks_initialization_to_lower_nibble(): void
    {
        $flags = new FlagRegister(0xFF);
        $this->assertSame(0xF0, $flags->get());
    }

    #[Test]
    public function it_masks_set_to_lower_nibble(): void
    {
        $flags = new FlagRegister();
        $flags->set(0xFF);
        $this->assertSame(0xF0, $flags->get());
    }

    #[Test]
    public function it_sets_and_gets_zero_flag(): void
    {
        $flags = new FlagRegister();
        $flags->setZero(true);
        $this->assertTrue($flags->getZero());
        $this->assertSame(0x80, $flags->get());
    }

    #[Test]
    public function it_clears_zero_flag(): void
    {
        $flags = new FlagRegister(0xFF);
        $flags->setZero(false);
        $this->assertFalse($flags->getZero());
        $this->assertSame(0x70, $flags->get());
    }

    #[Test]
    public function it_sets_and_gets_subtract_flag(): void
    {
        $flags = new FlagRegister();
        $flags->setSubtract(true);
        $this->assertTrue($flags->getSubtract());
        $this->assertSame(0x40, $flags->get());
    }

    #[Test]
    public function it_clears_subtract_flag(): void
    {
        $flags = new FlagRegister(0xFF);
        $flags->setSubtract(false);
        $this->assertFalse($flags->getSubtract());
        $this->assertSame(0xB0, $flags->get());
    }

    #[Test]
    public function it_sets_and_gets_half_carry_flag(): void
    {
        $flags = new FlagRegister();
        $flags->setHalfCarry(true);
        $this->assertTrue($flags->getHalfCarry());
        $this->assertSame(0x20, $flags->get());
    }

    #[Test]
    public function it_clears_half_carry_flag(): void
    {
        $flags = new FlagRegister(0xFF);
        $flags->setHalfCarry(false);
        $this->assertFalse($flags->getHalfCarry());
        $this->assertSame(0xD0, $flags->get());
    }

    #[Test]
    public function it_sets_and_gets_carry_flag(): void
    {
        $flags = new FlagRegister();
        $flags->setCarry(true);
        $this->assertTrue($flags->getCarry());
        $this->assertSame(0x10, $flags->get());
    }

    #[Test]
    public function it_clears_carry_flag(): void
    {
        $flags = new FlagRegister(0xFF);
        $flags->setCarry(false);
        $this->assertFalse($flags->getCarry());
        $this->assertSame(0xE0, $flags->get());
    }

    #[Test]
    public function it_sets_multiple_flags_independently(): void
    {
        $flags = new FlagRegister();
        $flags->setZero(true);
        $flags->setCarry(true);

        $this->assertTrue($flags->getZero());
        $this->assertFalse($flags->getSubtract());
        $this->assertFalse($flags->getHalfCarry());
        $this->assertTrue($flags->getCarry());
        $this->assertSame(0x90, $flags->get());
    }

    #[Test]
    public function it_resets_all_flags_with_clear(): void
    {
        $flags = new FlagRegister(0xFF);
        $flags->clear();

        $this->assertSame(0x00, $flags->get());
        $this->assertFalse($flags->getZero());
        $this->assertFalse($flags->getSubtract());
        $this->assertFalse($flags->getHalfCarry());
        $this->assertFalse($flags->getCarry());
    }

    #[Test]
    public function it_maintains_correct_flag_bit_positions(): void
    {
        $flags = new FlagRegister();

        // Test each flag's bit position individually
        $flags->setZero(true);
        $this->assertSame(0x80, $flags->get(), 'Zero flag should be at bit 7');

        $flags->clear();
        $flags->setSubtract(true);
        $this->assertSame(0x40, $flags->get(), 'Subtract flag should be at bit 6');

        $flags->clear();
        $flags->setHalfCarry(true);
        $this->assertSame(0x20, $flags->get(), 'Half carry flag should be at bit 5');

        $flags->clear();
        $flags->setCarry(true);
        $this->assertSame(0x10, $flags->get(), 'Carry flag should be at bit 4');
    }
}
