<?php

declare(strict_types=1);

namespace Gb\Frontend\Wasm;

use Gb\Input\Button;
use Gb\Input\InputInterface;

/**
 * WebAssembly input bridge for PHPBoy in the browser.
 *
 * This stub provides an interface for JavaScript keyboard events
 * to be passed into the PHP emulator running via WebAssembly.
 *
 * Expected JavaScript integration:
 * ```javascript
 * // JavaScript side (browser)
 * document.addEventListener('keydown', (e) => {
 *   // Call exported WASM function to set button state
 *   wasmInstance.exports.phpboy_button_press(buttonCode);
 * });
 *
 * document.addEventListener('keyup', (e) => {
 *   // Call exported WASM function to release button state
 *   wasmInstance.exports.phpboy_button_release(buttonCode);
 * });
 * ```
 *
 * Recommended key mappings for browser:
 * - Arrow keys → D-pad
 * - Z or A key → A button
 * - X or S key → B button
 * - Enter → Start
 * - Shift → Select
 *
 * Implementation will be completed in Step 15 (WebAssembly Target).
 */
final class WasmInput implements InputInterface
{
    /** @var array<string, bool> Button state (button name => pressed) */
    private array $buttonState = [];

    public function __construct()
    {
        // Initialize all buttons as not pressed
        foreach (Button::cases() as $button) {
            $this->buttonState[$button->name] = false;
        }
    }

    /**
     * Poll for currently pressed buttons.
     *
     * @return Button[] Array of currently pressed buttons
     */
    public function poll(): array
    {
        $pressed = [];

        foreach (Button::cases() as $button) {
            if ($this->buttonState[$button->name]) {
                $pressed[] = $button;
            }
        }

        return $pressed;
    }

    /**
     * Set button as pressed.
     *
     * This method will be called from JavaScript via FFI/exported function.
     *
     * @param Button $button Button to press
     */
    public function pressButton(Button $button): void
    {
        $this->buttonState[$button->name] = true;
    }

    /**
     * Set button as released.
     *
     * This method will be called from JavaScript via FFI/exported function.
     *
     * @param Button $button Button to release
     */
    public function releaseButton(Button $button): void
    {
        $this->buttonState[$button->name] = false;
    }

    /**
     * Set button state from JavaScript button code.
     *
     * Expected button codes (can be adjusted during WASM implementation):
     * - 0: A
     * - 1: B
     * - 2: Start
     * - 3: Select
     * - 4: Up
     * - 5: Down
     * - 6: Left
     * - 7: Right
     *
     * @param int $buttonCode Button code from JavaScript
     * @param bool $pressed Whether the button is pressed
     */
    public function setButtonState(int $buttonCode, bool $pressed): void
    {
        $button = $this->getButtonFromCode($buttonCode);

        if ($button === null) {
            return;
        }

        $this->buttonState[$button->name] = $pressed;
    }

    /**
     * Map button code to Button enum.
     *
     * @param int $code Button code (0-7)
     * @return Button|null Button or null if invalid code
     */
    private function getButtonFromCode(int $code): ?Button
    {
        return match ($code) {
            0 => Button::A,
            1 => Button::B,
            2 => Button::Start,
            3 => Button::Select,
            4 => Button::Up,
            5 => Button::Down,
            6 => Button::Left,
            7 => Button::Right,
            default => null,
        };
    }
}
