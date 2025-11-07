<?php

declare(strict_types=1);

namespace Gb\Support;

/**
 * BitOps - Bitwise operation utilities for Game Boy emulation
 *
 * Provides static helper methods for common bit manipulation operations
 * used throughout the emulator, particularly in CPU instruction implementations.
 */
final class BitOps
{
    /**
     * Extract a bit at the specified position
     *
     * @param int $byte The byte to extract from (0x00-0xFF)
     * @param int $position The bit position (0-7, where 0 is LSB)
     * @return bool True if bit is set, false otherwise
     */
    public static function getBit(int $byte, int $position): bool
    {
        return (($byte >> $position) & 1) === 1;
    }

    /**
     * Set or clear a bit at the specified position
     *
     * @param int $byte The byte to modify (0x00-0xFF)
     * @param int $position The bit position (0-7)
     * @param bool $value True to set bit, false to clear
     * @return int The modified byte
     */
    public static function setBit(int $byte, int $position, bool $value): int
    {
        if ($value) {
            return $byte | (1 << $position);
        }
        return $byte & ~(1 << $position);
    }

    /**
     * Rotate left through carry (RLC)
     * Bit 7 goes to carry flag, carry goes to bit 0
     *
     * @param int $byte The byte to rotate
     * @param bool $carry Current carry flag value
     * @return array{int, bool} [result byte, new carry flag]
     */
    public static function rotateLeft(int $byte, bool $carry): array
    {
        $bit7 = ($byte & 0x80) !== 0;
        $result = (($byte << 1) & 0xFF) | ($carry ? 1 : 0);
        return [$result, $bit7];
    }

    /**
     * Rotate right through carry (RRC)
     * Bit 0 goes to carry flag, carry goes to bit 7
     *
     * @param int $byte The byte to rotate
     * @param bool $carry Current carry flag value
     * @return array{int, bool} [result byte, new carry flag]
     */
    public static function rotateRight(int $byte, bool $carry): array
    {
        $bit0 = ($byte & 0x01) !== 0;
        $result = ($byte >> 1) | ($carry ? 0x80 : 0);
        return [$result, $bit0];
    }

    /**
     * Shift left arithmetic (SLA)
     * Bit 7 goes to carry, bit 0 becomes 0
     *
     * @param int $byte The byte to shift
     * @return array{int, bool} [result byte, new carry flag]
     */
    public static function shiftLeft(int $byte): array
    {
        $carry = ($byte & 0x80) !== 0;
        $result = ($byte << 1) & 0xFF;
        return [$result, $carry];
    }

    /**
     * Shift right (SRA for arithmetic, SRL for logical)
     * Bit 0 goes to carry, bit 7 becomes 0 (logical) or preserves sign (arithmetic)
     *
     * @param int $byte The byte to shift
     * @param bool $signed True for arithmetic shift (preserve bit 7), false for logical
     * @return array{int, bool} [result byte, new carry flag]
     */
    public static function shiftRight(int $byte, bool $signed): array
    {
        $carry = ($byte & 0x01) !== 0;
        $result = $byte >> 1;

        if ($signed) {
            // Preserve bit 7 for arithmetic shift
            $result |= ($byte & 0x80);
        }

        return [$result, $carry];
    }

    /**
     * Swap nibbles (upper and lower 4 bits)
     * Used by SWAP instruction
     *
     * @param int $byte The byte to swap (0x00-0xFF)
     * @return int Result with swapped nibbles
     */
    public static function swap(int $byte): int
    {
        $upper = ($byte & 0xF0) >> 4;
        $lower = ($byte & 0x0F) << 4;
        return $upper | $lower;
    }
}
