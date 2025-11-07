<?php

declare(strict_types=1);

namespace Tests\Unit\Cpu\Register;

use Gb\Cpu\Register\Register16;
use PHPUnit\Framework\TestCase;

final class Register16Test extends TestCase
{
    public function testInitializesWithDefaultValue(): void
    {
        $register = new Register16();
        $this->assertSame(0x0000, $register->get());
    }

    public function testInitializesWithSpecifiedValue(): void
    {
        $register = new Register16(0xABCD);
        $this->assertSame(0xABCD, $register->get());
    }

    public function testInitializationMasksTo16Bits(): void
    {
        $register = new Register16(0x1FFFF);
        $this->assertSame(0xFFFF, $register->get());
    }

    public function testSetStoresValue(): void
    {
        $register = new Register16();
        $register->set(0x1234);
        $this->assertSame(0x1234, $register->get());
    }

    public function testSetMasksTo16Bits(): void
    {
        $register = new Register16();
        $register->set(0x1ABCD);
        $this->assertSame(0xABCD, $register->get());
    }

    public function testGetHighReturnsUpperByte(): void
    {
        $register = new Register16(0xABCD);
        $this->assertSame(0xAB, $register->getHigh());
    }

    public function testGetLowReturnsLowerByte(): void
    {
        $register = new Register16(0xABCD);
        $this->assertSame(0xCD, $register->getLow());
    }

    public function testSetHighUpdatesUpperByte(): void
    {
        $register = new Register16(0x00CD);
        $register->setHigh(0xAB);
        $this->assertSame(0xABCD, $register->get());
    }

    public function testSetLowUpdatesLowerByte(): void
    {
        $register = new Register16(0xAB00);
        $register->setLow(0xCD);
        $this->assertSame(0xABCD, $register->get());
    }

    public function testSetHighMasksTo8Bits(): void
    {
        $register = new Register16(0x0000);
        $register->setHigh(0x1AB);
        $this->assertSame(0xAB00, $register->get());
    }

    public function testSetLowMasksTo8Bits(): void
    {
        $register = new Register16(0x0000);
        $register->setLow(0x1CD);
        $this->assertSame(0x00CD, $register->get());
    }

    public function testIncrementAddsOne(): void
    {
        $register = new Register16(0x1234);
        $register->increment();
        $this->assertSame(0x1235, $register->get());
    }

    public function testIncrementWrapsAtBoundary(): void
    {
        $register = new Register16(0xFFFF);
        $register->increment();
        $this->assertSame(0x0000, $register->get());
    }

    public function testDecrementSubtractsOne(): void
    {
        $register = new Register16(0x1234);
        $register->decrement();
        $this->assertSame(0x1233, $register->get());
    }

    public function testDecrementWrapsAtBoundary(): void
    {
        $register = new Register16(0x0000);
        $register->decrement();
        $this->assertSame(0xFFFF, $register->get());
    }
}
