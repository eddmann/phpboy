<?php

declare(strict_types=1);

namespace Gb\Cpu;

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
                handler: static fn(Cpu $cpu): int => throw new RuntimeException('Instruction 0x01 (LD BC,nn) not yet implemented'),
            ),

            // 0x02: LD (BC),A - Store A into memory at address BC
            0x02 => new Instruction(
                opcode: 0x02,
                mnemonic: 'LD (BC),A',
                length: 1,
                cycles: 8,
                handler: static fn(Cpu $cpu): int => throw new RuntimeException('Instruction 0x02 (LD (BC),A) not yet implemented'),
            ),

            // 0x03: INC BC - Increment BC
            0x03 => new Instruction(
                opcode: 0x03,
                mnemonic: 'INC BC',
                length: 1,
                cycles: 8,
                handler: static fn(Cpu $cpu): int => throw new RuntimeException('Instruction 0x03 (INC BC) not yet implemented'),
            ),

            // 0x04: INC B - Increment B
            0x04 => new Instruction(
                opcode: 0x04,
                mnemonic: 'INC B',
                length: 1,
                cycles: 4,
                handler: static fn(Cpu $cpu): int => throw new RuntimeException('Instruction 0x04 (INC B) not yet implemented'),
            ),

            // 0x05: DEC B - Decrement B
            0x05 => new Instruction(
                opcode: 0x05,
                mnemonic: 'DEC B',
                length: 1,
                cycles: 4,
                handler: static fn(Cpu $cpu): int => throw new RuntimeException('Instruction 0x05 (DEC B) not yet implemented'),
            ),

            // 0x06: LD B,n - Load 8-bit immediate into B
            0x06 => new Instruction(
                opcode: 0x06,
                mnemonic: 'LD B,n',
                length: 2,
                cycles: 8,
                handler: static fn(Cpu $cpu): int => throw new RuntimeException('Instruction 0x06 (LD B,n) not yet implemented'),
            ),

            // 0x0C: INC C - Increment C
            0x0C => new Instruction(
                opcode: 0x0C,
                mnemonic: 'INC C',
                length: 1,
                cycles: 4,
                handler: static fn(Cpu $cpu): int => throw new RuntimeException('Instruction 0x0C (INC C) not yet implemented'),
            ),

            // 0x0D: DEC C - Decrement C
            0x0D => new Instruction(
                opcode: 0x0D,
                mnemonic: 'DEC C',
                length: 1,
                cycles: 4,
                handler: static fn(Cpu $cpu): int => throw new RuntimeException('Instruction 0x0D (DEC C) not yet implemented'),
            ),

            // 0x0E: LD C,n - Load 8-bit immediate into C
            0x0E => new Instruction(
                opcode: 0x0E,
                mnemonic: 'LD C,n',
                length: 2,
                cycles: 8,
                handler: static fn(Cpu $cpu): int => throw new RuntimeException('Instruction 0x0E (LD C,n) not yet implemented'),
            ),

            // 0x20: JR NZ,e - Relative jump if not zero
            0x20 => new Instruction(
                opcode: 0x20,
                mnemonic: 'JR NZ,e',
                length: 2,
                cycles: 8, // 12 if taken, 8 if not taken
                handler: static fn(Cpu $cpu): int => throw new RuntimeException('Instruction 0x20 (JR NZ,e) not yet implemented'),
            ),

            default => throw new RuntimeException(sprintf('Unknown opcode: 0x%02X', $opcode)),
        };
    }
}
