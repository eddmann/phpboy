<?php

declare(strict_types=1);

namespace Gb\Input;

/**
 * Game Boy button enumeration.
 *
 * Represents the eight physical buttons on the Game Boy:
 * - Direction pad: Up, Down, Left, Right
 * - Action buttons: A, B
 * - System buttons: Start, Select
 */
enum Button
{
    case A;
    case B;
    case Start;
    case Select;
    case Up;
    case Down;
    case Left;
    case Right;

    /**
     * Check if this button is a direction button.
     */
    public function isDirection(): bool
    {
        return match ($this) {
            self::Up, self::Down, self::Left, self::Right => true,
            default => false,
        };
    }

    /**
     * Check if this button is an action/system button.
     */
    public function isButton(): bool
    {
        return !$this->isDirection();
    }

    /**
     * Get the bit position for this button within its group.
     * Returns 0-3 for the bit position in the JOYP register.
     */
    public function getBitPosition(): int
    {
        return match ($this) {
            // Direction buttons (when bit 4 is selected)
            self::Down => 3,
            self::Up => 2,
            self::Left => 1,
            self::Right => 0,
            // Action/system buttons (when bit 5 is selected)
            self::Start => 3,
            self::Select => 2,
            self::B => 1,
            self::A => 0,
        };
    }

    /**
     * Convert a button name string to a Button enum case.
     */
    public static function fromName(string $name): self
    {
        return match ($name) {
            'A' => self::A,
            'B' => self::B,
            'Start' => self::Start,
            'Select' => self::Select,
            'Up' => self::Up,
            'Down' => self::Down,
            'Left' => self::Left,
            'Right' => self::Right,
            default => throw new \ValueError("Invalid button name: $name"),
        };
    }
}
