<?php

declare(strict_types=1);

namespace Gb\Input;

use Gb\Bus\DeviceInterface;
use Gb\Interrupts\InterruptController;
use Gb\Interrupts\InterruptType;

/**
 * Game Boy Joypad (JOYP) Controller.
 *
 * Emulates the JOYP register at 0xFF00, which multiplexes 8 buttons
 * across a 2x4 matrix to minimize pin count.
 *
 * Register layout:
 * Bit 7-6: Not used (always 1)
 * Bit 5: Select direction keys (0=selected)
 * Bit 4: Select button keys (0=selected)
 * Bit 3-0: Input lines (0=pressed, 1=not pressed)
 *
 * When bit 5 is clear (direction mode):
 *   Bit 3: Down
 *   Bit 2: Up
 *   Bit 1: Left
 *   Bit 0: Right
 *
 * When bit 4 is clear (button mode):
 *   Bit 3: Start
 *   Bit 2: Select
 *   Bit 1: B
 *   Bit 0: A
 *
 * Pressing any button (transition from 1 to 0) requests a joypad interrupt.
 *
 * Reference: Pan Docs - Joypad Input
 */
final class Joypad implements DeviceInterface
{
    private const JOYP_ADDRESS = 0xFF00;

    /** @var array<string, bool> Button state map (button name => pressed) */
    private array $buttonState = [];

    /**
     * JOYP register value.
     * Bits 5-4 control which button group is selected for reading.
     */
    private int $joyp = 0xFF;

    public function __construct(
        private readonly InterruptController $interruptController,
    ) {
        // Initialize all buttons as not pressed
        foreach (Button::cases() as $button) {
            $this->buttonState[$button->name] = false;
        }
    }

    /**
     * Press a button and request joypad interrupt.
     */
    public function pressButton(Button $button): void
    {
        $wasPressed = $this->buttonState[$button->name];
        $this->buttonState[$button->name] = true;

        // Request interrupt on high-to-low transition (button press)
        if (!$wasPressed) {
            $this->interruptController->requestInterrupt(InterruptType::Joypad);
        }
    }

    /**
     * Release a button.
     */
    public function releaseButton(Button $button): void
    {
        $this->buttonState[$button->name] = false;
    }

    /**
     * Update button states from an input source.
     *
     * @param Button[] $pressedButtons Array of currently pressed buttons
     */
    public function updateFromInput(array $pressedButtons): void
    {
        // Convert array to set for faster lookup
        $pressedSet = array_fill_keys(array_map(fn($b) => $b->name, $pressedButtons), true);

        // Update each button state
        foreach (Button::cases() as $button) {
            $isPressed = isset($pressedSet[$button->name]);
            $wasPressed = $this->buttonState[$button->name];

            if ($isPressed && !$wasPressed) {
                $this->pressButton($button);
            } elseif (!$isPressed && $wasPressed) {
                $this->releaseButton($button);
            }
        }
    }

    /**
     * Read the JOYP register.
     */
    public function readByte(int $address): int
    {
        if ($address !== self::JOYP_ADDRESS) {
            return 0xFF;
        }

        // Start with bits 7-6 set, bits 5-4 from joyp, input bits set (unpressed)
        $value = 0xC0 | ($this->joyp & 0x30) | 0x0F; // Bits 7-6=1, bits 5-4 echoed, bits 3-0=1

        // Check direction keys (when bit 5 is clear)
        $selectDirections = ($this->joyp & 0x20) === 0;
        if ($selectDirections) {
            // Bits are 0 when pressed, 1 when not pressed
            // Clear bits for pressed buttons
            if ($this->buttonState[Button::Down->name]) {
                $value &= ~0x08;
            }
            if ($this->buttonState[Button::Up->name]) {
                $value &= ~0x04;
            }
            if ($this->buttonState[Button::Left->name]) {
                $value &= ~0x02;
            }
            if ($this->buttonState[Button::Right->name]) {
                $value &= ~0x01;
            }
        }

        // Check action/system buttons (when bit 4 is clear)
        $selectButtons = ($this->joyp & 0x10) === 0;
        if ($selectButtons) {
            // Bits are 0 when pressed, 1 when not pressed
            // Clear bits for pressed buttons
            if ($this->buttonState[Button::Start->name]) {
                $value &= ~0x08;
            }
            if ($this->buttonState[Button::Select->name]) {
                $value &= ~0x04;
            }
            if ($this->buttonState[Button::B->name]) {
                $value &= ~0x02;
            }
            if ($this->buttonState[Button::A->name]) {
                $value &= ~0x01;
            }
        }

        return $value;
    }

    /**
     * Write to the JOYP register.
     * Only bits 5-4 are writable (button group selection).
     */
    public function writeByte(int $address, int $value): void
    {
        if ($address === self::JOYP_ADDRESS) {
            // Only bits 5-4 are writable
            $this->joyp = ($value & 0x30) | 0xC0;
        }
    }
}
