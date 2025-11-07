<?php

declare(strict_types=1);

namespace Tests\Unit\Cpu\Register;

use Gb\Cpu\Register\Register8;
use PHPUnit\Framework\TestCase;

final class Register8Test extends TestCase
{
    public function testInitializesWithDefaultValue(): void
    {
        $register = new Register8();
        $this->assertSame(0x00, $register->get());
    }

    public function testInitializesWithSpecifiedValue(): void
    {
        $register = new Register8(0xAB);
        $this->assertSame(0xAB, $register->get());
    }

    public function testInitializationMasksTo8Bits(): void
    {
        $register = new Register8(0x1FF);
        $this->assertSame(0xFF, $register->get());
    }

    public function testSetStoresValue(): void
    {
        $register = new Register8();
        $register->set(0x42);
        $this->assertSame(0x42, $register->get());
    }

    public function testSetMasksTo8Bits(): void
    {
        $register = new Register8();
        $register->set(0x1AB);
        $this->assertSame(0xAB, $register->get());
    }

    public function testIncrementAddsOne(): void
    {
        $register = new Register8(0x05);
        $register->increment();
        $this->assertSame(0x06, $register->get());
    }

    public function testIncrementWrapsAtBoundary(): void
    {
        $register = new Register8(0xFF);
        $register->increment();
        $this->assertSame(0x00, $register->get());
    }

    public function testDecrementSubtractsOne(): void
    {
        $register = new Register8(0x05);
        $register->decrement();
        $this->assertSame(0x04, $register->get());
    }

    public function testDecrementWrapsAtBoundary(): void
    {
        $register = new Register8(0x00);
        $register->decrement();
        $this->assertSame(0xFF, $register->get());
    }
}
