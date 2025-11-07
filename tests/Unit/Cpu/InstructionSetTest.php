<?php

declare(strict_types=1);

namespace Tests\Unit\Cpu;

use Gb\Bus\MockBus;
use Gb\Cpu\Cpu;
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

    public function testLdRegReg(): void
    {
        $bus = new MockBus([
            0x0100 => 0x47, // LD B,A
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0x42);

        $cpu->step();

        $this->assertSame(0x42, $cpu->getB());
    }

    public function testLdRegImm(): void
    {
        $bus = new MockBus([
            0x0100 => 0x06, // LD B,n
            0x0101 => 0x37,
        ]);
        $cpu = new Cpu($bus);

        $cpu->step();

        $this->assertSame(0x37, $cpu->getB());
    }

    public function testLdRegIndirectHL(): void
    {
        $bus = new MockBus([
            0x0100 => 0x46, // LD B,(HL)
            0x1234 => 0xAB,
        ]);
        $cpu = new Cpu($bus);
        $cpu->getHL()->set(0x1234);

        $cpu->step();

        $this->assertSame(0xAB, $cpu->getB());
    }

    public function testLdIndirectHLReg(): void
    {
        $bus = new MockBus([
            0x0100 => 0x70, // LD (HL),B
        ]);
        $cpu = new Cpu($bus);
        $cpu->getHL()->set(0x1234);
        $cpu->setB(0xCD);

        $cpu->step();

        $this->assertSame(0xCD, $bus->readByte(0x1234));
    }

    // ============================================================================
    // 16-BIT LOAD INSTRUCTIONS
    // ============================================================================

    public function testLd16Imm(): void
    {
        $bus = new MockBus([
            0x0100 => 0x01, // LD BC,nn
            0x0101 => 0x34,
            0x0102 => 0x12,
        ]);
        $cpu = new Cpu($bus);

        $cpu->step();

        $this->assertSame(0x1234, $cpu->getBC()->get());
    }

    public function testLdHLIncDec(): void
    {
        $bus = new MockBus([
            0x0100 => 0x22, // LD (HL+),A
            0x0101 => 0x32, // LD (HL-),A
        ]);
        $cpu = new Cpu($bus);
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

    public function testAddBasic(): void
    {
        $bus = new MockBus([
            0x0100 => 0x80, // ADD A,B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0x3A);
        $cpu->setB(0x12);

        $cpu->step();

        $this->assertSame(0x4C, $cpu->getA());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertFalse($cpu->getFlags()->getH());
        $this->assertFalse($cpu->getFlags()->getC());
    }

    public function testAddWithCarry(): void
    {
        $bus = new MockBus([
            0x0100 => 0x80, // ADD A,B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0xFF);
        $cpu->setB(0x01);

        $cpu->step();

        $this->assertSame(0x00, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getZ());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
        $this->assertTrue($cpu->getFlags()->getC());
    }

    public function testAddWithHalfCarry(): void
    {
        $bus = new MockBus([
            0x0100 => 0x80, // ADD A,B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0x0F);
        $cpu->setB(0x01);

        $cpu->step();

        $this->assertSame(0x10, $cpu->getA());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertTrue($cpu->getFlags()->getH());
        $this->assertFalse($cpu->getFlags()->getC());
    }

    public function testAdcWithCarryFlag(): void
    {
        $bus = new MockBus([
            0x0100 => 0x88, // ADC A,B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0x10);
        $cpu->setB(0x05);
        $cpu->getFlags()->setC(true);

        $cpu->step();

        $this->assertSame(0x16, $cpu->getA()); // 0x10 + 0x05 + 1
    }

    public function testSubBasic(): void
    {
        $bus = new MockBus([
            0x0100 => 0x90, // SUB B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0x3E);
        $cpu->setB(0x0E);

        $cpu->step();

        $this->assertSame(0x30, $cpu->getA());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertTrue($cpu->getFlags()->getN());
        $this->assertFalse($cpu->getFlags()->getH());
        $this->assertFalse($cpu->getFlags()->getC());
    }

    public function testSubWithBorrow(): void
    {
        $bus = new MockBus([
            0x0100 => 0x90, // SUB B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0x00);
        $cpu->setB(0x01);

        $cpu->step();

        $this->assertSame(0xFF, $cpu->getA());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertTrue($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
        $this->assertTrue($cpu->getFlags()->getC());
    }

    public function testSubSelf(): void
    {
        $bus = new MockBus([
            0x0100 => 0x97, // SUB A
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0x42);

        $cpu->step();

        $this->assertSame(0x00, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getZ());
        $this->assertTrue($cpu->getFlags()->getN());
        $this->assertFalse($cpu->getFlags()->getH());
        $this->assertFalse($cpu->getFlags()->getC());
    }

    public function testAndOperation(): void
    {
        $bus = new MockBus([
            0x0100 => 0xA0, // AND B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0b11110000);
        $cpu->setB(0b10101010);

        $cpu->step();

        $this->assertSame(0b10100000, $cpu->getA());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
        $this->assertFalse($cpu->getFlags()->getC());
    }

    public function testOrOperation(): void
    {
        $bus = new MockBus([
            0x0100 => 0xB0, // OR B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0b11110000);
        $cpu->setB(0b00001111);

        $cpu->step();

        $this->assertSame(0xFF, $cpu->getA());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertFalse($cpu->getFlags()->getH());
        $this->assertFalse($cpu->getFlags()->getC());
    }

    public function testXorOperation(): void
    {
        $bus = new MockBus([
            0x0100 => 0xA8, // XOR B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0b11110000);
        $cpu->setB(0b10101010);

        $cpu->step();

        $this->assertSame(0b01011010, $cpu->getA());
        $this->assertFalse($cpu->getFlags()->getZ());
    }

    public function testXorSelf(): void
    {
        $bus = new MockBus([
            0x0100 => 0xAF, // XOR A
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0x42);

        $cpu->step();

        $this->assertSame(0x00, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getZ());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertFalse($cpu->getFlags()->getH());
        $this->assertFalse($cpu->getFlags()->getC());
    }

    public function testCpOperation(): void
    {
        $bus = new MockBus([
            0x0100 => 0xB8, // CP B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0x3C);
        $cpu->setB(0x2F);

        $originalA = $cpu->getA();
        $cpu->step();

        // CP doesn't modify A
        $this->assertSame($originalA, $cpu->getA());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertTrue($cpu->getFlags()->getN());
    }

    public function testCpEqual(): void
    {
        $bus = new MockBus([
            0x0100 => 0xB8, // CP B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0x42);
        $cpu->setB(0x42);

        $cpu->step();

        $this->assertTrue($cpu->getFlags()->getZ());
        $this->assertTrue($cpu->getFlags()->getN());
    }

    // ============================================================================
    // INC/DEC INSTRUCTIONS
    // ============================================================================

    public function testInc8Bit(): void
    {
        $bus = new MockBus([
            0x0100 => 0x04, // INC B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setB(0x0F);

        $cpu->step();

        $this->assertSame(0x10, $cpu->getB());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
    }

    public function testIncWrapToZero(): void
    {
        $bus = new MockBus([
            0x0100 => 0x04, // INC B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setB(0xFF);

        $cpu->step();

        $this->assertSame(0x00, $cpu->getB());
        $this->assertTrue($cpu->getFlags()->getZ());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
    }

    public function testDec8Bit(): void
    {
        $bus = new MockBus([
            0x0100 => 0x05, // DEC B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setB(0x10);

        $cpu->step();

        $this->assertSame(0x0F, $cpu->getB());
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertTrue($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
    }

    public function testDecToZero(): void
    {
        $bus = new MockBus([
            0x0100 => 0x05, // DEC B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setB(0x01);

        $cpu->step();

        $this->assertSame(0x00, $cpu->getB());
        $this->assertTrue($cpu->getFlags()->getZ());
        $this->assertTrue($cpu->getFlags()->getN());
    }

    // ============================================================================
    // 16-BIT ARITHMETIC
    // ============================================================================

    public function testAddHL16Bit(): void
    {
        $bus = new MockBus([
            0x0100 => 0x09, // ADD HL,BC
        ]);
        $cpu = new Cpu($bus);
        $cpu->getHL()->set(0x8A23);
        $cpu->getBC()->set(0x0605);

        $cpu->step();

        $this->assertSame(0x9028, $cpu->getHL()->get());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
        $this->assertFalse($cpu->getFlags()->getC());
    }

    public function testAddHL16BitWithCarry(): void
    {
        $bus = new MockBus([
            0x0100 => 0x09, // ADD HL,BC
        ]);
        $cpu = new Cpu($bus);
        $cpu->getHL()->set(0xFFFF);
        $cpu->getBC()->set(0x0001);

        $cpu->step();

        $this->assertSame(0x0000, $cpu->getHL()->get());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
        $this->assertTrue($cpu->getFlags()->getC());
    }

    public function testInc16Bit(): void
    {
        $bus = new MockBus([
            0x0100 => 0x03, // INC BC
        ]);
        $cpu = new Cpu($bus);
        $cpu->getBC()->set(0x235F);

        $cpu->step();

        $this->assertSame(0x2360, $cpu->getBC()->get());
        // 16-bit INC doesn't affect flags
    }

    public function testDec16Bit(): void
    {
        $bus = new MockBus([
            0x0100 => 0x0B, // DEC BC
        ]);
        $cpu = new Cpu($bus);
        $cpu->getBC()->set(0x235F);

        $cpu->step();

        $this->assertSame(0x235E, $cpu->getBC()->get());
    }

    // ============================================================================
    // DAA (DECIMAL ADJUST ACCUMULATOR)
    // ============================================================================

    public function testDaaAfterAddition(): void
    {
        $bus = new MockBus([
            0x0100 => 0x80, // ADD A,B
            0x0101 => 0x27, // DAA
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0x09); // BCD 09
        $cpu->setB(0x08); // BCD 08

        $cpu->step(); // ADD: 0x09 + 0x08 = 0x11
        $cpu->step(); // DAA: adjust to 0x17 (BCD 17)

        $this->assertSame(0x17, $cpu->getA());
    }

    public function testDaaAfterAdditionWithCarry(): void
    {
        $bus = new MockBus([
            0x0100 => 0x80, // ADD A,B
            0x0101 => 0x27, // DAA
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0x99); // BCD 99
        $cpu->setB(0x01); // BCD 01

        $cpu->step(); // ADD: 0x99 + 0x01 = 0x9A
        $cpu->step(); // DAA: adjust to 0x00, set carry

        $this->assertSame(0x00, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getC());
    }

    public function testDaaAfterSubtraction(): void
    {
        $bus = new MockBus([
            0x0100 => 0x90, // SUB B
            0x0101 => 0x27, // DAA
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0x46); // BCD 46
        $cpu->setB(0x08); // BCD 08

        $cpu->step(); // SUB: 0x46 - 0x08 = 0x3E
        $cpu->step(); // DAA: adjust to 0x38 (BCD 38)

        $this->assertSame(0x38, $cpu->getA());
    }

    // ============================================================================
    // SPECIAL OPERATIONS
    // ============================================================================

    public function testCpl(): void
    {
        $bus = new MockBus([
            0x0100 => 0x2F, // CPL
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0b10101010);

        $cpu->step();

        $this->assertSame(0b01010101, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getN());
        $this->assertTrue($cpu->getFlags()->getH());
    }

    public function testScf(): void
    {
        $bus = new MockBus([
            0x0100 => 0x37, // SCF
        ]);
        $cpu = new Cpu($bus);

        $cpu->step();

        $this->assertTrue($cpu->getFlags()->getC());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertFalse($cpu->getFlags()->getH());
    }

    public function testCcf(): void
    {
        $bus = new MockBus([
            0x0100 => 0x3F, // CCF
            0x0101 => 0x3F, // CCF
        ]);
        $cpu = new Cpu($bus);
        $cpu->getFlags()->setC(false);

        $cpu->step();
        $this->assertTrue($cpu->getFlags()->getC());

        $cpu->step();
        $this->assertFalse($cpu->getFlags()->getC());
    }

    // ============================================================================
    // ROTATE & SHIFT OPERATIONS
    // ============================================================================

    public function testRlca(): void
    {
        $bus = new MockBus([
            0x0100 => 0x07, // RLCA
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0b10000101);

        $cpu->step();

        $this->assertSame(0b00001011, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getC()); // Bit 7 was set
        $this->assertFalse($cpu->getFlags()->getZ());
        $this->assertFalse($cpu->getFlags()->getN());
        $this->assertFalse($cpu->getFlags()->getH());
    }

    public function testRrca(): void
    {
        $bus = new MockBus([
            0x0100 => 0x0F, // RRCA
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0b10000101);

        $cpu->step();

        $this->assertSame(0b11000010, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getC()); // Bit 0 was set
    }

    public function testRla(): void
    {
        $bus = new MockBus([
            0x0100 => 0x17, // RLA
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0b10000101);
        $cpu->getFlags()->setC(true);

        $cpu->step();

        $this->assertSame(0b00001011, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getC()); // Old bit 7
    }

    public function testRra(): void
    {
        $bus = new MockBus([
            0x0100 => 0x1F, // RRA
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0b10000101);
        $cpu->getFlags()->setC(true);

        $cpu->step();

        $this->assertSame(0b11000010, $cpu->getA());
        $this->assertTrue($cpu->getFlags()->getC()); // Old bit 0
    }

    // ============================================================================
    // STACK OPERATIONS
    // ============================================================================

    public function testPushPop(): void
    {
        $bus = new MockBus([
            0x0100 => 0xC5, // PUSH BC
            0x0101 => 0xC1, // POP BC
        ]);
        $cpu = new Cpu($bus);
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

    public function testJpAbsolute(): void
    {
        $bus = new MockBus([
            0x0100 => 0xC3, // JP nn
            0x0101 => 0x50,
            0x0102 => 0x01,
        ]);
        $cpu = new Cpu($bus);

        $cpu->step();

        $this->assertSame(0x0150, $cpu->getPC()->get());
    }

    public function testJrRelative(): void
    {
        $bus = new MockBus([
            0x0100 => 0x18, // JR e
            0x0101 => 0x05,
        ]);
        $cpu = new Cpu($bus);

        $cpu->step();

        $this->assertSame(0x0107, $cpu->getPC()->get()); // 0x0102 + 5
    }

    public function testJrRelativeNegative(): void
    {
        $bus = new MockBus([
            0x0100 => 0x18, // JR e
            0x0101 => 0xFE, // -2 in two's complement
        ]);
        $cpu = new Cpu($bus);

        $cpu->step();

        $this->assertSame(0x0100, $cpu->getPC()->get()); // 0x0102 - 2
    }

    public function testJrConditionalTaken(): void
    {
        $bus = new MockBus([
            0x0100 => 0x20, // JR NZ,e
            0x0101 => 0x05,
        ]);
        $cpu = new Cpu($bus);
        $cpu->getFlags()->setZ(false); // Not zero

        $cycles = $cpu->step();

        $this->assertSame(0x0107, $cpu->getPC()->get());
        $this->assertSame(12, $cycles); // Taken branch = 12 cycles
    }

    public function testJrConditionalNotTaken(): void
    {
        $bus = new MockBus([
            0x0100 => 0x20, // JR NZ,e
            0x0101 => 0x05,
        ]);
        $cpu = new Cpu($bus);
        $cpu->getFlags()->setZ(true); // Zero set

        $cycles = $cpu->step();

        $this->assertSame(0x0102, $cpu->getPC()->get());
        $this->assertSame(8, $cycles); // Not taken = 8 cycles
    }

    // ============================================================================
    // CALL & RETURN
    // ============================================================================

    public function testCallAndRet(): void
    {
        $bus = new MockBus([
            0x0100 => 0xCD, // CALL nn
            0x0101 => 0x50,
            0x0102 => 0x01,
            0x0150 => 0xC9, // RET at target
        ]);
        $cpu = new Cpu($bus);
        $cpu->getSP()->set(0xFFFE);

        $cpu->step(); // CALL
        $this->assertSame(0x0150, $cpu->getPC()->get());
        $this->assertSame(0xFFFC, $cpu->getSP()->get());

        $cpu->step(); // RET
        $this->assertSame(0x0103, $cpu->getPC()->get()); // Return address
        $this->assertSame(0xFFFE, $cpu->getSP()->get());
    }

    public function testRst(): void
    {
        $bus = new MockBus([
            0x0100 => 0xC7, // RST 00H
        ]);
        $cpu = new Cpu($bus);
        $cpu->getSP()->set(0xFFFE);

        $cpu->step();

        $this->assertSame(0x0000, $cpu->getPC()->get());
        $this->assertSame(0xFFFC, $cpu->getSP()->get());
    }

    // ============================================================================
    // CB-PREFIXED INSTRUCTIONS
    // ============================================================================

    public function testCBRotateLeft(): void
    {
        $bus = new MockBus([
            0x0100 => 0xCB, // CB prefix
            0x0101 => 0x10, // RL B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setB(0b10000001);
        $cpu->getFlags()->setC(false);

        $cpu->step();

        $this->assertSame(0b00000010, $cpu->getB());
        $this->assertTrue($cpu->getFlags()->getC()); // Bit 7 was set
    }

    public function testCBBitTest(): void
    {
        $bus = new MockBus([
            0x0100 => 0xCB, // CB prefix
            0x0101 => 0x47, // BIT 0,A
        ]);
        $cpu = new Cpu($bus);
        $cpu->setA(0b00000001);

        $cpu->step();

        $this->assertFalse($cpu->getFlags()->getZ()); // Bit 0 is set
        $this->assertTrue($cpu->getFlags()->getH());
    }

    public function testCBBitTestZero(): void
    {
        $bus = new MockBus([
            0x0100 => 0xCB, // CB prefix
            0x0101 => 0x40, // BIT 0,B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setB(0b11111110);

        $cpu->step();

        $this->assertTrue($cpu->getFlags()->getZ()); // Bit 0 is clear
    }

    public function testCBSetBit(): void
    {
        $bus = new MockBus([
            0x0100 => 0xCB, // CB prefix
            0x0101 => 0xC0, // SET 0,B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setB(0b11111110);

        $cpu->step();

        $this->assertSame(0b11111111, $cpu->getB());
    }

    public function testCBResBit(): void
    {
        $bus = new MockBus([
            0x0100 => 0xCB, // CB prefix
            0x0101 => 0x80, // RES 0,B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setB(0b11111111);

        $cpu->step();

        $this->assertSame(0b11111110, $cpu->getB());
    }

    public function testCBSwap(): void
    {
        $bus = new MockBus([
            0x0100 => 0xCB, // CB prefix
            0x0101 => 0x30, // SWAP B
        ]);
        $cpu = new Cpu($bus);
        $cpu->setB(0x12);

        $cpu->step();

        $this->assertSame(0x21, $cpu->getB());
        $this->assertFalse($cpu->getFlags()->getZ());
    }

    // ============================================================================
    // HALT & STOP
    // ============================================================================

    public function testHalt(): void
    {
        $bus = new MockBus([
            0x0100 => 0x76, // HALT
        ]);
        $cpu = new Cpu($bus);

        $cpu->step();

        $this->assertTrue($cpu->isHalted());
    }

    public function testStop(): void
    {
        $bus = new MockBus([
            0x0100 => 0x10, // STOP
            0x0101 => 0x00,
        ]);
        $cpu = new Cpu($bus);

        $cpu->step();

        $this->assertTrue($cpu->isStopped());
        $this->assertTrue($cpu->isHalted());
    }

    // ============================================================================
    // INTERRUPT CONTROL
    // ============================================================================

    public function testDiEi(): void
    {
        $bus = new MockBus([
            0x0100 => 0xF3, // DI
            0x0101 => 0xFB, // EI
        ]);
        $cpu = new Cpu($bus);
        $cpu->setIME(true);

        $cpu->step(); // DI
        $this->assertFalse($cpu->getIME());

        $cpu->step(); // EI
        $this->assertTrue($cpu->getIME());
    }
}
