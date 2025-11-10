<?php

declare(strict_types=1);

namespace Tests\Unit\Cpu;

use Gb\Bus\MockBus;
use Gb\Cpu\Cpu;
use Gb\Interrupts\InterruptController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive Instruction Set Tests
 *
 * Tests complex CPU instructions including DAA, flag handling,
 * 16-bit arithmetic, ALU operations, and edge cases.
 */
final class InstructionSetTest extends TestCase
{
    // ============================================================================
    // 8-BIT LOAD INSTRUCTIONS
    // ============================================================================

    #[Test]
    public function it_loads_register_to_register(): void
    {
        $bus = new MockBus([
            0x0100 => 0x47, // LD B,A
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0x42);

        $cpu->step();

        $this->assertSame(0x42, $cpu->getB());
    }

    #[Test]
    public function it_loads_immediate_to_register(): void
    {
        $bus = new MockBus([
            0x0100 => 0x06, // LD B,n
            0x0101 => 0x37,
        ]);
        $cpu = new Cpu($bus, new InterruptController());

        $cpu->step();

        $this->assertSame(0x37, $cpu->getB());
    }

    #[Test]
    public function it_loads_indirect_hl_to_register(): void
    {
        $bus = new MockBus([
            0x0100 => 0x46, // LD B,(HL)
            0x1234 => 0xAB,
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->getHL()->set(0x1234);

        $cpu->step();

        $this->assertSame(0xAB, $cpu->getB());
    }

    #[Test]
    public function it_loads_register_to_indirect_hl(): void
    {
        $bus = new MockBus([
            0x0100 => 0x70, // LD (HL),B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->getHL()->set(0x1234);
        $cpu->setB(0xCD);

        $cpu->step();

        $this->assertSame(0xCD, $bus->readByte(0x1234));
    }

    // ============================================================================
    // 16-BIT LOAD INSTRUCTIONS
    // ============================================================================

    #[Test]
    public function it_loads_16_bit_immediate(): void
    {
        $bus = new MockBus([
            0x0100 => 0x01, // LD BC,nn
            0x0101 => 0x34,
            0x0102 => 0x12,
        ]);
        $cpu = new Cpu($bus, new InterruptController());

        $cpu->step();

        $this->assertSame(0x1234, $cpu->getBC()->get());
    }

    #[Test]
    public function it_loads_with_hl_increment_and_decrement(): void
    {
        $bus = new MockBus([
            0x0100 => 0x22, // LD (HL+),A
            0x0101 => 0x32, // LD (HL-),A
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->getHL()->set(0x1000);
        $cpu->setA(0x42);

        $cpu->step();
        $this->assertSame(0x42, $bus->readByte(0x1000));
        $this->assertSame(0x1001, $cpu->getHL()->get());

        $cpu->step();
        $this->assertSame(0x42, $bus->readByte(0x1001));
        $this->assertSame(0x1000, $cpu->getHL()->get());
    }

    // ============================================================================
    // 8-BIT ALU INSTRUCTIONS
    // ============================================================================

    #[Test]
    public function it_performs_basic_addition(): void
    {
        $bus = new MockBus([
            0x0100 => 0x80, // ADD A,B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0x3A);
        $cpu->setB(0x12);

        $cpu->step();

        $this->assertSame(0x4C, $cpu->getA());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertFalse($cpu->getFlags()->getH());
        $this->assertFalse($cpu->getFlags()->getC());
    }

    #[Test]
    public function it_performs_addition_with_carry(): void
    {
        $bus = new MockBus([
            0x0100 => 0x80, // ADD A,B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0xFF);
        $cpu->setB(0x01);

        $cpu->step();

        $this->assertSame(0x00, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getZ());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
        $this->assertTrue($cpu->getFlags()->getC());
    }

    #[Test]
    public function it_performs_addition_with_half_carry(): void
    {
        $bus = new MockBus([
            0x0100 => 0x80, // ADD A,B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0x0F);
        $cpu->setB(0x01);

        $cpu->step();

        $this->assertSame(0x10, $cpu->getA());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertTrue($cpu->getFlags()->getH());
        $this->assertFalse($cpu->getFlags()->getC());
    }

    #[Test]
    public function it_performs_adc_with_carry_flag(): void
    {
        $bus = new MockBus([
            0x0100 => 0x88, // ADC A,B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0x10);
        $cpu->setB(0x05);
        $cpu->getFlags()->setC(true);

        $cpu->step();

        $this->assertSame(0x16, $cpu->getA()); // 0x10 + 0x05 + 1
    }

    #[Test]
    public function it_performs_basic_subtraction(): void
    {
        $bus = new MockBus([
            0x0100 => 0x90, // SUB B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0x3E);
        $cpu->setB(0x0E);

        $cpu->step();

        $this->assertSame(0x30, $cpu->getA());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertTrue($cpu->getFlags()->getN());
        $this->assertFalse($cpu->getFlags()->getH());
        $this->assertFalse($cpu->getFlags()->getC());
    }

    #[Test]
    public function it_performs_subtraction_with_borrow(): void
    {
        $bus = new MockBus([
            0x0100 => 0x90, // SUB B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0x00);
        $cpu->setB(0x01);

        $cpu->step();

        $this->assertSame(0xFF, $cpu->getA());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertTrue($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
        $this->assertTrue($cpu->getFlags()->getC());
    }

    #[Test]
    public function it_subtracts_register_from_itself(): void
    {
        $bus = new MockBus([
            0x0100 => 0x97, // SUB A
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0x42);

        $cpu->step();

        $this->assertSame(0x00, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getZ());
        $this->assertTrue($cpu->getFlags()->getN());
        $this->assertFalse($cpu->getFlags()->getH());
        $this->assertFalse($cpu->getFlags()->getC());
    }

    #[Test]
    public function it_performs_and_operation(): void
    {
        $bus = new MockBus([
            0x0100 => 0xA0, // AND B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0b11110000);
        $cpu->setB(0b10101010);

        $cpu->step();

        $this->assertSame(0b10100000, $cpu->getA());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
        $this->assertFalse($cpu->getFlags()->getC());
    }

    #[Test]
    public function it_performs_or_operation(): void
    {
        $bus = new MockBus([
            0x0100 => 0xB0, // OR B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0b11110000);
        $cpu->setB(0b00001111);

        $cpu->step();

        $this->assertSame(0xFF, $cpu->getA());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertFalse($cpu->getFlags()->getH());
        $this->assertFalse($cpu->getFlags()->getC());
    }

    #[Test]
    public function it_performs_xor_operation(): void
    {
        $bus = new MockBus([
            0x0100 => 0xA8, // XOR B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0b11110000);
        $cpu->setB(0b10101010);

        $cpu->step();

        $this->assertSame(0b01011010, $cpu->getA());
        $this->assertFalse($cpu->getFlags()->getZ());
    }

    #[Test]
    public function it_xors_register_with_itself(): void
    {
        $bus = new MockBus([
            0x0100 => 0xAF, // XOR A
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0x42);

        $cpu->step();

        $this->assertSame(0x00, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getZ());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertFalse($cpu->getFlags()->getH());
        $this->assertFalse($cpu->getFlags()->getC());
    }

    #[Test]
    public function it_performs_compare_operation(): void
    {
        $bus = new MockBus([
            0x0100 => 0xB8, // CP B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0x3C);
        $cpu->setB(0x2F);

        $originalA = $cpu->getA();
        $cpu->step();

        // CP doesn't modify A
        $this->assertSame($originalA, $cpu->getA());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertTrue($cpu->getFlags()->getN());
    }

    #[Test]
    public function it_compares_equal_values(): void
    {
        $bus = new MockBus([
            0x0100 => 0xB8, // CP B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0x42);
        $cpu->setB(0x42);

        $cpu->step();

        $this->assertTrue($cpu->getFlags()->getZ());
        $this->assertTrue($cpu->getFlags()->getN());
    }

    // ============================================================================
    // INC/DEC INSTRUCTIONS
    // ============================================================================

    #[Test]
    public function it_increments_8_bit_register(): void
    {
        $bus = new MockBus([
            0x0100 => 0x04, // INC B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setB(0x0F);

        $cpu->step();

        $this->assertSame(0x10, $cpu->getB());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
    }

    #[Test]
    public function it_increments_and_wraps_to_zero(): void
    {
        $bus = new MockBus([
            0x0100 => 0x04, // INC B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setB(0xFF);

        $cpu->step();

        $this->assertSame(0x00, $cpu->getB());
        $this->assertTrue($cpu->getFlags()->getZ());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
    }

    #[Test]
    public function it_decrements_8_bit_register(): void
    {
        $bus = new MockBus([
            0x0100 => 0x05, // DEC B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setB(0x10);

        $cpu->step();

        $this->assertSame(0x0F, $cpu->getB());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertTrue($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
    }

    #[Test]
    public function it_decrements_to_zero(): void
    {
        $bus = new MockBus([
            0x0100 => 0x05, // DEC B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setB(0x01);

        $cpu->step();

        $this->assertSame(0x00, $cpu->getB());
        $this->assertTrue($cpu->getFlags()->getZ());
        $this->assertTrue($cpu->getFlags()->getN());
    }

    // ============================================================================
    // 16-BIT ARITHMETIC
    // ============================================================================

    #[Test]
    public function it_performs_16_bit_hl_addition(): void
    {
        $bus = new MockBus([
            0x0100 => 0x09, // ADD HL,BC
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->getHL()->set(0x8A23);
        $cpu->getBC()->set(0x0605);

        $cpu->step();

        $this->assertSame(0x9028, $cpu->getHL()->get());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
        $this->assertFalse($cpu->getFlags()->getC());
    }

    #[Test]
    public function it_performs_16_bit_addition_with_carry(): void
    {
        $bus = new MockBus([
            0x0100 => 0x09, // ADD HL,BC
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->getHL()->set(0xFFFF);
        $cpu->getBC()->set(0x0001);

        $cpu->step();

        $this->assertSame(0x0000, $cpu->getHL()->get());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
        $this->assertTrue($cpu->getFlags()->getC());
    }

    #[Test]
    public function it_increments_16_bit_register(): void
    {
        $bus = new MockBus([
            0x0100 => 0x03, // INC BC
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->getBC()->set(0x235F);

        $cpu->step();

        $this->assertSame(0x2360, $cpu->getBC()->get());
        // 16-bit INC doesn't affect flags
    }

    #[Test]
    public function it_decrements_16_bit_register(): void
    {
        $bus = new MockBus([
            0x0100 => 0x0B, // DEC BC
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->getBC()->set(0x235F);

        $cpu->step();

        $this->assertSame(0x235E, $cpu->getBC()->get());
    }

    // ============================================================================
    // DAA (DECIMAL ADJUST ACCUMULATOR)
    // ============================================================================

    #[Test]
    public function it_performs_daa_after_addition(): void
    {
        $bus = new MockBus([
            0x0100 => 0x80, // ADD A,B
            0x0101 => 0x27, // DAA
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0x09); // BCD 09
        $cpu->setB(0x08); // BCD 08

        $cpu->step(); // ADD: 0x09 + 0x08 = 0x11
        $cpu->step(); // DAA: adjust to 0x17 (BCD 17)

        $this->assertSame(0x17, $cpu->getA());
    }

    #[Test]
    public function it_performs_daa_after_addition_with_carry(): void
    {
        $bus = new MockBus([
            0x0100 => 0x80, // ADD A,B
            0x0101 => 0x27, // DAA
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0x99); // BCD 99
        $cpu->setB(0x01); // BCD 01

        $cpu->step(); // ADD: 0x99 + 0x01 = 0x9A
        $cpu->step(); // DAA: adjust to 0x00, set carry

        $this->assertSame(0x00, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getC());
    }

    #[Test]
    public function it_performs_daa_after_subtraction(): void
    {
        $bus = new MockBus([
            0x0100 => 0x90, // SUB B
            0x0101 => 0x27, // DAA
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0x46); // BCD 46
        $cpu->setB(0x08); // BCD 08

        $cpu->step(); // SUB: 0x46 - 0x08 = 0x3E
        $cpu->step(); // DAA: adjust to 0x38 (BCD 38)

        $this->assertSame(0x38, $cpu->getA());
    }

    // ============================================================================
    // SPECIAL OPERATIONS
    // ============================================================================

    #[Test]
    public function it_performs_complement_operation(): void
    {
        $bus = new MockBus([
            0x0100 => 0x2F, // CPL
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0b10101010);

        $cpu->step();

        $this->assertSame(0b01010101, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
    }

    #[Test]
    public function it_sets_carry_flag(): void
    {
        $bus = new MockBus([
            0x0100 => 0x37, // SCF
        ]);
        $cpu = new Cpu($bus, new InterruptController());

        $cpu->step();

        $this->assertTrue($cpu->getFlags()->getC());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertFalse($cpu->getFlags()->getH());
    }

    #[Test]
    public function it_complements_carry_flag(): void
    {
        $bus = new MockBus([
            0x0100 => 0x3F, // CCF
            0x0101 => 0x3F, // CCF
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->getFlags()->setC(false);

        $cpu->step();
        $this->assertTrue($cpu->getFlags()->getC());

        $cpu->step();
        $this->assertFalse($cpu->getFlags()->getC());
    }

    // ============================================================================
    // ROTATE & SHIFT OPERATIONS
    // ============================================================================

    #[Test]
    public function it_rotates_left_circular_accumulator(): void
    {
        $bus = new MockBus([
            0x0100 => 0x07, // RLCA
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0b10000101);

        $cpu->step();

        $this->assertSame(0b00001011, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getC()); // Bit 7 was set
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertFalse($cpu->getFlags()->getH());
    }

    #[Test]
    public function it_rotates_right_circular_accumulator(): void
    {
        $bus = new MockBus([
            0x0100 => 0x0F, // RRCA
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0b10000101);

        $cpu->step();

        $this->assertSame(0b11000010, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getC()); // Bit 0 was set
    }

    #[Test]
    public function it_rotates_left_accumulator_through_carry(): void
    {
        $bus = new MockBus([
            0x0100 => 0x17, // RLA
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0b10000101);
        $cpu->getFlags()->setC(true);

        $cpu->step();

        $this->assertSame(0b00001011, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getC()); // Old bit 7
    }

    #[Test]
    public function it_rotates_right_accumulator_through_carry(): void
    {
        $bus = new MockBus([
            0x0100 => 0x1F, // RRA
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0b10000101);
        $cpu->getFlags()->setC(true);

        $cpu->step();

        $this->assertSame(0b11000010, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getC()); // Old bit 0
    }

    // ============================================================================
    // STACK OPERATIONS
    // ============================================================================

    #[Test]
    public function it_pushes_and_pops_from_stack(): void
    {
        $bus = new MockBus([
            0x0100 => 0xC5, // PUSH BC
            0x0101 => 0xC1, // POP BC
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->getBC()->set(0x1234);
        $cpu->getSP()->set(0xFFFE);

        $cpu->step(); // PUSH BC
        $this->assertSame(0xFFFC, $cpu->getSP()->get());
        $this->assertSame(0x34, $bus->readByte(0xFFFC)); // Low byte
        $this->assertSame(0x12, $bus->readByte(0xFFFD)); // High byte

        $cpu->getBC()->set(0x0000); // Clear BC
        $cpu->step(); // POP BC
        $this->assertSame(0xFFFE, $cpu->getSP()->get());
        $this->assertSame(0x1234, $cpu->getBC()->get());
    }

    // ============================================================================
    // JUMP & BRANCH OPERATIONS
    // ============================================================================

    #[Test]
    public function it_performs_absolute_jump(): void
    {
        $bus = new MockBus([
            0x0100 => 0xC3, // JP nn
            0x0101 => 0x50,
            0x0102 => 0x01,
        ]);
        $cpu = new Cpu($bus, new InterruptController());

        $cpu->step();

        $this->assertSame(0x0150, $cpu->getPC()->get());
    }

    #[Test]
    public function it_performs_relative_jump(): void
    {
        $bus = new MockBus([
            0x0100 => 0x18, // JR e
            0x0101 => 0x05,
        ]);
        $cpu = new Cpu($bus, new InterruptController());

        $cpu->step();

        $this->assertSame(0x0107, $cpu->getPC()->get()); // 0x0102 + 5
    }

    #[Test]
    public function it_performs_negative_relative_jump(): void
    {
        $bus = new MockBus([
            0x0100 => 0x18, // JR e
            0x0101 => 0xFE, // -2 in two's complement
        ]);
        $cpu = new Cpu($bus, new InterruptController());

        $cpu->step();

        $this->assertSame(0x0100, $cpu->getPC()->get()); // 0x0102 - 2
    }

    #[Test]
    public function it_takes_conditional_jump_when_condition_met(): void
    {
        $bus = new MockBus([
            0x0100 => 0x20, // JR NZ,e
            0x0101 => 0x05,
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->getFlags()->setZ(false); // Not zero

        $cycles = $cpu->step();

        $this->assertSame(0x0107, $cpu->getPC()->get());
        $this->assertSame(12, $cycles); // Taken branch = 12 cycles
    }

    #[Test]
    public function it_skips_conditional_jump_when_condition_not_met(): void
    {
        $bus = new MockBus([
            0x0100 => 0x20, // JR NZ,e
            0x0101 => 0x05,
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->getFlags()->setZ(true); // Zero set

        $cycles = $cpu->step();

        $this->assertSame(0x0102, $cpu->getPC()->get());
        $this->assertSame(8, $cycles); // Not taken = 8 cycles
    }

    // ============================================================================
    // CALL & RETURN
    // ============================================================================

    #[Test]
    public function it_calls_and_returns_from_subroutine(): void
    {
        $bus = new MockBus([
            0x0100 => 0xCD, // CALL nn
            0x0101 => 0x50,
            0x0102 => 0x01,
            0x0150 => 0xC9, // RET at target
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->getSP()->set(0xFFFE);

        $cpu->step(); // CALL
        $this->assertSame(0x0150, $cpu->getPC()->get());
        $this->assertSame(0xFFFC, $cpu->getSP()->get());

        $cpu->step(); // RET
        $this->assertSame(0x0103, $cpu->getPC()->get()); // Return address
        $this->assertSame(0xFFFE, $cpu->getSP()->get());
    }

    #[Test]
    public function it_performs_restart_instruction(): void
    {
        $bus = new MockBus([
            0x0100 => 0xC7, // RST 00H
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->getSP()->set(0xFFFE);

        $cpu->step();

        $this->assertSame(0x0000, $cpu->getPC()->get());
        $this->assertSame(0xFFFC, $cpu->getSP()->get());
    }

    // ============================================================================
    // CB-PREFIXED INSTRUCTIONS
    // ============================================================================

    #[Test]
    public function it_performs_cb_rotate_left(): void
    {
        $bus = new MockBus([
            0x0100 => 0xCB, // CB prefix
            0x0101 => 0x10, // RL B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setB(0b10000001);
        $cpu->getFlags()->setC(false);

        $cpu->step();

        $this->assertSame(0b00000010, $cpu->getB());
        $this->assertTrue($cpu->getFlags()->getC()); // Bit 7 was set
    }

    #[Test]
    public function it_performs_cb_bit_test(): void
    {
        $bus = new MockBus([
            0x0100 => 0xCB, // CB prefix
            0x0101 => 0x47, // BIT 0,A
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setA(0b00000001);

        $cpu->step();

        $this->assertFalse($cpu->getFlags()->getZ()); // Bit 0 is set
        $this->assertTrue($cpu->getFlags()->getH());
    }

    #[Test]
    public function it_performs_cb_bit_test_for_zero(): void
    {
        $bus = new MockBus([
            0x0100 => 0xCB, // CB prefix
            0x0101 => 0x40, // BIT 0,B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setB(0b11111110);

        $cpu->step();

        $this->assertTrue($cpu->getFlags()->getZ()); // Bit 0 is clear
    }

    #[Test]
    public function it_performs_cb_set_bit(): void
    {
        $bus = new MockBus([
            0x0100 => 0xCB, // CB prefix
            0x0101 => 0xC0, // SET 0,B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setB(0b11111110);

        $cpu->step();

        $this->assertSame(0b11111111, $cpu->getB());
    }

    #[Test]
    public function it_performs_cb_reset_bit(): void
    {
        $bus = new MockBus([
            0x0100 => 0xCB, // CB prefix
            0x0101 => 0x80, // RES 0,B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setB(0b11111111);

        $cpu->step();

        $this->assertSame(0b11111110, $cpu->getB());
    }

    #[Test]
    public function it_performs_cb_swap_nibbles(): void
    {
        $bus = new MockBus([
            0x0100 => 0xCB, // CB prefix
            0x0101 => 0x30, // SWAP B
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setB(0x12);

        $cpu->step();

        $this->assertSame(0x21, $cpu->getB());
        $this->assertFalse($cpu->getFlags()->getZ());
    }

    // ============================================================================
    // HALT & STOP
    // ============================================================================

    #[Test]
    public function it_enters_halt_mode(): void
    {
        $bus = new MockBus([
            0x0100 => 0x76, // HALT
        ]);
        $cpu = new Cpu($bus, new InterruptController());

        $cpu->step();

        $this->assertTrue($cpu->isHalted());
    }

    #[Test]
    public function it_enters_stop_mode(): void
    {
        $bus = new MockBus([
            0x0100 => 0x10, // STOP
            0x0101 => 0x00,
        ]);
        $cpu = new Cpu($bus, new InterruptController());

        $cpu->step();

        $this->assertTrue($cpu->isStopped());
        $this->assertTrue($cpu->isHalted());
    }

    // ============================================================================
    // INTERRUPT CONTROL
    // ============================================================================

    #[Test]
    public function it_disables_and_enables_interrupts(): void
    {
        $bus = new MockBus([
            0x0100 => 0xF3, // DI
            0x0101 => 0xFB, // EI
            0x0102 => 0x00, // NOP (for EI delay)
        ]);
        $cpu = new Cpu($bus, new InterruptController());
        $cpu->setIME(true);

        $cpu->step(); // DI
        $this->assertFalse($cpu->getIME());

        $cpu->step(); // EI (IME still false due to 1-instruction delay)
        $this->assertFalse($cpu->getIME());

        $cpu->step(); // NOP (IME becomes true at start of this instruction)
        $this->assertTrue($cpu->getIME());
    }
}
