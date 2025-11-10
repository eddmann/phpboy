<?php

declare(strict_types=1);

namespace Tests\Unit\Frontend\Cli;

use Gb\Frontend\Cli\CliInput;
use Gb\Input\Button;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CliInputTest extends TestCase
{
    #[Test]
    public function it_parses_arrow_key_escape_sequences(): void
    {
        $input = new CliInput();

        // Use reflection to test private parseInput method
        $reflection = new \ReflectionClass($input);
        $parseMethod = $reflection->getMethod('parseInput');
        $parseMethod->setAccessible(true);

        // Test Up arrow (ESC [ A)
        $parseMethod->invoke($input, "\033[A");
        $buttons = $input->poll();
        $this->assertContains(Button::Up, $buttons);

        // Test Down arrow (ESC [ B)
        $parseMethod->invoke($input, "\033[B");
        $buttons = $input->poll();
        $this->assertContains(Button::Down, $buttons);

        // Test Right arrow (ESC [ C)
        $parseMethod->invoke($input, "\033[C");
        $buttons = $input->poll();
        $this->assertContains(Button::Right, $buttons);

        // Test Left arrow (ESC [ D)
        $parseMethod->invoke($input, "\033[D");
        $buttons = $input->poll();
        $this->assertContains(Button::Left, $buttons);
    }

    #[Test]
    public function it_parses_wasd_keys(): void
    {
        $input = new CliInput();
        $reflection = new \ReflectionClass($input);
        $parseMethod = $reflection->getMethod('parseInput');
        $parseMethod->setAccessible(true);

        // Test W (up)
        $parseMethod->invoke($input, 'w');
        $buttons = $input->poll();
        $this->assertContains(Button::Up, $buttons);

        // Test S (down)
        $parseMethod->invoke($input, 's');
        $buttons = $input->poll();
        $this->assertContains(Button::Down, $buttons);

        // Test A (left)
        $parseMethod->invoke($input, 'a');
        $buttons = $input->poll();
        $this->assertContains(Button::Left, $buttons);

        // Test D (right)
        $parseMethod->invoke($input, 'd');
        $buttons = $input->poll();
        $this->assertContains(Button::Right, $buttons);
    }

    #[Test]
    public function it_parses_action_buttons(): void
    {
        $input = new CliInput();
        $reflection = new \ReflectionClass($input);
        $parseMethod = $reflection->getMethod('parseInput');
        $parseMethod->setAccessible(true);

        // Test Z (A button)
        $parseMethod->invoke($input, 'z');
        $buttons = $input->poll();
        $this->assertContains(Button::A, $buttons);

        // Test X (B button)
        $parseMethod->invoke($input, 'x');
        $buttons = $input->poll();
        $this->assertContains(Button::B, $buttons);

        // Test Enter (Start)
        $parseMethod->invoke($input, "\n");
        $buttons = $input->poll();
        $this->assertContains(Button::Start, $buttons);

        // Test Space (Select)
        $parseMethod->invoke($input, ' ');
        $buttons = $input->poll();
        $this->assertContains(Button::Select, $buttons);
    }

    #[Test]
    public function it_holds_buttons_for_minimum_frames(): void
    {
        $input = new CliInput();
        $reflection = new \ReflectionClass($input);
        $parseMethod = $reflection->getMethod('parseInput');
        $parseMethod->setAccessible(true);

        // Press A button
        $parseMethod->invoke($input, 'z');
        $buttons = $input->poll();
        $this->assertCount(1, $buttons);
        $this->assertContains(Button::A, $buttons);

        // Button should still be pressed for MIN_HOLD_FRAMES (3) polls
        // Poll 1: hold counter = 2
        $buttons = $input->poll();
        $this->assertContains(Button::A, $buttons);

        // Poll 2: hold counter = 1
        $buttons = $input->poll();
        $this->assertContains(Button::A, $buttons);

        // Poll 3: hold counter = 0, button released
        $buttons = $input->poll();
        $this->assertEmpty($buttons);
    }

    #[Test]
    public function it_handles_multiple_character_input(): void
    {
        $input = new CliInput();
        $reflection = new \ReflectionClass($input);
        $parseMethod = $reflection->getMethod('parseInput');
        $parseMethod->setAccessible(true);

        // Multiple keys in one read (though unlikely in practice)
        // Only the last one should be registered due to clearing
        $parseMethod->invoke($input, 'zx');
        $buttons = $input->poll();

        // Should have parsed both but only X remains (last character)
        $this->assertContains(Button::B, $buttons);
    }

    #[Test]
    public function it_ignores_unknown_characters(): void
    {
        $input = new CliInput();
        $reflection = new \ReflectionClass($input);
        $parseMethod = $reflection->getMethod('parseInput');
        $parseMethod->setAccessible(true);

        // Unknown characters should be ignored
        $parseMethod->invoke($input, 'q');
        $buttons = $input->poll();
        $this->assertEmpty($buttons);

        $parseMethod->invoke($input, '1');
        $buttons = $input->poll();
        $this->assertEmpty($buttons);
    }

    #[Test]
    public function it_handles_incomplete_escape_sequences(): void
    {
        $input = new CliInput();
        $reflection = new \ReflectionClass($input);
        $parseMethod = $reflection->getMethod('parseInput');
        $parseMethod->setAccessible(true);

        // Incomplete escape sequence (just ESC)
        $parseMethod->invoke($input, "\033");
        $buttons = $input->poll();
        $this->assertEmpty($buttons);

        // Incomplete escape sequence (ESC + [)
        $parseMethod->invoke($input, "\033[");
        $buttons = $input->poll();
        $this->assertEmpty($buttons);
    }

    #[Test]
    public function it_returns_empty_array_when_no_input(): void
    {
        $input = new CliInput();

        // Poll without any input
        $buttons = $input->poll();
        $this->assertEmpty($buttons);
    }

    #[Test]
    public function it_handles_carriage_return_as_start(): void
    {
        $input = new CliInput();
        $reflection = new \ReflectionClass($input);
        $parseMethod = $reflection->getMethod('parseInput');
        $parseMethod->setAccessible(true);

        // Test carriage return (also maps to Start)
        $parseMethod->invoke($input, "\r");
        $buttons = $input->poll();
        $this->assertContains(Button::Start, $buttons);
    }
}
