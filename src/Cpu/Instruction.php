<?php

declare(strict_types=1);

namespace Gb\Cpu;

/**
 * CPU Instruction
 *
 * Represents a single CPU instruction with its metadata and execution handler.
 * The LR35902 CPU (Sharp SM83) has 256 base opcodes plus 256 CB-prefixed opcodes.
 */
final readonly class Instruction
{
    /**
     * @param int $opcode The opcode byte (0x00-0xFF, or 0xCB00-0xCBFF for CB-prefixed)
     * @param string $mnemonic Human-readable instruction name (e.g., "LD A,B", "NOP")
     * @param int $length Instruction length in bytes (1-3)
     * @param int $cycles Base cycle cost (4, 8, 12, 16, 20, 24)
     * @param callable(Cpu): int $handler Execution handler that returns actual cycles consumed
     */
    public function __construct(
        public int $opcode,
        public string $mnemonic,
        public int $length,
        public int $cycles,
        public mixed $handler,
    ) {
    }
}
