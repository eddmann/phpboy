<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Gb\Support\BitOps;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BitOpsTest extends TestCase
{
    #[Test]
    public function it_returns_true_when_bit_is_set(): void
    {
        $this->assertTrue(BitOps::getBit(0b10000000, 7));
        $this->assertTrue(BitOps::getBit(0b00000001, 0));
        $this->assertTrue(BitOps::getBit(0b00010000, 4));
    }

    #[Test]
    public function it_returns_false_when_bit_is_not_set(): void
    {
        $this->assertFalse(BitOps::getBit(0b01111111, 7));
        $this->assertFalse(BitOps::getBit(0b11111110, 0));
        $this->assertFalse(BitOps::getBit(0b11101111, 4));
    }

    #[Test]
    public function it_sets_specified_bit(): void
    {
        $result = BitOps::setBit(0b00000000, 7, true);
        $this->assertSame(0b10000000, $result);

        $result = BitOps::setBit(0b00000000, 0, true);
        $this->assertSame(0b00000001, $result);

        $result = BitOps::setBit(0b10101010, 0, true);
        $this->assertSame(0b10101011, $result);
    }

    #[Test]
    public function it_clears_specified_bit(): void
    {
        $result = BitOps::setBit(0b11111111, 7, false);
        $this->assertSame(0b01111111, $result);

        $result = BitOps::setBit(0b11111111, 0, false);
        $this->assertSame(0b11111110, $result);

        $result = BitOps::setBit(0b10101011, 0, false);
        $this->assertSame(0b10101010, $result);
    }

    #[Test]
    public function it_rotates_left_with_carry_clear(): void
    {
        [$result, $carry] = BitOps::rotateLeft(0b01010101, false);
        $this->assertSame(0b10101010, $result);
        $this->assertFalse($carry);
    }

    #[Test]
    public function it_rotates_left_with_carry_set(): void
    {
        [$result, $carry] = BitOps::rotateLeft(0b01010101, true);
        $this->assertSame(0b10101011, $result);
        $this->assertFalse($carry);
    }

    #[Test]
    public function it_sets_carry_when_bit_7_is_set_on_rotate_left(): void
    {
        [$result, $carry] = BitOps::rotateLeft(0b10000000, false);
        $this->assertSame(0b00000000, $result);
        $this->assertTrue($carry);
    }

    #[Test]
    public function it_rotates_right_with_carry_clear(): void
    {
        [$result, $carry] = BitOps::rotateRight(0b10101010, false);
        $this->assertSame(0b01010101, $result);
        $this->assertFalse($carry);
    }

    #[Test]
    public function it_rotates_right_with_carry_set(): void
    {
        [$result, $carry] = BitOps::rotateRight(0b10101010, true);
        $this->assertSame(0b11010101, $result);
        $this->assertFalse($carry);
    }

    #[Test]
    public function it_sets_carry_when_bit_0_is_set_on_rotate_right(): void
    {
        [$result, $carry] = BitOps::rotateRight(0b00000001, false);
        $this->assertSame(0b00000000, $result);
        $this->assertTrue($carry);
    }

    #[Test]
    public function it_shifts_left_correctly(): void
    {
        [$result, $carry] = BitOps::shiftLeft(0b01010101);
        $this->assertSame(0b10101010, $result);
        $this->assertFalse($carry);
    }

    #[Test]
    public function it_sets_carry_when_bit_7_is_set_on_shift_left(): void
    {
        [$result, $carry] = BitOps::shiftLeft(0b10000000);
        $this->assertSame(0b00000000, $result);
        $this->assertTrue($carry);
    }

    #[Test]
    public function it_shifts_right_logically_correctly(): void
    {
        [$result, $carry] = BitOps::shiftRight(0b10101010, false);
        $this->assertSame(0b01010101, $result);
        $this->assertFalse($carry);
    }

    #[Test]
    public function it_sets_carry_when_bit_0_is_set_on_logical_shift_right(): void
    {
        [$result, $carry] = BitOps::shiftRight(0b00000001, false);
        $this->assertSame(0b00000000, $result);
        $this->assertTrue($carry);
    }

    #[Test]
    public function it_preserves_bit_7_on_arithmetic_shift_right(): void
    {
        [$result, $carry] = BitOps::shiftRight(0b10101010, true);
        $this->assertSame(0b11010101, $result);
        $this->assertFalse($carry);
    }

    #[Test]
    public function it_shifts_right_arithmetically_with_bit_7_clear(): void
    {
        [$result, $carry] = BitOps::shiftRight(0b01010100, true);
        $this->assertSame(0b00101010, $result);
        $this->assertFalse($carry);
    }

    #[Test]
    public function it_exchanges_nibbles(): void
    {
        $this->assertSame(0x4F, BitOps::swap(0xF4));
        $this->assertSame(0x21, BitOps::swap(0x12));
        $this->assertSame(0xAB, BitOps::swap(0xBA));
        $this->assertSame(0x00, BitOps::swap(0x00));
        $this->assertSame(0xFF, BitOps::swap(0xFF));
    }
}
