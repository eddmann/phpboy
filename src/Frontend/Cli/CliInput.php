<?php

declare(strict_types=1);

namespace Gb\Frontend\Cli;

use Gb\Input\Button;
use Gb\Input\InputInterface;

/**
 * CLI keyboard input handler for PHPBoy.
 *
 * Maps keyboard keys to Game Boy buttons:
 * - Arrow keys → D-pad (Up, Down, Left, Right)
 * - Z → A button
 * - X → B button
 * - Enter → Start
 * - Space → Select
 * - W/A/S/D → D-pad (alternative)
 *
 * Note: Non-blocking keyboard input in PHP CLI is limited.
 * This implementation uses stream_select for non-blocking reads
 * when possible, but may require terminal mode setup for optimal behavior.
 *
 * Limitation: Cannot detect key-up events in raw terminal mode, so buttons
 * remain pressed until a different key is pressed.
 *
 * Future enhancement: Use ncurses extension or external library
 * for better keyboard handling with proper key-up detection.
 */
final class CliInput implements InputInterface
{
    /** @var array<string, Button> Keyboard key to button mapping */
    private const KEY_MAP = [
        // Arrow keys (ANSI escape sequences)
        "\033[A" => Button::Up,
        "\033[B" => Button::Down,
        "\033[C" => Button::Right,
        "\033[D" => Button::Left,
        // Alternative WASD controls
        'w' => Button::Up,
        's' => Button::Down,
        'a' => Button::Left,
        'd' => Button::Right,
        // Action buttons
        'z' => Button::A,
        'x' => Button::B,
        // System buttons
        "\n" => Button::Start,    // Enter key
        "\r" => Button::Start,    // Carriage return
        ' ' => Button::Select,    // Space for Select
    ];

    /** @var Button[] Currently pressed buttons */
    private array $pressedButtons = [];

    /** @var resource|null STDIN resource */
    private $stdin;

    /** @var bool Whether terminal mode has been set */
    private bool $terminalModeSet = false;

    public function __construct()
    {
        $this->stdin = STDIN;
        $this->setupTerminal();
    }

    public function __destruct()
    {
        $this->restoreTerminal();
    }

    /**
     * Poll for currently pressed buttons.
     *
     * Attempts to read keyboard input in a non-blocking manner.
     * Returns the set of buttons currently considered "pressed".
     *
     * @return Button[] Array of currently pressed buttons
     */
    public function poll(): array
    {
        $this->readAvailableInput();
        return array_values($this->pressedButtons);
    }

    /**
     * Setup terminal for non-blocking input (Unix-like systems only).
     */
    private function setupTerminal(): void
    {
        if (!$this->isUnix()) {
            return;
        }

        // Save current terminal settings and set to raw mode
        // This allows reading individual keystrokes without waiting for Enter
        if (function_exists('shell_exec')) {
            shell_exec('stty -icanon -echo');
            $this->terminalModeSet = true;
        }
    }

    /**
     * Restore terminal to normal mode.
     */
    private function restoreTerminal(): void
    {
        if (!$this->terminalModeSet || !function_exists('shell_exec')) {
            return;
        }

        shell_exec('stty icanon echo');
        $this->terminalModeSet = false;
    }

    /**
     * Read available input from STDIN without blocking.
     */
    private function readAvailableInput(): void
    {
        if ($this->stdin === null) {
            return;
        }

        // Use stream_select to check if input is available
        $read = [$this->stdin];
        $write = null;
        $except = null;
        $timeout = 0;

        // Check if there's data available to read
        $result = @stream_select($read, $write, $except, $timeout);

        if ($result === false || $result === 0) {
            // No input available or error
            return;
        }

        // Read available input
        $input = fread($this->stdin, 10);

        if ($input === false || $input === '') {
            return;
        }

        // Parse input and update button states
        $this->parseInput($input);
    }

    /**
     * Parse input string and update button states.
     *
     * When new input is detected, this method:
     * 1. Identifies which buttons are pressed in the current input
     * 2. Clears buttons that were NOT pressed in this input (simulating release)
     * 3. Adds newly pressed buttons
     *
     * This approach allows:
     * - Holding keys works (generates repeated events)
     * - Multiple simultaneous button presses
     * - Buttons get "released" when you press other keys
     *
     * Limitation: Cannot release ALL buttons without pressing at least one key
     * (inherent PHP CLI limitation without ncurses)
     *
     * @param string $input Raw input from STDIN
     */
    private function parseInput(string $input): void
    {
        // Track which buttons are pressed in THIS input event
        $buttonsInCurrentInput = [];

        // Check for arrow key escape sequences (3 characters)
        if (strlen($input) >= 3 && $input[0] === "\033" && $input[1] === '[') {
            $sequence = substr($input, 0, 3);
            if (isset(self::KEY_MAP[$sequence])) {
                $button = self::KEY_MAP[$sequence];
                $buttonsInCurrentInput[$button->name] = $button;
            }
        } else {
            // Check for simple character mappings
            for ($i = 0; $i < strlen($input); $i++) {
                $char = $input[$i];
                if (isset(self::KEY_MAP[$char])) {
                    $button = self::KEY_MAP[$char];
                    $buttonsInCurrentInput[$button->name] = $button;
                }
            }
        }

        // Clear buttons that were NOT in this input event (they were released)
        // This simulates button release behavior
        $this->pressedButtons = $buttonsInCurrentInput;
    }

    /**
     * Check if running on Unix-like system.
     */
    private function isUnix(): bool
    {
        return PHP_OS_FAMILY !== 'Windows';
    }
}
