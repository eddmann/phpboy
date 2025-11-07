<?php

declare(strict_types=1);

namespace Gb\Cpu;

use Gb\Support\BitOps;
use RuntimeException;

/**
 * Instruction Set Table
 *
 * Provides instruction metadata and handlers for all LR35902 opcodes.
 * The Game Boy CPU has 256 base opcodes (0x00-0xFF) and 256 CB-prefixed opcodes (0xCB00-0xCBFF).
 *
 * Reference: Pan Docs - CPU Instruction Set
 */
final class InstructionSet
{
    /** @var array<int, Instruction> Cached instruction table */
    private static array $instructions = [];

    /** @var array<int, Instruction> Cached CB-prefixed instruction table */
    private static array $cbInstructions = [];

    /**
     * Get instruction metadata for a given opcode.
     *
     * @param int $opcode The opcode byte (0x00-0xFF)
     * @return Instruction The instruction metadata and handler
     * @throws RuntimeException If opcode is not implemented
     */
    public static function getInstruction(int $opcode): Instruction
    {
        if (!isset(self::$instructions[$opcode])) {
            self::$instructions[$opcode] = self::buildInstruction($opcode);
        }

        return self::$instructions[$opcode];
    }

    /**
     * Get CB-prefixed instruction metadata for a given opcode.
     *
     * @param int $opcode The CB opcode byte (0x00-0xFF)
     * @return Instruction The instruction metadata and handler
     * @throws RuntimeException If opcode is not implemented
     */
    public static function getCBInstruction(int $opcode): Instruction
    {
        if (!isset(self::$cbInstructions[$opcode])) {
            self::$cbInstructions[$opcode] = self::buildCBInstruction($opcode);
        }

        return self::$cbInstructions[$opcode];
    }

    /**
     * Helper: Read next byte from PC and increment PC
     */
    private static function readImm8(Cpu $cpu): int
    {
        return $cpu->fetch();
    }

    /**
     * Helper: Read next word (16-bit) from PC and increment PC twice
     */
    private static function readImm16(Cpu $cpu): int
    {
        $low = $cpu->fetch();
        $high = $cpu->fetch();
        return ($high << 8) | $low;
    }

    /**
     * Helper: Check for half-carry on 8-bit addition
     */
    private static function halfCarry8Add(int $a, int $b, int $carry = 0): bool
    {
        return ((($a & 0x0F) + ($b & 0x0F) + $carry) & 0x10) !== 0;
    }

    /**
     * Helper: Check for half-carry on 8-bit subtraction
     */
    private static function halfCarry8Sub(int $a, int $b, int $carry = 0): bool
    {
        return ((($a & 0x0F) - ($b & 0x0F) - $carry) & 0x10) !== 0;
    }

    /**
     * Helper: Check for half-carry on 16-bit addition (bit 11 to bit 12)
     */
    private static function halfCarry16Add(int $a, int $b): bool
    {
        return ((($a & 0x0FFF) + ($b & 0x0FFF)) & 0x1000) !== 0;
    }

    /**
     * Build instruction metadata for a given opcode.
     *
     * @param int $opcode The opcode byte
     * @return Instruction The instruction
     * @throws RuntimeException If opcode is not implemented
     */
    private static function buildInstruction(int $opcode): Instruction
    {
        return match ($opcode) {
            // 0x00: NOP - No operation
            0x00 => new Instruction(
                opcode: 0x00,
                mnemonic: 'NOP',
                length: 1,
                cycles: 4,
                handler: static fn(Cpu $cpu): int => 4,
            ),

            // 0x01: LD BC,nn - Load 16-bit immediate into BC
            0x01 => new Instruction(
                opcode: 0x01,
                mnemonic: 'LD BC,nn',
                length: 3,
                cycles: 12,
                handler: static function (Cpu $cpu): int {
                    $value = self::readImm16($cpu);
                    $cpu->getBC()->set($value);
                    return 12;
                },
            ),

            // 0x02: LD (BC),A - Store A into memory at address BC
            0x02 => new Instruction(
                opcode: 0x02,
                mnemonic: 'LD (BC),A',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getBC()->get();
                    $cpu->getBus()->writeByte($address, $cpu->getA());
                    return 8;
                },
            ),

            // 0x03: INC BC - Increment BC
            0x03 => new Instruction(
                opcode: 0x03,
                mnemonic: 'INC BC',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $cpu->getBC()->increment();
                    return 8;
                },
            ),

            // 0x04: INC B - Increment B
            0x04 => new Instruction(
                opcode: 0x04,
                mnemonic: 'INC B',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getB();
                    $result = ($value + 1) & 0xFF;
                    $cpu->setB($result);
                    $cpu->getFlags()->setZ($result === 0);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH((($value & 0x0F) + 1) > 0x0F);
                    return 4;
                },
            ),

            // 0x05: DEC B - Decrement B
            0x05 => new Instruction(
                opcode: 0x05,
                mnemonic: 'DEC B',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getB();
                    $result = ($value - 1) & 0xFF;
                    $cpu->setB($result);
                    $cpu->getFlags()->setZ($result === 0);
                    $cpu->getFlags()->setN(true);
                    $cpu->getFlags()->setH(($value & 0x0F) === 0);
                    return 4;
                },
            ),

            // 0x06: LD B,n - Load 8-bit immediate into B
            0x06 => new Instruction(
                opcode: 0x06,
                mnemonic: 'LD B,n',
                length: 2,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $cpu->setB(self::readImm8($cpu));
                    return 8;
                },
            ),

            // 0x07: RLCA - Rotate A left, old bit 7 to carry
            0x07 => new Instruction(
                opcode: 0x07,
                mnemonic: 'RLCA',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getA();
                    $carry = ($value & 0x80) !== 0;
                    $result = (($value << 1) & 0xFF) | ($carry ? 1 : 0);
                    $cpu->setA($result);
                    $cpu->getFlags()->setZ(false);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(false);
                    $cpu->getFlags()->setC($carry);
                    return 4;
                },
            ),

            // 0x08: LD (nn),SP - Store SP at address nn
            0x08 => new Instruction(
                opcode: 0x08,
                mnemonic: 'LD (nn),SP',
                length: 3,
                cycles: 20,
                handler: static function (Cpu $cpu): int {
                    $address = self::readImm16($cpu);
                    $sp = $cpu->getSP()->get();
                    $cpu->getBus()->writeByte($address, $sp & 0xFF);
                    $cpu->getBus()->writeByte($address + 1, ($sp >> 8) & 0xFF);
                    return 20;
                },
            ),

            // 0x09: ADD HL,BC - Add BC to HL
            0x09 => new Instruction(
                opcode: 0x09,
                mnemonic: 'ADD HL,BC',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $hl = $cpu->getHL()->get();
                    $bc = $cpu->getBC()->get();
                    $result = $hl + $bc;
                    $cpu->getHL()->set($result & 0xFFFF);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(self::halfCarry16Add($hl, $bc));
                    $cpu->getFlags()->setC($result > 0xFFFF);
                    return 8;
                },
            ),

            // 0x0A: LD A,(BC) - Load byte at address BC into A
            0x0A => new Instruction(
                opcode: 0x0A,
                mnemonic: 'LD A,(BC)',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getBC()->get();
                    $cpu->setA($cpu->getBus()->readByte($address));
                    return 8;
                },
            ),

            // 0x0B: DEC BC - Decrement BC
            0x0B => new Instruction(
                opcode: 0x0B,
                mnemonic: 'DEC BC',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $cpu->getBC()->decrement();
                    return 8;
                },
            ),

            // 0x0C: INC C - Increment C
            0x0C => new Instruction(
                opcode: 0x0C,
                mnemonic: 'INC C',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getC();
                    $result = ($value + 1) & 0xFF;
                    $cpu->setC($result);
                    $cpu->getFlags()->setZ($result === 0);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH((($value & 0x0F) + 1) > 0x0F);
                    return 4;
                },
            ),

            // 0x0D: DEC C - Decrement C
            0x0D => new Instruction(
                opcode: 0x0D,
                mnemonic: 'DEC C',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getC();
                    $result = ($value - 1) & 0xFF;
                    $cpu->setC($result);
                    $cpu->getFlags()->setZ($result === 0);
                    $cpu->getFlags()->setN(true);
                    $cpu->getFlags()->setH(($value & 0x0F) === 0);
                    return 4;
                },
            ),

            // 0x0E: LD C,n - Load 8-bit immediate into C
            0x0E => new Instruction(
                opcode: 0x0E,
                mnemonic: 'LD C,n',
                length: 2,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $cpu->setC(self::readImm8($cpu));
                    return 8;
                },
            ),

            // 0x0F: RRCA - Rotate A right, old bit 0 to carry
            0x0F => new Instruction(
                opcode: 0x0F,
                mnemonic: 'RRCA',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getA();
                    $carry = ($value & 0x01) !== 0;
                    $result = ($value >> 1) | ($carry ? 0x80 : 0);
                    $cpu->setA($result);
                    $cpu->getFlags()->setZ(false);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(false);
                    $cpu->getFlags()->setC($carry);
                    return 4;
                },
            ),

            // 0x10: STOP - Stop CPU and LCD until button press
            0x10 => new Instruction(
                opcode: 0x10,
                mnemonic: 'STOP',
                length: 2,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    // Read next byte (should be 0x00)
                    self::readImm8($cpu);
                    $cpu->setHalted(true);
                    $cpu->setStopped(true);
                    return 4;
                },
            ),

            // 0x11: LD DE,nn - Load 16-bit immediate into DE
            0x11 => new Instruction(
                opcode: 0x11,
                mnemonic: 'LD DE,nn',
                length: 3,
                cycles: 12,
                handler: static function (Cpu $cpu): int {
                    $value = self::readImm16($cpu);
                    $cpu->getDE()->set($value);
                    return 12;
                },
            ),

            // 0x12: LD (DE),A - Store A into memory at address DE
            0x12 => new Instruction(
                opcode: 0x12,
                mnemonic: 'LD (DE),A',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getDE()->get();
                    $cpu->getBus()->writeByte($address, $cpu->getA());
                    return 8;
                },
            ),

            // 0x13: INC DE - Increment DE
            0x13 => new Instruction(
                opcode: 0x13,
                mnemonic: 'INC DE',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $cpu->getDE()->increment();
                    return 8;
                },
            ),

            // 0x14: INC D - Increment D
            0x14 => new Instruction(
                opcode: 0x14,
                mnemonic: 'INC D',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getD();
                    $result = ($value + 1) & 0xFF;
                    $cpu->setD($result);
                    $cpu->getFlags()->setZ($result === 0);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH((($value & 0x0F) + 1) > 0x0F);
                    return 4;
                },
            ),

            // 0x15: DEC D - Decrement D
            0x15 => new Instruction(
                opcode: 0x15,
                mnemonic: 'DEC D',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getD();
                    $result = ($value - 1) & 0xFF;
                    $cpu->setD($result);
                    $cpu->getFlags()->setZ($result === 0);
                    $cpu->getFlags()->setN(true);
                    $cpu->getFlags()->setH(($value & 0x0F) === 0);
                    return 4;
                },
            ),

            // 0x16: LD D,n - Load 8-bit immediate into D
            0x16 => new Instruction(
                opcode: 0x16,
                mnemonic: 'LD D,n',
                length: 2,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $cpu->setD(self::readImm8($cpu));
                    return 8;
                },
            ),

            // 0x17: RLA - Rotate A left through carry
            0x17 => new Instruction(
                opcode: 0x17,
                mnemonic: 'RLA',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getA();
                    $oldCarry = $cpu->getFlags()->getC();
                    $newCarry = ($value & 0x80) !== 0;
                    $result = (($value << 1) & 0xFF) | ($oldCarry ? 1 : 0);
                    $cpu->setA($result);
                    $cpu->getFlags()->setZ(false);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(false);
                    $cpu->getFlags()->setC($newCarry);
                    return 4;
                },
            ),

            // 0x18: JR e - Relative jump
            0x18 => new Instruction(
                opcode: 0x18,
                mnemonic: 'JR e',
                length: 2,
                cycles: 12,
                handler: static function (Cpu $cpu): int {
                    $offset = self::readImm8($cpu);
                    // Sign extend
                    if ($offset > 0x7F) {
                        $offset -= 0x100;
                    }
                    $pc = $cpu->getPC()->get();
                    $cpu->getPC()->set($pc + $offset);
                    return 12;
                },
            ),

            // 0x19: ADD HL,DE - Add DE to HL
            0x19 => new Instruction(
                opcode: 0x19,
                mnemonic: 'ADD HL,DE',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $hl = $cpu->getHL()->get();
                    $de = $cpu->getDE()->get();
                    $result = $hl + $de;
                    $cpu->getHL()->set($result & 0xFFFF);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(self::halfCarry16Add($hl, $de));
                    $cpu->getFlags()->setC($result > 0xFFFF);
                    return 8;
                },
            ),

            // 0x1A: LD A,(DE) - Load byte at address DE into A
            0x1A => new Instruction(
                opcode: 0x1A,
                mnemonic: 'LD A,(DE)',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getDE()->get();
                    $cpu->setA($cpu->getBus()->readByte($address));
                    return 8;
                },
            ),

            // 0x1B: DEC DE - Decrement DE
            0x1B => new Instruction(
                opcode: 0x1B,
                mnemonic: 'DEC DE',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $cpu->getDE()->decrement();
                    return 8;
                },
            ),

            // 0x1C: INC E - Increment E
            0x1C => new Instruction(
                opcode: 0x1C,
                mnemonic: 'INC E',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getE();
                    $result = ($value + 1) & 0xFF;
                    $cpu->setE($result);
                    $cpu->getFlags()->setZ($result === 0);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH((($value & 0x0F) + 1) > 0x0F);
                    return 4;
                },
            ),

            // 0x1D: DEC E - Decrement E
            0x1D => new Instruction(
                opcode: 0x1D,
                mnemonic: 'DEC E',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getE();
                    $result = ($value - 1) & 0xFF;
                    $cpu->setE($result);
                    $cpu->getFlags()->setZ($result === 0);
                    $cpu->getFlags()->setN(true);
                    $cpu->getFlags()->setH(($value & 0x0F) === 0);
                    return 4;
                },
            ),

            // 0x1E: LD E,n - Load 8-bit immediate into E
            0x1E => new Instruction(
                opcode: 0x1E,
                mnemonic: 'LD E,n',
                length: 2,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $cpu->setE(self::readImm8($cpu));
                    return 8;
                },
            ),

            // 0x1F: RRA - Rotate A right through carry
            0x1F => new Instruction(
                opcode: 0x1F,
                mnemonic: 'RRA',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getA();
                    $oldCarry = $cpu->getFlags()->getC();
                    $newCarry = ($value & 0x01) !== 0;
                    $result = ($value >> 1) | ($oldCarry ? 0x80 : 0);
                    $cpu->setA($result);
                    $cpu->getFlags()->setZ(false);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(false);
                    $cpu->getFlags()->setC($newCarry);
                    return 4;
                },
            ),

            // 0x20: JR NZ,e - Relative jump if not zero
            0x20 => new Instruction(
                opcode: 0x20,
                mnemonic: 'JR NZ,e',
                length: 2,
                cycles: 8, // 12 if taken, 8 if not taken
                handler: static function (Cpu $cpu): int {
                    $offset = self::readImm8($cpu);
                    if (!$cpu->getFlags()->getZ()) {
                        // Sign extend
                        if ($offset > 0x7F) {
                            $offset -= 0x100;
                        }
                        $pc = $cpu->getPC()->get();
                        $cpu->getPC()->set($pc + $offset);
                        return 12;
                    }
                    return 8;
                },
            ),

            // 0x21: LD HL,nn - Load 16-bit immediate into HL
            0x21 => new Instruction(
                opcode: 0x21,
                mnemonic: 'LD HL,nn',
                length: 3,
                cycles: 12,
                handler: static function (Cpu $cpu): int {
                    $value = self::readImm16($cpu);
                    $cpu->getHL()->set($value);
                    return 12;
                },
            ),

            // 0x22: LD (HL+),A - Store A at HL, increment HL
            0x22 => new Instruction(
                opcode: 0x22,
                mnemonic: 'LD (HL+),A',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->getBus()->writeByte($address, $cpu->getA());
                    $cpu->getHL()->increment();
                    return 8;
                },
            ),

            // 0x23: INC HL - Increment HL
            0x23 => new Instruction(
                opcode: 0x23,
                mnemonic: 'INC HL',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $cpu->getHL()->increment();
                    return 8;
                },
            ),

            // 0x24: INC H - Increment H
            0x24 => new Instruction(
                opcode: 0x24,
                mnemonic: 'INC H',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getH();
                    $result = ($value + 1) & 0xFF;
                    $cpu->setH($result);
                    $cpu->getFlags()->setZ($result === 0);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH((($value & 0x0F) + 1) > 0x0F);
                    return 4;
                },
            ),

            // 0x25: DEC H - Decrement H
            0x25 => new Instruction(
                opcode: 0x25,
                mnemonic: 'DEC H',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getH();
                    $result = ($value - 1) & 0xFF;
                    $cpu->setH($result);
                    $cpu->getFlags()->setZ($result === 0);
                    $cpu->getFlags()->setN(true);
                    $cpu->getFlags()->setH(($value & 0x0F) === 0);
                    return 4;
                },
            ),

            // 0x26: LD H,n - Load 8-bit immediate into H
            0x26 => new Instruction(
                opcode: 0x26,
                mnemonic: 'LD H,n',
                length: 2,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $cpu->setH(self::readImm8($cpu));
                    return 8;
                },
            ),

            // 0x27: DAA - Decimal Adjust Accumulator
            0x27 => new Instruction(
                opcode: 0x27,
                mnemonic: 'DAA',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $a = $cpu->getA();
                    $flags = $cpu->getFlags();

                    if (!$flags->getN()) {
                        // After addition
                        if ($flags->getC() || $a > 0x99) {
                            $a += 0x60;
                            $flags->setC(true);
                        }
                        if ($flags->getH() || ($a & 0x0F) > 0x09) {
                            $a += 0x06;
                        }
                    } else {
                        // After subtraction
                        if ($flags->getC()) {
                            $a -= 0x60;
                        }
                        if ($flags->getH()) {
                            $a -= 0x06;
                        }
                    }

                    $a &= 0xFF;
                    $cpu->setA($a);
                    $flags->setZ($a === 0);
                    $flags->setH(false);
                    return 4;
                },
            ),

            // 0x28: JR Z,e - Relative jump if zero
            0x28 => new Instruction(
                opcode: 0x28,
                mnemonic: 'JR Z,e',
                length: 2,
                cycles: 8, // 12 if taken, 8 if not taken
                handler: static function (Cpu $cpu): int {
                    $offset = self::readImm8($cpu);
                    if ($cpu->getFlags()->getZ()) {
                        // Sign extend
                        if ($offset > 0x7F) {
                            $offset -= 0x100;
                        }
                        $pc = $cpu->getPC()->get();
                        $cpu->getPC()->set($pc + $offset);
                        return 12;
                    }
                    return 8;
                },
            ),

            // 0x29: ADD HL,HL - Add HL to HL
            0x29 => new Instruction(
                opcode: 0x29,
                mnemonic: 'ADD HL,HL',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $hl = $cpu->getHL()->get();
                    $result = $hl + $hl;
                    $cpu->getHL()->set($result & 0xFFFF);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(self::halfCarry16Add($hl, $hl));
                    $cpu->getFlags()->setC($result > 0xFFFF);
                    return 8;
                },
            ),

            // 0x2A: LD A,(HL+) - Load byte at HL into A, increment HL
            0x2A => new Instruction(
                opcode: 0x2A,
                mnemonic: 'LD A,(HL+)',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->setA($cpu->getBus()->readByte($address));
                    $cpu->getHL()->increment();
                    return 8;
                },
            ),

            // 0x2B: DEC HL - Decrement HL
            0x2B => new Instruction(
                opcode: 0x2B,
                mnemonic: 'DEC HL',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $cpu->getHL()->decrement();
                    return 8;
                },
            ),

            // 0x2C: INC L - Increment L
            0x2C => new Instruction(
                opcode: 0x2C,
                mnemonic: 'INC L',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getL();
                    $result = ($value + 1) & 0xFF;
                    $cpu->setL($result);
                    $cpu->getFlags()->setZ($result === 0);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH((($value & 0x0F) + 1) > 0x0F);
                    return 4;
                },
            ),

            // 0x2D: DEC L - Decrement L
            0x2D => new Instruction(
                opcode: 0x2D,
                mnemonic: 'DEC L',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getL();
                    $result = ($value - 1) & 0xFF;
                    $cpu->setL($result);
                    $cpu->getFlags()->setZ($result === 0);
                    $cpu->getFlags()->setN(true);
                    $cpu->getFlags()->setH(($value & 0x0F) === 0);
                    return 4;
                },
            ),

            // 0x2E: LD L,n - Load 8-bit immediate into L
            0x2E => new Instruction(
                opcode: 0x2E,
                mnemonic: 'LD L,n',
                length: 2,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $cpu->setL(self::readImm8($cpu));
                    return 8;
                },
            ),

            // 0x2F: CPL - Complement A (flip all bits)
            0x2F => new Instruction(
                opcode: 0x2F,
                mnemonic: 'CPL',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setA($cpu->getA() ^ 0xFF);
                    $cpu->getFlags()->setN(true);
                    $cpu->getFlags()->setH(true);
                    return 4;
                },
            ),

            // 0x30: JR NC,e - Relative jump if not carry
            0x30 => new Instruction(
                opcode: 0x30,
                mnemonic: 'JR NC,e',
                length: 2,
                cycles: 8, // 12 if taken, 8 if not taken
                handler: static function (Cpu $cpu): int {
                    $offset = self::readImm8($cpu);
                    if (!$cpu->getFlags()->getC()) {
                        // Sign extend
                        if ($offset > 0x7F) {
                            $offset -= 0x100;
                        }
                        $pc = $cpu->getPC()->get();
                        $cpu->getPC()->set($pc + $offset);
                        return 12;
                    }
                    return 8;
                },
            ),

            // 0x31: LD SP,nn - Load 16-bit immediate into SP
            0x31 => new Instruction(
                opcode: 0x31,
                mnemonic: 'LD SP,nn',
                length: 3,
                cycles: 12,
                handler: static function (Cpu $cpu): int {
                    $value = self::readImm16($cpu);
                    $cpu->getSP()->set($value);
                    return 12;
                },
            ),

            // 0x32: LD (HL-),A - Store A at HL, decrement HL
            0x32 => new Instruction(
                opcode: 0x32,
                mnemonic: 'LD (HL-),A',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->getBus()->writeByte($address, $cpu->getA());
                    $cpu->getHL()->decrement();
                    return 8;
                },
            ),

            // 0x33: INC SP - Increment SP
            0x33 => new Instruction(
                opcode: 0x33,
                mnemonic: 'INC SP',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $cpu->getSP()->increment();
                    return 8;
                },
            ),

            // 0x34: INC (HL) - Increment byte at address HL
            0x34 => new Instruction(
                opcode: 0x34,
                mnemonic: 'INC (HL)',
                length: 1,
                cycles: 12,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $value = $cpu->getBus()->readByte($address);
                    $result = ($value + 1) & 0xFF;
                    $cpu->getBus()->writeByte($address, $result);
                    $cpu->getFlags()->setZ($result === 0);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH((($value & 0x0F) + 1) > 0x0F);
                    return 12;
                },
            ),

            // 0x35: DEC (HL) - Decrement byte at address HL
            0x35 => new Instruction(
                opcode: 0x35,
                mnemonic: 'DEC (HL)',
                length: 1,
                cycles: 12,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $value = $cpu->getBus()->readByte($address);
                    $result = ($value - 1) & 0xFF;
                    $cpu->getBus()->writeByte($address, $result);
                    $cpu->getFlags()->setZ($result === 0);
                    $cpu->getFlags()->setN(true);
                    $cpu->getFlags()->setH(($value & 0x0F) === 0);
                    return 12;
                },
            ),

            // 0x36: LD (HL),n - Load immediate byte into address HL
            0x36 => new Instruction(
                opcode: 0x36,
                mnemonic: 'LD (HL),n',
                length: 2,
                cycles: 12,
                handler: static function (Cpu $cpu): int {
                    $value = self::readImm8($cpu);
                    $address = $cpu->getHL()->get();
                    $cpu->getBus()->writeByte($address, $value);
                    return 12;
                },
            ),

            // 0x37: SCF - Set Carry Flag
            0x37 => new Instruction(
                opcode: 0x37,
                mnemonic: 'SCF',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(false);
                    $cpu->getFlags()->setC(true);
                    return 4;
                },
            ),

            // 0x38: JR C,e - Relative jump if carry
            0x38 => new Instruction(
                opcode: 0x38,
                mnemonic: 'JR C,e',
                length: 2,
                cycles: 8, // 12 if taken, 8 if not taken
                handler: static function (Cpu $cpu): int {
                    $offset = self::readImm8($cpu);
                    if ($cpu->getFlags()->getC()) {
                        // Sign extend
                        if ($offset > 0x7F) {
                            $offset -= 0x100;
                        }
                        $pc = $cpu->getPC()->get();
                        $cpu->getPC()->set($pc + $offset);
                        return 12;
                    }
                    return 8;
                },
            ),

            // 0x39: ADD HL,SP - Add SP to HL
            0x39 => new Instruction(
                opcode: 0x39,
                mnemonic: 'ADD HL,SP',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $hl = $cpu->getHL()->get();
                    $sp = $cpu->getSP()->get();
                    $result = $hl + $sp;
                    $cpu->getHL()->set($result & 0xFFFF);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(self::halfCarry16Add($hl, $sp));
                    $cpu->getFlags()->setC($result > 0xFFFF);
                    return 8;
                },
            ),

            // 0x3A: LD A,(HL-) - Load byte at HL into A, decrement HL
            0x3A => new Instruction(
                opcode: 0x3A,
                mnemonic: 'LD A,(HL-)',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->setA($cpu->getBus()->readByte($address));
                    $cpu->getHL()->decrement();
                    return 8;
                },
            ),

            // 0x3B: DEC SP - Decrement SP
            0x3B => new Instruction(
                opcode: 0x3B,
                mnemonic: 'DEC SP',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $cpu->getSP()->decrement();
                    return 8;
                },
            ),

            // 0x3C: INC A - Increment A
            0x3C => new Instruction(
                opcode: 0x3C,
                mnemonic: 'INC A',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getA();
                    $result = ($value + 1) & 0xFF;
                    $cpu->setA($result);
                    $cpu->getFlags()->setZ($result === 0);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH((($value & 0x0F) + 1) > 0x0F);
                    return 4;
                },
            ),

            // 0x3D: DEC A - Decrement A
            0x3D => new Instruction(
                opcode: 0x3D,
                mnemonic: 'DEC A',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $value = $cpu->getA();
                    $result = ($value - 1) & 0xFF;
                    $cpu->setA($result);
                    $cpu->getFlags()->setZ($result === 0);
                    $cpu->getFlags()->setN(true);
                    $cpu->getFlags()->setH(($value & 0x0F) === 0);
                    return 4;
                },
            ),

            // 0x3E: LD A,n - Load 8-bit immediate into A
            0x3E => new Instruction(
                opcode: 0x3E,
                mnemonic: 'LD A,n',
                length: 2,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $cpu->setA(self::readImm8($cpu));
                    return 8;
                },
            ),

            // 0x3F: CCF - Complement Carry Flag
            0x3F => new Instruction(
                opcode: 0x3F,
                mnemonic: 'CCF',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(false);
                    $cpu->getFlags()->setC(!$cpu->getFlags()->getC());
                    return 4;
                },
            ),

            // 0x40-0x7F: LD r,r instructions (load register to register)
            // B=0, C=1, D=2, E=3, H=4, L=5, (HL)=6, A=7

            // 0x40: LD B,B
            0x40 => new Instruction(
                opcode: 0x40,
                mnemonic: 'LD B,B',
                length: 1,
                cycles: 4,
                handler: static fn(Cpu $cpu): int => 4,
            ),

            // 0x41: LD B,C
            0x41 => new Instruction(
                opcode: 0x41,
                mnemonic: 'LD B,C',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setB($cpu->getC());
                    return 4;
                },
            ),

            // 0x42: LD B,D
            0x42 => new Instruction(
                opcode: 0x42,
                mnemonic: 'LD B,D',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setB($cpu->getD());
                    return 4;
                },
            ),

            // 0x43: LD B,E
            0x43 => new Instruction(
                opcode: 0x43,
                mnemonic: 'LD B,E',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setB($cpu->getE());
                    return 4;
                },
            ),

            // 0x44: LD B,H
            0x44 => new Instruction(
                opcode: 0x44,
                mnemonic: 'LD B,H',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setB($cpu->getH());
                    return 4;
                },
            ),

            // 0x45: LD B,L
            0x45 => new Instruction(
                opcode: 0x45,
                mnemonic: 'LD B,L',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setB($cpu->getL());
                    return 4;
                },
            ),

            // 0x46: LD B,(HL)
            0x46 => new Instruction(
                opcode: 0x46,
                mnemonic: 'LD B,(HL)',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->setB($cpu->getBus()->readByte($address));
                    return 8;
                },
            ),

            // 0x47: LD B,A
            0x47 => new Instruction(
                opcode: 0x47,
                mnemonic: 'LD B,A',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setB($cpu->getA());
                    return 4;
                },
            ),

            // 0x48: LD C,B
            0x48 => new Instruction(
                opcode: 0x48,
                mnemonic: 'LD C,B',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setC($cpu->getB());
                    return 4;
                },
            ),

            // 0x49: LD C,C
            0x49 => new Instruction(
                opcode: 0x49,
                mnemonic: 'LD C,C',
                length: 1,
                cycles: 4,
                handler: static fn(Cpu $cpu): int => 4,
            ),

            // 0x4A: LD C,D
            0x4A => new Instruction(
                opcode: 0x4A,
                mnemonic: 'LD C,D',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setC($cpu->getD());
                    return 4;
                },
            ),

            // 0x4B: LD C,E
            0x4B => new Instruction(
                opcode: 0x4B,
                mnemonic: 'LD C,E',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setC($cpu->getE());
                    return 4;
                },
            ),

            // 0x4C: LD C,H
            0x4C => new Instruction(
                opcode: 0x4C,
                mnemonic: 'LD C,H',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setC($cpu->getH());
                    return 4;
                },
            ),

            // 0x4D: LD C,L
            0x4D => new Instruction(
                opcode: 0x4D,
                mnemonic: 'LD C,L',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setC($cpu->getL());
                    return 4;
                },
            ),

            // 0x4E: LD C,(HL)
            0x4E => new Instruction(
                opcode: 0x4E,
                mnemonic: 'LD C,(HL)',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->setC($cpu->getBus()->readByte($address));
                    return 8;
                },
            ),

            // 0x4F: LD C,A
            0x4F => new Instruction(
                opcode: 0x4F,
                mnemonic: 'LD C,A',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setC($cpu->getA());
                    return 4;
                },
            ),

            // 0x50: LD D,B
            0x50 => new Instruction(
                opcode: 0x50,
                mnemonic: 'LD D,B',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setD($cpu->getB());
                    return 4;
                },
            ),

            // 0x51: LD D,C
            0x51 => new Instruction(
                opcode: 0x51,
                mnemonic: 'LD D,C',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setD($cpu->getC());
                    return 4;
                },
            ),

            // 0x52: LD D,D
            0x52 => new Instruction(
                opcode: 0x52,
                mnemonic: 'LD D,D',
                length: 1,
                cycles: 4,
                handler: static fn(Cpu $cpu): int => 4,
            ),

            // 0x53: LD D,E
            0x53 => new Instruction(
                opcode: 0x53,
                mnemonic: 'LD D,E',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setD($cpu->getE());
                    return 4;
                },
            ),

            // 0x54: LD D,H
            0x54 => new Instruction(
                opcode: 0x54,
                mnemonic: 'LD D,H',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setD($cpu->getH());
                    return 4;
                },
            ),

            // 0x55: LD D,L
            0x55 => new Instruction(
                opcode: 0x55,
                mnemonic: 'LD D,L',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setD($cpu->getL());
                    return 4;
                },
            ),

            // 0x56: LD D,(HL)
            0x56 => new Instruction(
                opcode: 0x56,
                mnemonic: 'LD D,(HL)',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->setD($cpu->getBus()->readByte($address));
                    return 8;
                },
            ),

            // 0x57: LD D,A
            0x57 => new Instruction(
                opcode: 0x57,
                mnemonic: 'LD D,A',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setD($cpu->getA());
                    return 4;
                },
            ),

            // 0x58: LD E,B
            0x58 => new Instruction(
                opcode: 0x58,
                mnemonic: 'LD E,B',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setE($cpu->getB());
                    return 4;
                },
            ),

            // 0x59: LD E,C
            0x59 => new Instruction(
                opcode: 0x59,
                mnemonic: 'LD E,C',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setE($cpu->getC());
                    return 4;
                },
            ),

            // 0x5A: LD E,D
            0x5A => new Instruction(
                opcode: 0x5A,
                mnemonic: 'LD E,D',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setE($cpu->getD());
                    return 4;
                },
            ),

            // 0x5B: LD E,E
            0x5B => new Instruction(
                opcode: 0x5B,
                mnemonic: 'LD E,E',
                length: 1,
                cycles: 4,
                handler: static fn(Cpu $cpu): int => 4,
            ),

            // 0x5C: LD E,H
            0x5C => new Instruction(
                opcode: 0x5C,
                mnemonic: 'LD E,H',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setE($cpu->getH());
                    return 4;
                },
            ),

            // 0x5D: LD E,L
            0x5D => new Instruction(
                opcode: 0x5D,
                mnemonic: 'LD E,L',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setE($cpu->getL());
                    return 4;
                },
            ),

            // 0x5E: LD E,(HL)
            0x5E => new Instruction(
                opcode: 0x5E,
                mnemonic: 'LD E,(HL)',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->setE($cpu->getBus()->readByte($address));
                    return 8;
                },
            ),

            // 0x5F: LD E,A
            0x5F => new Instruction(
                opcode: 0x5F,
                mnemonic: 'LD E,A',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setE($cpu->getA());
                    return 4;
                },
            ),

            // 0x60: LD H,B
            0x60 => new Instruction(
                opcode: 0x60,
                mnemonic: 'LD H,B',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setH($cpu->getB());
                    return 4;
                },
            ),

            // 0x61: LD H,C
            0x61 => new Instruction(
                opcode: 0x61,
                mnemonic: 'LD H,C',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setH($cpu->getC());
                    return 4;
                },
            ),

            // 0x62: LD H,D
            0x62 => new Instruction(
                opcode: 0x62,
                mnemonic: 'LD H,D',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setH($cpu->getD());
                    return 4;
                },
            ),

            // 0x63: LD H,E
            0x63 => new Instruction(
                opcode: 0x63,
                mnemonic: 'LD H,E',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setH($cpu->getE());
                    return 4;
                },
            ),

            // 0x64: LD H,H
            0x64 => new Instruction(
                opcode: 0x64,
                mnemonic: 'LD H,H',
                length: 1,
                cycles: 4,
                handler: static fn(Cpu $cpu): int => 4,
            ),

            // 0x65: LD H,L
            0x65 => new Instruction(
                opcode: 0x65,
                mnemonic: 'LD H,L',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setH($cpu->getL());
                    return 4;
                },
            ),

            // 0x66: LD H,(HL)
            0x66 => new Instruction(
                opcode: 0x66,
                mnemonic: 'LD H,(HL)',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->setH($cpu->getBus()->readByte($address));
                    return 8;
                },
            ),

            // 0x67: LD H,A
            0x67 => new Instruction(
                opcode: 0x67,
                mnemonic: 'LD H,A',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setH($cpu->getA());
                    return 4;
                },
            ),

            // 0x68: LD L,B
            0x68 => new Instruction(
                opcode: 0x68,
                mnemonic: 'LD L,B',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setL($cpu->getB());
                    return 4;
                },
            ),

            // 0x69: LD L,C
            0x69 => new Instruction(
                opcode: 0x69,
                mnemonic: 'LD L,C',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setL($cpu->getC());
                    return 4;
                },
            ),

            // 0x6A: LD L,D
            0x6A => new Instruction(
                opcode: 0x6A,
                mnemonic: 'LD L,D',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setL($cpu->getD());
                    return 4;
                },
            ),

            // 0x6B: LD L,E
            0x6B => new Instruction(
                opcode: 0x6B,
                mnemonic: 'LD L,E',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setL($cpu->getE());
                    return 4;
                },
            ),

            // 0x6C: LD L,H
            0x6C => new Instruction(
                opcode: 0x6C,
                mnemonic: 'LD L,H',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setL($cpu->getH());
                    return 4;
                },
            ),

            // 0x6D: LD L,L
            0x6D => new Instruction(
                opcode: 0x6D,
                mnemonic: 'LD L,L',
                length: 1,
                cycles: 4,
                handler: static fn(Cpu $cpu): int => 4,
            ),

            // 0x6E: LD L,(HL)
            0x6E => new Instruction(
                opcode: 0x6E,
                mnemonic: 'LD L,(HL)',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->setL($cpu->getBus()->readByte($address));
                    return 8;
                },
            ),

            // 0x6F: LD L,A
            0x6F => new Instruction(
                opcode: 0x6F,
                mnemonic: 'LD L,A',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setL($cpu->getA());
                    return 4;
                },
            ),

            // 0x70: LD (HL),B
            0x70 => new Instruction(
                opcode: 0x70,
                mnemonic: 'LD (HL),B',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->getBus()->writeByte($address, $cpu->getB());
                    return 8;
                },
            ),

            // 0x71: LD (HL),C
            0x71 => new Instruction(
                opcode: 0x71,
                mnemonic: 'LD (HL),C',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->getBus()->writeByte($address, $cpu->getC());
                    return 8;
                },
            ),

            // 0x72: LD (HL),D
            0x72 => new Instruction(
                opcode: 0x72,
                mnemonic: 'LD (HL),D',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->getBus()->writeByte($address, $cpu->getD());
                    return 8;
                },
            ),

            // 0x73: LD (HL),E
            0x73 => new Instruction(
                opcode: 0x73,
                mnemonic: 'LD (HL),E',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->getBus()->writeByte($address, $cpu->getE());
                    return 8;
                },
            ),

            // 0x74: LD (HL),H
            0x74 => new Instruction(
                opcode: 0x74,
                mnemonic: 'LD (HL),H',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->getBus()->writeByte($address, $cpu->getH());
                    return 8;
                },
            ),

            // 0x75: LD (HL),L
            0x75 => new Instruction(
                opcode: 0x75,
                mnemonic: 'LD (HL),L',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->getBus()->writeByte($address, $cpu->getL());
                    return 8;
                },
            ),

            // 0x76: HALT - Halt CPU until interrupt
            0x76 => new Instruction(
                opcode: 0x76,
                mnemonic: 'HALT',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setHalted(true);
                    return 4;
                },
            ),

            // 0x77: LD (HL),A
            0x77 => new Instruction(
                opcode: 0x77,
                mnemonic: 'LD (HL),A',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->getBus()->writeByte($address, $cpu->getA());
                    return 8;
                },
            ),

            // 0x78: LD A,B
            0x78 => new Instruction(
                opcode: 0x78,
                mnemonic: 'LD A,B',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setA($cpu->getB());
                    return 4;
                },
            ),

            // 0x79: LD A,C
            0x79 => new Instruction(
                opcode: 0x79,
                mnemonic: 'LD A,C',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setA($cpu->getC());
                    return 4;
                },
            ),

            // 0x7A: LD A,D
            0x7A => new Instruction(
                opcode: 0x7A,
                mnemonic: 'LD A,D',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setA($cpu->getD());
                    return 4;
                },
            ),

            // 0x7B: LD A,E
            0x7B => new Instruction(
                opcode: 0x7B,
                mnemonic: 'LD A,E',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setA($cpu->getE());
                    return 4;
                },
            ),

            // 0x7C: LD A,H
            0x7C => new Instruction(
                opcode: 0x7C,
                mnemonic: 'LD A,H',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setA($cpu->getH());
                    return 4;
                },
            ),

            // 0x7D: LD A,L
            0x7D => new Instruction(
                opcode: 0x7D,
                mnemonic: 'LD A,L',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $cpu->setA($cpu->getL());
                    return 4;
                },
            ),

            // 0x7E: LD A,(HL)
            0x7E => new Instruction(
                opcode: 0x7E,
                mnemonic: 'LD A,(HL)',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $address = $cpu->getHL()->get();
                    $cpu->setA($cpu->getBus()->readByte($address));
                    return 8;
                },
            ),

            // 0x7F: LD A,A
            0x7F => new Instruction(
                opcode: 0x7F,
                mnemonic: 'LD A,A',
                length: 1,
                cycles: 4,
                handler: static fn(Cpu $cpu): int => 4,
            ),

            // 0x80-0xBF: ALU instructions with A

            // 0x80: ADD A,B
            0x80 => new Instruction(
                opcode: 0x80,
                mnemonic: 'ADD A,B',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $a = $cpu->getA();
                    $b = $cpu->getB();
                    $result = $a + $b;
                    $cpu->setA($result & 0xFF);
                    $cpu->getFlags()->setZ(($result & 0xFF) === 0);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(self::halfCarry8Add($a, $b));
                    $cpu->getFlags()->setC($result > 0xFF);
                    return 4;
                },
            ),

            // 0x81: ADD A,C
            0x81 => new Instruction(
                opcode: 0x81,
                mnemonic: 'ADD A,C',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $a = $cpu->getA();
                    $c = $cpu->getC();
                    $result = $a + $c;
                    $cpu->setA($result & 0xFF);
                    $cpu->getFlags()->setZ(($result & 0xFF) === 0);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(self::halfCarry8Add($a, $c));
                    $cpu->getFlags()->setC($result > 0xFF);
                    return 4;
                },
            ),

            // 0x82: ADD A,D
            0x82 => new Instruction(
                opcode: 0x82,
                mnemonic: 'ADD A,D',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $a = $cpu->getA();
                    $d = $cpu->getD();
                    $result = $a + $d;
                    $cpu->setA($result & 0xFF);
                    $cpu->getFlags()->setZ(($result & 0xFF) === 0);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(self::halfCarry8Add($a, $d));
                    $cpu->getFlags()->setC($result > 0xFF);
                    return 4;
                },
            ),

            // 0x83: ADD A,E
            0x83 => new Instruction(
                opcode: 0x83,
                mnemonic: 'ADD A,E',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $a = $cpu->getA();
                    $e = $cpu->getE();
                    $result = $a + $e;
                    $cpu->setA($result & 0xFF);
                    $cpu->getFlags()->setZ(($result & 0xFF) === 0);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(self::halfCarry8Add($a, $e));
                    $cpu->getFlags()->setC($result > 0xFF);
                    return 4;
                },
            ),

            // 0x84: ADD A,H
            0x84 => new Instruction(
                opcode: 0x84,
                mnemonic: 'ADD A,H',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $a = $cpu->getA();
                    $h = $cpu->getH();
                    $result = $a + $h;
                    $cpu->setA($result & 0xFF);
                    $cpu->getFlags()->setZ(($result & 0xFF) === 0);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(self::halfCarry8Add($a, $h));
                    $cpu->getFlags()->setC($result > 0xFF);
                    return 4;
                },
            ),

            // 0x85: ADD A,L
            0x85 => new Instruction(
                opcode: 0x85,
                mnemonic: 'ADD A,L',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $a = $cpu->getA();
                    $l = $cpu->getL();
                    $result = $a + $l;
                    $cpu->setA($result & 0xFF);
                    $cpu->getFlags()->setZ(($result & 0xFF) === 0);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(self::halfCarry8Add($a, $l));
                    $cpu->getFlags()->setC($result > 0xFF);
                    return 4;
                },
            ),

            // 0x86: ADD A,(HL)
            0x86 => new Instruction(
                opcode: 0x86,
                mnemonic: 'ADD A,(HL)',
                length: 1,
                cycles: 8,
                handler: static function (Cpu $cpu): int {
                    $a = $cpu->getA();
                    $address = $cpu->getHL()->get();
                    $value = $cpu->getBus()->readByte($address);
                    $result = $a + $value;
                    $cpu->setA($result & 0xFF);
                    $cpu->getFlags()->setZ(($result & 0xFF) === 0);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(self::halfCarry8Add($a, $value));
                    $cpu->getFlags()->setC($result > 0xFF);
                    return 8;
                },
            ),

            // 0x87: ADD A,A
            0x87 => new Instruction(
                opcode: 0x87,
                mnemonic: 'ADD A,A',
                length: 1,
                cycles: 4,
                handler: static function (Cpu $cpu): int {
                    $a = $cpu->getA();
                    $result = $a + $a;
                    $cpu->setA($result & 0xFF);
                    $cpu->getFlags()->setZ(($result & 0xFF) === 0);
                    $cpu->getFlags()->setN(false);
                    $cpu->getFlags()->setH(self::halfCarry8Add($a, $a));
                    $cpu->getFlags()->setC($result > 0xFF);
                    return 4;
                },
            ),

            // Continue with remaining instructions in next part...

            default => throw new RuntimeException(sprintf('Unknown opcode: 0x%02X', $opcode)),
        };
    }

    /**
     * Build CB-prefixed instruction metadata.
     */
    private static function buildCBInstruction(int $opcode): Instruction
    {
        // CB instructions to be implemented
        throw new RuntimeException(sprintf('CB-prefixed opcode 0xCB%02X not yet implemented', $opcode));
    }
}
