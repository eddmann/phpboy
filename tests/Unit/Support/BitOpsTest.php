<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Gb\Support\BitOps;
use PHPUnit\Framework\TestCase;

final class BitOpsTest extends TestCase
{
    public function testGetBitReturnsTrueWhenBitIsSet(): void
    {
        $this->assertTrue(BitOps::getBit(0b10000000, 7));
        $this->assertTrue(BitOps::getBit(0b00000001, 0));
        $this->assertTrue(BitOps::getBit(0b00010000, 4));
    }

    public function testGetBitReturnsFalseWhenBitIsNotSet(): void
    {
        $this->assertFalse(BitOps::getBit(0b01111111, 7));
        $this->assertFalse(BitOps::getBit(0b11111110, 0));
        $this->assertFalse(BitOps::getBit(0b11101111, 4));
    }

    public function testSetBitSetsSpecifiedBit(): void
    {
        $result = BitOps::setBit(0b00000000, 7, true);
        $this->assertSame(0b10000000, $result);

        $result = BitOps::setBit(0b00000000, 0, true);
        $this->assertSame(0b00000001, $result);

        $result = BitOps::setBit(0b10101010, 0, true);
        $this->assertSame(0b10101011, $result);
    }

    public function testSetBitClearsSpecifiedBit(): void
    {
        $result = BitOps::setBit(0b11111111, 7, false);
        $this->assertSame(0b01111111, $result);

        $result = BitOps::setBit(0b11111111, 0, false);
        $this->assertSame(0b11111110, $result);

        $result = BitOps::setBit(0b10101011, 0, false);
        $this->assertSame(0b10101010, $result);
    }

    public function testRotateLeftWithCarryClear(): void
    {
        [$result, $carry] = BitOps::rotateLeft(0b01010101, false);
        $this->assertSame(0b10101010, $result);
        $this->assertFalse($carry);
    }

    public function testRotateLeftWithCarrySet(): void
    {
        [$result, $carry] = BitOps::rotateLeft(0b01010101, true);
        $this->assertSame(0b10101011, $result);
        $this->assertFalse($carry);
    }

    public function testRotateLeftSetsCarryWhenBit7IsSet(): void
    {
        [$result, $carry] = BitOps::rotateLeft(0b10000000, false);
        $this->assertSame(0b00000000, $result);
        $this->assertTrue($carry);
    }

    public function testRotateRightWithCarryClear(): void
    {
        [$result, $carry] = BitOps::rotateRight(0b10101010, false);
        $this->assertSame(0b01010101, $result);
        $this->assertFalse($carry);
    }

    public function testRotateRightWithCarrySet(): void
    {
        [$result, $carry] = BitOps::rotateRight(0b10101010, true);
        $this->assertSame(0b11010101, $result);
        $this->assertFalse($carry);
    }

    public function testRotateRightSetsCarryWhenBit0IsSet(): void
    {
        [$result, $carry] = BitOps::rotateRight(0b00000001, false);
        $this->assertSame(0b00000000, $result);
        $this->assertTrue($carry);
    }

    public function testShiftLeftShiftsCorrectly(): void
    {
        [$result, $carry] = BitOps::shiftLeft(0b01010101);
        $this->assertSame(0b10101010, $result);
        $this->assertFalse($carry);
    }

    public function testShiftLeftSetsCarryWhenBit7IsSet(): void
    {
        [$result, $carry] = BitOps::shiftLeft(0b10000000);
        $this->assertSame(0b00000000, $result);
        $this->assertTrue($carry);
    }

    public function testShiftRightLogicalShiftsCorrectly(): void
    {
        [$result, $carry] = BitOps::shiftRight(0b10101010, false);
        $this->assertSame(0b01010101, $result);
        $this->assertFalse($carry);
    }

    public function testShiftRightLogicalSetsCarryWhenBit0IsSet(): void
    {
        [$result, $carry] = BitOps::shiftRight(0b00000001, false);
        $this->assertSame(0b00000000, $result);
        $this->assertTrue($carry);
    }

    public function testShiftRightArithmeticPreservesBit7(): void
    {
        [$result, $carry] = BitOps::shiftRight(0b10101010, true);
        $this->assertSame(0b11010101, $result);
        $this->assertFalse($carry);
    }

    public function testShiftRightArithmeticWithBit7Clear(): void
    {
        [$result, $carry] = BitOps::shiftRight(0b01010100, true);
        $this->assertSame(0b00101010, $result);
        $this->assertFalse($carry);
    }

    public function testSwapExchangesNibbles(): void
    {
        $this->assertSame(0x4F, BitOps::swap(0xF4));
        $this->assertSame(0x21, BitOps::swap(0x12));
        $this->assertSame(0xAB, BitOps::swap(0xBA));
        $this->assertSame(0x00, BitOps::swap(0x00));
        $this->assertSame(0xFF, BitOps::swap(0xFF));
    }
}
