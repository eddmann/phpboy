<?php

declare(strict_types=1);

namespace Tests\Unit\Cpu\Register;

use Gb\Cpu\Register\FlagRegister;
use PHPUnit\Framework\TestCase;

final class FlagRegisterTest extends TestCase
{
    public function testInitializesWithDefaultValue(): void
    {
        $flags = new FlagRegister();
        $this->assertSame(0x00, $flags->get());
        $this->assertFalse($flags->getZero());
        $this->assertFalse($flags->getSubtract());
        $this->assertFalse($flags->getHalfCarry());
        $this->assertFalse($flags->getCarry());
    }

    public function testInitializesWithSpecifiedValue(): void
    {
        $flags = new FlagRegister(0xF0);
        $this->assertSame(0xF0, $flags->get());
    }

    public function testInitializationMasksLowerNibble(): void
    {
        $flags = new FlagRegister(0xFF);
        $this->assertSame(0xF0, $flags->get());
    }

    public function testSetMasksLowerNibble(): void
    {
        $flags = new FlagRegister();
        $flags->set(0xFF);
        $this->assertSame(0xF0, $flags->get());
    }

    public function testZeroFlagSetAndGet(): void
    {
        $flags = new FlagRegister();
        $flags->setZero(true);
        $this->assertTrue($flags->getZero());
        $this->assertSame(0x80, $flags->get());
    }

    public function testZeroFlagClear(): void
    {
        $flags = new FlagRegister(0xFF);
        $flags->setZero(false);
        $this->assertFalse($flags->getZero());
        $this->assertSame(0x70, $flags->get());
    }

    public function testSubtractFlagSetAndGet(): void
    {
        $flags = new FlagRegister();
        $flags->setSubtract(true);
        $this->assertTrue($flags->getSubtract());
        $this->assertSame(0x40, $flags->get());
    }

    public function testSubtractFlagClear(): void
    {
        $flags = new FlagRegister(0xFF);
        $flags->setSubtract(false);
        $this->assertFalse($flags->getSubtract());
        $this->assertSame(0xB0, $flags->get());
    }

    public function testHalfCarryFlagSetAndGet(): void
    {
        $flags = new FlagRegister();
        $flags->setHalfCarry(true);
        $this->assertTrue($flags->getHalfCarry());
        $this->assertSame(0x20, $flags->get());
    }

    public function testHalfCarryFlagClear(): void
    {
        $flags = new FlagRegister(0xFF);
        $flags->setHalfCarry(false);
        $this->assertFalse($flags->getHalfCarry());
        $this->assertSame(0xD0, $flags->get());
    }

    public function testCarryFlagSetAndGet(): void
    {
        $flags = new FlagRegister();
        $flags->setCarry(true);
        $this->assertTrue($flags->getCarry());
        $this->assertSame(0x10, $flags->get());
    }

    public function testCarryFlagClear(): void
    {
        $flags = new FlagRegister(0xFF);
        $flags->setCarry(false);
        $this->assertFalse($flags->getCarry());
        $this->assertSame(0xE0, $flags->get());
    }

    public function testMultipleFlagsCanBeSetIndependently(): void
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

    public function testClearResetsAllFlags(): void
    {
        $flags = new FlagRegister(0xFF);
        $flags->clear();

        $this->assertSame(0x00, $flags->get());
        $this->assertFalse($flags->getZero());
        $this->assertFalse($flags->getSubtract());
        $this->assertFalse($flags->getHalfCarry());
        $this->assertFalse($flags->getCarry());
    }

    public function testFlagBitPositions(): void
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
