<?php

declare(strict_types=1);

namespace Gb\Debug;

use Gb\Bus\BusInterface;
use Gb\Cpu\InstructionSet;

/**
 * Game Boy CPU Disassembler
 *
 * Disassembles LR35902 (Game Boy CPU) instructions from memory.
 * Supports both unprefixed and CB-prefixed instructions.
 */
final class Disassembler
{
    public function __construct(
        private readonly BusInterface $bus
    ) {
    }

    /**
     * Disassemble instructions starting at the given address.
     *
     * @param int $address Starting address
     * @param int $count Number of instructions to disassemble
     * @return array<int, array{address: int, bytes: string, mnemonic: string, operands: string}> Disassembled instructions
     */
    public function disassemble(int $address, int $count = 10): array
    {
        $instructions = [];
        $currentAddress = $address & 0xFFFF;

        for ($i = 0; $i < $count; $i++) {
            $instruction = $this->disassembleOne($currentAddress);
            $instructions[] = $instruction;
            $currentAddress = ($currentAddress + $instruction['length']) & 0xFFFF;
        }

        return $instructions;
    }

    /**
     * Disassemble a single instruction at the given address.
     *
     * @param int $address Address to disassemble
     * @return array{address: int, bytes: string, mnemonic: string, operands: string, length: int} Disassembled instruction
     */
    public function disassembleOne(int $address): array
    {
        $address &= 0xFFFF;
        $opcode = $this->bus->readByte($address);

        if ($opcode === 0xCB) {
            // CB-prefixed instruction
            $cbOpcode = $this->bus->readByte(($address + 1) & 0xFFFF);
            return $this->disassembleCB($address, $cbOpcode);
        }

        return $this->disassembleNormal($address, $opcode);
    }

    /**
     * Disassemble a normal (unprefixed) instruction.
     *
     * @param int $address Instruction address
     * @param int $opcode Opcode byte
     * @return array{address: int, bytes: string, mnemonic: string, operands: string, length: int}
     */
    private function disassembleNormal(int $address, int $opcode): array
    {
        // Read potential operands
        $byte1 = $this->bus->readByte(($address + 1) & 0xFFFF);
        $byte2 = $this->bus->readByte(($address + 2) & 0xFFFF);
        $word = $byte1 | ($byte2 << 8);

        // Determine instruction format based on opcode
        [$mnemonic, $operands, $length] = $this->decodeInstruction($opcode, $byte1, $word);

        // Format bytes as hex string
        $bytes = match ($length) {
            1 => sprintf('%02X', $opcode),
            2 => sprintf('%02X %02X', $opcode, $byte1),
            3 => sprintf('%02X %02X %02X', $opcode, $byte1, $byte2),
            default => sprintf('%02X', $opcode),
        };

        return [
            'address' => $address,
            'bytes' => $bytes,
            'mnemonic' => $mnemonic,
            'operands' => $operands,
            'length' => $length,
        ];
    }

    /**
     * Disassemble a CB-prefixed instruction.
     *
     * @param int $address Instruction address (address of 0xCB)
     * @param int $cbOpcode CB opcode byte
     * @return array{address: int, bytes: string, mnemonic: string, operands: string, length: int}
     */
    private function disassembleCB(int $address, int $cbOpcode): array
    {
        $bit = ($cbOpcode >> 3) & 0x07;
        $reg = $cbOpcode & 0x07;
        $regName = ['B', 'C', 'D', 'E', 'H', 'L', '(HL)', 'A'][$reg];

        $mnemonic = match ($cbOpcode >> 6) {
            0 => ['RLC', 'RRC', 'RL', 'RR', 'SLA', 'SRA', 'SWAP', 'SRL'][($cbOpcode >> 3) & 0x07],
            1 => 'BIT',
            2 => 'RES',
            3 => 'SET',
            default => 'UNKNOWN',
        };

        $operands = match ($cbOpcode >> 6) {
            0 => $regName,
            default => "{$bit},{$regName}",
        };

        $bytes = sprintf('CB %02X', $cbOpcode);

        return [
            'address' => $address,
            'bytes' => $bytes,
            'mnemonic' => $mnemonic,
            'operands' => $operands,
            'length' => 2,
        ];
    }

    /**
     * Decode instruction mnemonic and operands from opcode.
     *
     * This is a simplified decoder that covers common instructions.
     * For complete accuracy, this would need to reference the full instruction set table.
     *
     * @param int $opcode Opcode byte
     * @param int $byte1 First operand byte
     * @param int $word 16-bit operand word
     * @return array{string, string, int} [mnemonic, operands, length]
     */
    private function decodeInstruction(int $opcode, int $byte1, int $word): array
    {
        // This is a simplified implementation
        // A complete implementation would have a full opcode table

        return match ($opcode) {
            0x00 => ['NOP', '', 1],
            0x01 => ['LD', 'BC,0x' . sprintf('%04X', $word), 3],
            0x02 => ['LD', '(BC),A', 1],
            0x03 => ['INC', 'BC', 1],
            0x04 => ['INC', 'B', 1],
            0x05 => ['DEC', 'B', 1],
            0x06 => ['LD', 'B,0x' . sprintf('%02X', $byte1), 2],
            0x07 => ['RLCA', '', 1],
            0x08 => ['LD', '(0x' . sprintf('%04X', $word) . '),SP', 3],
            0x09 => ['ADD', 'HL,BC', 1],
            0x0A => ['LD', 'A,(BC)', 1],
            0x0B => ['DEC', 'BC', 1],
            0x0C => ['INC', 'C', 1],
            0x0D => ['DEC', 'C', 1],
            0x0E => ['LD', 'C,0x' . sprintf('%02X', $byte1), 2],
            0x0F => ['RRCA', '', 1],

            0x10 => ['STOP', '', 2], // STOP takes 2 bytes
            0x11 => ['LD', 'DE,0x' . sprintf('%04X', $word), 3],
            0x12 => ['LD', '(DE),A', 1],
            0x13 => ['INC', 'DE', 1],
            0x18 => ['JR', '0x' . sprintf('%02X', $byte1), 2],
            0x1A => ['LD', 'A,(DE)', 1],

            0x20 => ['JR', 'NZ,0x' . sprintf('%02X', $byte1), 2],
            0x21 => ['LD', 'HL,0x' . sprintf('%04X', $word), 3],
            0x22 => ['LD', '(HL+),A', 1],
            0x2A => ['LD', 'A,(HL+)', 1],
            0x28 => ['JR', 'Z,0x' . sprintf('%02X', $byte1), 2],

            0x30 => ['JR', 'NC,0x' . sprintf('%02X', $byte1), 2],
            0x31 => ['LD', 'SP,0x' . sprintf('%04X', $word), 3],
            0x32 => ['LD', '(HL-),A', 1],
            0x36 => ['LD', '(HL),0x' . sprintf('%02X', $byte1), 2],
            0x38 => ['JR', 'C,0x' . sprintf('%02X', $byte1), 2],
            0x3A => ['LD', 'A,(HL-)', 1],
            0x3E => ['LD', 'A,0x' . sprintf('%02X', $byte1), 2],

            0x76 => ['HALT', '', 1],
            0x77 => ['LD', '(HL),A', 1],

            0xAF => ['XOR', 'A', 1],

            0xC0 => ['RET', 'NZ', 1],
            0xC1 => ['POP', 'BC', 1],
            0xC2 => ['JP', 'NZ,0x' . sprintf('%04X', $word), 3],
            0xC3 => ['JP', '0x' . sprintf('%04X', $word), 3],
            0xC4 => ['CALL', 'NZ,0x' . sprintf('%04X', $word), 3],
            0xC5 => ['PUSH', 'BC', 1],
            0xC8 => ['RET', 'Z', 1],
            0xC9 => ['RET', '', 1],
            0xCA => ['JP', 'Z,0x' . sprintf('%04X', $word), 3],
            0xCC => ['CALL', 'Z,0x' . sprintf('%04X', $word), 3],
            0xCD => ['CALL', '0x' . sprintf('%04X', $word), 3],

            0xD0 => ['RET', 'NC', 1],
            0xD1 => ['POP', 'DE', 1],
            0xD5 => ['PUSH', 'DE', 1],
            0xD8 => ['RET', 'C', 1],
            0xD9 => ['RETI', '', 1],

            0xE0 => ['LDH', '(0xFF' . sprintf('%02X', $byte1) . '),A', 2],
            0xE1 => ['POP', 'HL', 1],
            0xE2 => ['LD', '(C),A', 1],
            0xE5 => ['PUSH', 'HL', 1],
            0xE6 => ['AND', '0x' . sprintf('%02X', $byte1), 2],
            0xE9 => ['JP', '(HL)', 1],
            0xEA => ['LD', '(0x' . sprintf('%04X', $word) . '),A', 3],

            0xF0 => ['LDH', 'A,(0xFF' . sprintf('%02X', $byte1) . ')', 2],
            0xF1 => ['POP', 'AF', 1],
            0xF2 => ['LD', 'A,(C)', 1],
            0xF3 => ['DI', '', 1],
            0xF5 => ['PUSH', 'AF', 1],
            0xF6 => ['OR', '0x' . sprintf('%02X', $byte1), 2],
            0xFA => ['LD', 'A,(0x' . sprintf('%04X', $word) . ')', 3],
            0xFB => ['EI', '', 1],
            0xFE => ['CP', '0x' . sprintf('%02X', $byte1), 2],

            default => $this->decodeByPattern($opcode, $byte1, $word),
        };
    }

    /**
     * Decode instructions by pattern matching.
     *
     * @param int $opcode Opcode byte
     * @param int $byte1 First operand byte
     * @param int $word 16-bit operand word
     * @return array{string, string, int}
     */
    private function decodeByPattern(int $opcode, int $byte1, int $word): array
    {
        // LD r,r instructions (0x40-0x7F, excluding HALT at 0x76)
        if ($opcode >= 0x40 && $opcode <= 0x7F && $opcode !== 0x76) {
            $regs = ['B', 'C', 'D', 'E', 'H', 'L', '(HL)', 'A'];
            $dst = $regs[($opcode >> 3) & 0x07];
            $src = $regs[$opcode & 0x07];
            return ['LD', "{$dst},{$src}", 1];
        }

        // ALU operations (0x80-0xBF)
        if ($opcode >= 0x80 && $opcode <= 0xBF) {
            $ops = ['ADD', 'ADC', 'SUB', 'SBC', 'AND', 'XOR', 'OR', 'CP'];
            $regs = ['B', 'C', 'D', 'E', 'H', 'L', '(HL)', 'A'];
            $op = $ops[($opcode >> 3) & 0x07];
            $reg = $regs[$opcode & 0x07];
            $operand = $op === 'ADD' ? "A,{$reg}" : $reg;
            return [$op, $operand, 1];
        }

        // Default: unknown instruction
        return ['DB', '0x' . sprintf('%02X', $opcode), 1];
    }

    /**
     * Format disassembled instruction as a string.
     *
     * @param array{address: int, bytes: string, mnemonic: string, operands: string} $instruction
     * @return string Formatted instruction string
     */
    public static function format(array $instruction): string
    {
        $operands = $instruction['operands'] !== '' ? ' ' . $instruction['operands'] : '';
        return sprintf(
            '%04X: %-11s %s%s',
            $instruction['address'],
            $instruction['bytes'],
            $instruction['mnemonic'],
            $operands
        );
    }
}
