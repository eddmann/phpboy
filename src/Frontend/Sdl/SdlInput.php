<?php

declare(strict_types=1);

namespace Gb\Frontend\Sdl;

use Gb\Input\Button;
use Gb\Input\InputInterface;

/**
 * SDL2 Input Handler for PHPBoy.
 *
 * Maps keyboard input from SDL2 to Game Boy buttons.
 *
 * Default keyboard mapping:
 * - Arrow Keys: D-pad (Up/Down/Left/Right)
 * - Z or A: A button
 * - X or S: B button
 * - Enter/Return: Start
 * - Right Shift: Select
 *
 * The mapping can be customized via setKeyMapping().
 */
final class SdlInput implements InputInterface
{
    /** @var array<int, Button> Current pressed buttons (Button[] indexed by SDL scancode) */
    private array $pressedButtons = [];

    /** @var array<int, Button> Keyboard mapping (SDL scancode => Button) */
    private array $keyMapping = [];

    public function __construct()
    {
        if (!extension_loaded('sdl')) {
            throw new \RuntimeException('SDL extension not loaded');
        }

        // Initialize default keyboard mapping
        $this->initializeDefaultKeyMapping();
    }

    /**
     * Initialize default keyboard to Game Boy button mapping.
     */
    private function initializeDefaultKeyMapping(): void
    {
        // Arrow keys for D-pad
        $this->keyMapping[SDL_SCANCODE_UP] = Button::Up;
        $this->keyMapping[SDL_SCANCODE_DOWN] = Button::Down;
        $this->keyMapping[SDL_SCANCODE_LEFT] = Button::Left;
        $this->keyMapping[SDL_SCANCODE_RIGHT] = Button::Right;

        // Action buttons - multiple keys for convenience
        $this->keyMapping[SDL_SCANCODE_Z] = Button::A;
        $this->keyMapping[SDL_SCANCODE_A] = Button::A;

        $this->keyMapping[SDL_SCANCODE_X] = Button::B;
        $this->keyMapping[SDL_SCANCODE_S] = Button::B;

        // System buttons
        $this->keyMapping[SDL_SCANCODE_RETURN] = Button::Start;
        $this->keyMapping[SDL_SCANCODE_RSHIFT] = Button::Select;

        // Alternative mappings for convenience
        $this->keyMapping[SDL_SCANCODE_SPACE] = Button::Select;
    }

    /**
     * Set a custom key mapping for a button.
     *
     * @param int $scancode SDL scancode constant (e.g., SDL_SCANCODE_SPACE)
     * @param Button $button Game Boy button to map to
     */
    public function setKeyMapping(int $scancode, Button $button): void
    {
        $this->keyMapping[$scancode] = $button;
    }

    /**
     * Clear all key mappings.
     */
    public function clearKeyMappings(): void
    {
        $this->keyMapping = [];
    }

    /**
     * Get current key mappings.
     *
     * @return array<int, Button>
     */
    public function getKeyMappings(): array
    {
        return $this->keyMapping;
    }

    /**
     * Poll for currently pressed buttons.
     *
     * Returns array of currently pressed Game Boy buttons.
     * This should be called after SDL_PollEvent() to get the current keyboard state.
     *
     * @return Button[] Array of currently pressed buttons
     */
    public function poll(): array
    {
        // Get keyboard state from SDL
        $numKeys = 0;
        $keyState = SDL_GetKeyboardState($numKeys);

        $pressed = [];

        // Check each mapped key
        foreach ($this->keyMapping as $scancode => $button) {
            // SDL_GetKeyboardState returns 1 if key is pressed
            if ($keyState[$scancode] ?? 0) {
                // Avoid duplicates (multiple keys can map to same button)
                if (!in_array($button, $pressed, true)) {
                    $pressed[] = $button;
                }
            }
        }

        return $pressed;
    }

    /**
     * Handle SDL keyboard event.
     *
     * This method can be called from the event loop to track key presses/releases.
     * It's an alternative to using SDL_GetKeyboardState().
     *
     * @param \SDL_Event $event SDL event object
     */
    public function handleKeyEvent(\SDL_Event $event): void
    {
        if (!isset($event->key)) {
            return;
        }

        $scancode = $event->key->keysym->scancode ?? null;
        if ($scancode === null) {
            return;
        }

        // Check if this scancode is mapped
        if (!isset($this->keyMapping[$scancode])) {
            return;
        }

        $button = $this->keyMapping[$scancode];

        if ($event->type === SDL_KEYDOWN) {
            // Key pressed
            $this->pressedButtons[$scancode] = $button;
        } elseif ($event->type === SDL_KEYUP) {
            // Key released
            unset($this->pressedButtons[$scancode]);
        }
    }

    /**
     * Get currently pressed buttons (when using event-based handling).
     *
     * This is used with handleKeyEvent() for event-based input tracking.
     *
     * @return Button[] Array of currently pressed buttons
     */
    public function getPressedButtons(): array
    {
        // Remove duplicates and return unique buttons
        return array_values(array_unique($this->pressedButtons, SORT_REGULAR));
    }

    /**
     * Check if a specific button is currently pressed.
     *
     * @param Button $button Button to check
     * @return bool True if button is pressed
     */
    public function isButtonPressed(Button $button): bool
    {
        return in_array($button, $this->poll(), true);
    }

    /**
     * Get a human-readable description of current key mappings.
     *
     * @return string Description of key mappings
     */
    public function getKeyMappingDescription(): string
    {
        $scancodeNames = [
            SDL_SCANCODE_UP => 'Up Arrow',
            SDL_SCANCODE_DOWN => 'Down Arrow',
            SDL_SCANCODE_LEFT => 'Left Arrow',
            SDL_SCANCODE_RIGHT => 'Right Arrow',
            SDL_SCANCODE_Z => 'Z',
            SDL_SCANCODE_X => 'X',
            SDL_SCANCODE_A => 'A',
            SDL_SCANCODE_S => 'S',
            SDL_SCANCODE_RETURN => 'Enter',
            SDL_SCANCODE_RSHIFT => 'Right Shift',
            SDL_SCANCODE_SPACE => 'Space',
        ];

        $lines = ["Keyboard Controls:"];
        $lines[] = str_repeat('-', 30);

        $grouped = [];
        foreach ($this->keyMapping as $scancode => $button) {
            $keyName = $scancodeNames[$scancode] ?? "Scancode $scancode";
            $buttonName = $button->name;

            if (!isset($grouped[$buttonName])) {
                $grouped[$buttonName] = [];
            }
            $grouped[$buttonName][] = $keyName;
        }

        foreach ($grouped as $buttonName => $keys) {
            $keyList = implode(' or ', $keys);
            $lines[] = sprintf('%-15s: %s', $buttonName, $keyList);
        }

        return implode("\n", $lines);
    }

    /**
     * Print key mapping description to stdout.
     */
    public function printKeyMappings(): void
    {
        echo $this->getKeyMappingDescription() . "\n";
    }
}
