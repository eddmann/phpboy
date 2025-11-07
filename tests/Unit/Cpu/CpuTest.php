<?php

declare(strict_types=1);

namespace Tests\Unit\Cpu;

use Gb\Bus\MockBus;
use Gb\Cpu\Cpu;
use PHPUnit\Framework\TestCase;

/**
 * CPU Core Tests
 *
 * Tests the basic CPU functionality including register initialization,
 * fetch-decode-execute cycle, and NOP instruction execution.
 */
final class CpuTest extends TestCase
{
    /**
     * Test that CPU registers are initialized to correct power-up values.
     */
    public function testRegisterInitialization(): void
    {
        $bus = new MockBus();
        $cpu = new Cpu($bus);

        // After boot ROM, PC should be at 0x0100 (start of cartridge ROM)
        $this->assertSame(0x0100, $cpu->getPC()->get(), 'PC should initialize to 0x0100');

        // Stack pointer initializes to 0xFFFE (top of HRAM)
        $this->assertSame(0xFFFE, $cpu->getSP()->get(), 'SP should initialize to 0xFFFE');

        // Other registers should be zero
        $this->assertSame(0x0000, $cpu->getAF()->get(), 'AF should initialize to 0x0000');
        $this->assertSame(0x0000, $cpu->getBC()->get(), 'BC should initialize to 0x0000');
        $this->assertSame(0x0000, $cpu->getDE()->get(), 'DE should initialize to 0x0000');
        $this->assertSame(0x0000, $cpu->getHL()->get(), 'HL should initialize to 0x0000');
    }

    /**
     * Test NOP instruction execution.
     * NOP (0x00) should increment PC by 1 and return 4 cycles.
     */
    public function testNopExecution(): void
    {
        $bus = new MockBus([
            0x0100 => 0x00, // NOP at starting address
        ]);
        $cpu = new Cpu($bus);

        $initialPc = $cpu->getPC()->get();
        $cycles = $cpu->step();

        $this->assertSame(4, $cycles, 'NOP should consume 4 cycles');
        $this->assertSame($initialPc + 1, $cpu->getPC()->get(), 'PC should increment by 1 after NOP');
    }

    /**
     * Test fetch operation reads from bus at PC address.
     */
    public function testFetchReadsFromBusAtPc(): void
    {
        $bus = new MockBus([
            0x0100 => 0x42, // Some opcode at PC
        ]);
        $cpu = new Cpu($bus);

        $opcode = $cpu->fetch();

        $this->assertSame(0x42, $opcode, 'Fetch should read opcode at PC address');
        $this->assertSame(0x0101, $cpu->getPC()->get(), 'PC should increment after fetch');
    }

    /**
     * Test flag register operations.
     */
    public function testFlagRegisterOperations(): void
    {
        $bus = new MockBus();
        $cpu = new Cpu($bus);

        $flags = $cpu->getFlags();

        // Test zero flag
        $flags->setZero(true);
        $this->assertTrue($flags->getZero(), 'Zero flag should be set');
        $flags->setZero(false);
        $this->assertFalse($flags->getZero(), 'Zero flag should be cleared');

        // Test subtract flag
        $flags->setSubtract(true);
        $this->assertTrue($flags->getSubtract(), 'Subtract flag should be set');
        $flags->setSubtract(false);
        $this->assertFalse($flags->getSubtract(), 'Subtract flag should be cleared');

        // Test half-carry flag
        $flags->setHalfCarry(true);
        $this->assertTrue($flags->getHalfCarry(), 'Half-carry flag should be set');
        $flags->setHalfCarry(false);
        $this->assertFalse($flags->getHalfCarry(), 'Half-carry flag should be cleared');

        // Test carry flag
        $flags->setCarry(true);
        $this->assertTrue($flags->getCarry(), 'Carry flag should be set');
        $flags->setCarry(false);
        $this->assertFalse($flags->getCarry(), 'Carry flag should be cleared');
    }

    /**
     * Test multiple NOP instructions in sequence.
     */
    public function testMultipleNopInstructions(): void
    {
        $bus = new MockBus([
            0x0100 => 0x00, // NOP
            0x0101 => 0x00, // NOP
            0x0102 => 0x00, // NOP
        ]);
        $cpu = new Cpu($bus);

        // Execute three NOPs
        $cycles1 = $cpu->step();
        $cycles2 = $cpu->step();
        $cycles3 = $cpu->step();

        $this->assertSame(4, $cycles1, 'First NOP should consume 4 cycles');
        $this->assertSame(4, $cycles2, 'Second NOP should consume 4 cycles');
        $this->assertSame(4, $cycles3, 'Third NOP should consume 4 cycles');
        $this->assertSame(0x0103, $cpu->getPC()->get(), 'PC should be at 0x0103 after three NOPs');
    }

    /**
     * Test 8-bit register accessors.
     */
    public function testEightBitRegisterAccessors(): void
    {
        $bus = new MockBus();
        $cpu = new Cpu($bus);

        // Test A register
        $cpu->setA(0x42);
        $this->assertSame(0x42, $cpu->getA(), 'A register should be set correctly');

        // Test B register
        $cpu->setB(0x12);
        $this->assertSame(0x12, $cpu->getB(), 'B register should be set correctly');

        // Test C register
        $cpu->setC(0x34);
        $this->assertSame(0x34, $cpu->getC(), 'C register should be set correctly');

        // Test D register
        $cpu->setD(0x56);
        $this->assertSame(0x56, $cpu->getD(), 'D register should be set correctly');

        // Test E register
        $cpu->setE(0x78);
        $this->assertSame(0x78, $cpu->getE(), 'E register should be set correctly');

        // Test H register
        $cpu->setH(0x9A);
        $this->assertSame(0x9A, $cpu->getH(), 'H register should be set correctly');

        // Test L register
        $cpu->setL(0xBC);
        $this->assertSame(0xBC, $cpu->getL(), 'L register should be set correctly');
    }

    /**
     * Test that 8-bit register changes affect 16-bit register pairs.
     */
    public function testRegisterPairConsistency(): void
    {
        $bus = new MockBus();
        $cpu = new Cpu($bus);

        // Set BC via individual registers
        $cpu->setB(0x12);
        $cpu->setC(0x34);
        $this->assertSame(0x1234, $cpu->getBC()->get(), 'BC should be 0x1234');

        // Set DE via individual registers
        $cpu->setD(0x56);
        $cpu->setE(0x78);
        $this->assertSame(0x5678, $cpu->getDE()->get(), 'DE should be 0x5678');

        // Set HL via individual registers
        $cpu->setH(0x9A);
        $cpu->setL(0xBC);
        $this->assertSame(0x9ABC, $cpu->getHL()->get(), 'HL should be 0x9ABC');
    }
}
