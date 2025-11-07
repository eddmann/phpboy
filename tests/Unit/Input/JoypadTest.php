<?php

declare(strict_types=1);

namespace Tests\Unit\Input;

use Gb\Input\Button;
use Gb\Input\Joypad;
use Gb\Interrupts\InterruptController;
use Gb\Interrupts\InterruptType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JoypadTest extends TestCase
{
    private InterruptController $interruptController;
    private Joypad $joypad;

    protected function setUp(): void
    {
        $this->interruptController = new InterruptController();
        $this->joypad = new Joypad($this->interruptController);
    }

    #[Test]
    public function it_initializes_with_no_buttons_pressed(): void
    {
        // With no selection, all bits should be 1 (not pressed)
        $this->assertSame(0xFF, $this->joypad->readByte(0xFF00));
    }

    #[Test]
    public function it_reads_direction_keys_when_bit_5_is_clear(): void
    {
        // Select direction keys (bit 5 = 0)
        $this->joypad->writeByte(0xFF00, 0xDF);

        // Press Down button (bit 3 should become 0)
        $this->joypad->pressButton(Button::Down);
        $value = $this->joypad->readByte(0xFF00);
        $this->assertSame(0xD7, $value); // Bits 7-6=1, bits 5-4=01, bit 3=0, bits 2-0=1

        // Press Up button (bit 2 should become 0)
        $this->joypad->pressButton(Button::Up);
        $value = $this->joypad->readByte(0xFF00);
        $this->assertSame(0xD3, $value); // Bits 3 and 2 now 0

        // Press Left button (bit 1 should become 0)
        $this->joypad->pressButton(Button::Left);
        $value = $this->joypad->readByte(0xFF00);
        $this->assertSame(0xD1, $value); // Bits 3, 2, 1 now 0

        // Press Right button (bit 0 should become 0)
        $this->joypad->pressButton(Button::Right);
        $value = $this->joypad->readByte(0xFF00);
        $this->assertSame(0xD0, $value); // All direction bits now 0
    }

    #[Test]
    public function it_reads_action_keys_when_bit_4_is_clear(): void
    {
        // Select action keys (bit 4 = 0)
        $this->joypad->writeByte(0xFF00, 0xEF);

        // Press Start button (bit 3 should become 0)
        $this->joypad->pressButton(Button::Start);
        $value = $this->joypad->readByte(0xFF00);
        $this->assertSame(0xE7, $value); // Bits 7-6=1, bits 5-4=10, bit 3=0, bits 2-0=1

        // Press Select button (bit 2 should become 0)
        $this->joypad->pressButton(Button::Select);
        $value = $this->joypad->readByte(0xFF00);
        $this->assertSame(0xE3, $value); // Bits 3 and 2 now 0

        // Press B button (bit 1 should become 0)
        $this->joypad->pressButton(Button::B);
        $value = $this->joypad->readByte(0xFF00);
        $this->assertSame(0xE1, $value); // Bits 3, 2, 1 now 0

        // Press A button (bit 0 should become 0)
        $this->joypad->pressButton(Button::A);
        $value = $this->joypad->readByte(0xFF00);
        $this->assertSame(0xE0, $value); // All action bits now 0
    }

    #[Test]
    public function it_ignores_direction_keys_when_bit_5_is_set(): void
    {
        // Select action keys only (bit 5 = 1, bit 4 = 0)
        $this->joypad->writeByte(0xFF00, 0xEF);

        // Press direction buttons - they should not show up
        $this->joypad->pressButton(Button::Down);
        $this->joypad->pressButton(Button::Up);
        $this->joypad->pressButton(Button::Left);
        $this->joypad->pressButton(Button::Right);

        // Reading should show no buttons pressed (bits 3-0 all 1)
        $value = $this->joypad->readByte(0xFF00);
        $this->assertSame(0xEF, $value); // Bits 5-4=10, bits 3-0 all set (direction buttons ignored)
    }

    #[Test]
    public function it_ignores_action_keys_when_bit_4_is_set(): void
    {
        // Select direction keys only (bit 5 = 0, bit 4 = 1)
        $this->joypad->writeByte(0xFF00, 0xDF);

        // Press action buttons - they should not show up
        $this->joypad->pressButton(Button::Start);
        $this->joypad->pressButton(Button::Select);
        $this->joypad->pressButton(Button::B);
        $this->joypad->pressButton(Button::A);

        // Reading should show no buttons pressed (bits 3-0 all 1)
        $value = $this->joypad->readByte(0xFF00);
        $this->assertSame(0xDF, $value); // Only bits 7-6 and 4 and 3-0 set
    }

    #[Test]
    public function it_reads_both_groups_when_both_selection_bits_are_clear(): void
    {
        // Select both groups (bits 5 and 4 = 0)
        $this->joypad->writeByte(0xFF00, 0xCF);

        // Press one button from each group
        $this->joypad->pressButton(Button::Down);  // Direction, bit 3
        $this->joypad->pressButton(Button::A);     // Action, bit 0

        // Both should show up
        $value = $this->joypad->readByte(0xFF00);
        $this->assertSame(0xC6, $value); // Bits 3 and 0 are 0
    }

    #[Test]
    public function it_requests_interrupt_on_button_press(): void
    {
        // Enable joypad interrupt
        $this->interruptController->writeByte(0xFFFF, 0x10); // Bit 4 = Joypad

        // Initially no interrupt
        $this->assertNull($this->interruptController->getPendingInterrupt());

        // Press a button
        $this->joypad->pressButton(Button::A);

        // Interrupt should be requested
        $interrupt = $this->interruptController->getPendingInterrupt();
        $this->assertSame(InterruptType::Joypad, $interrupt);
    }

    #[Test]
    public function it_does_not_request_interrupt_on_button_release(): void
    {
        // Press and release a button
        $this->joypad->pressButton(Button::A);

        // Clear the interrupt flag
        $this->interruptController->writeByte(0xFF0F, 0xE0);

        // Release button
        $this->joypad->releaseButton(Button::A);

        // No new interrupt should be requested
        $value = $this->interruptController->readByte(0xFF0F);
        $this->assertSame(0xE0, $value); // No interrupts
    }

    #[Test]
    public function it_does_not_request_interrupt_when_button_already_pressed(): void
    {
        // Press a button
        $this->joypad->pressButton(Button::A);

        // Clear the interrupt
        $this->interruptController->writeByte(0xFF0F, 0xE0);

        // Press the same button again
        $this->joypad->pressButton(Button::A);

        // No new interrupt should be requested
        $value = $this->interruptController->readByte(0xFF0F);
        $this->assertSame(0xE0, $value); // No interrupts
    }

    #[Test]
    public function it_shows_released_buttons_as_not_pressed(): void
    {
        // Select action keys
        $this->joypad->writeByte(0xFF00, 0xEF);

        // Press and release A button
        $this->joypad->pressButton(Button::A);
        $this->assertSame(0xEE, $this->joypad->readByte(0xFF00)); // Bits 5-4=10, bit 0 = 0

        $this->joypad->releaseButton(Button::A);
        $this->assertSame(0xEF, $this->joypad->readByte(0xFF00)); // Bits 5-4=10, bit 0 = 1
    }

    #[Test]
    public function it_handles_multiple_simultaneous_button_presses(): void
    {
        // Select both groups
        $this->joypad->writeByte(0xFF00, 0xCF);

        // Press multiple buttons
        $this->joypad->pressButton(Button::A);
        $this->joypad->pressButton(Button::B);
        $this->joypad->pressButton(Button::Up);
        $this->joypad->pressButton(Button::Down);

        // All should be reflected
        $value = $this->joypad->readByte(0xFF00);
        $this->assertSame(0xC0, $value); // Bits 3, 2, 1, 0 all 0
    }

    #[Test]
    public function it_updates_from_input_array(): void
    {
        // Select direction keys
        $this->joypad->writeByte(0xFF00, 0xDF);

        // Update with array of pressed buttons
        $this->joypad->updateFromInput([Button::Up, Button::Right]);

        $value = $this->joypad->readByte(0xFF00);
        // Bit 2 (Up) and bit 0 (Right) should be 0
        $this->assertSame(0xDA, $value); // Bits 5-4=01, bit 3=1, bit 2=0, bit 1=1, bit 0=0

        // Update again with different buttons
        $this->joypad->updateFromInput([Button::Down, Button::Left]);

        $value = $this->joypad->readByte(0xFF00);
        // Bit 3 (Down) and bit 1 (Left) should be 0
        $this->assertSame(0xD5, $value); // Bits 5-4=01, bit 3=0, bit 1=0

        // Update with no buttons
        $this->joypad->updateFromInput([]);

        $value = $this->joypad->readByte(0xFF00);
        // All bits should be 1 (no buttons pressed)
        $this->assertSame(0xDF, $value); // Bits 5-4=01, bits 3-0=1
    }

    #[Test]
    public function it_only_writes_bits_5_and_4(): void
    {
        // Try to write all bits
        $this->joypad->writeByte(0xFF00, 0x00);

        // Read back - only bits 5-4 should be affected
        // Bits 7-6 are always 1, bits 3-0 depend on button state (all 1 if no buttons pressed)
        $value = $this->joypad->readByte(0xFF00);
        $this->assertSame(0xCF, $value); // Bits 7-6=1, bits 5-4=0, bits 3-0=1

        // Try to write with bits 5-4 set
        $this->joypad->writeByte(0xFF00, 0xFF);
        $value = $this->joypad->readByte(0xFF00);
        $this->assertSame(0xFF, $value); // Bits 5-4=1
    }

    #[Test]
    public function it_returns_0xff_for_non_joyp_addresses(): void
    {
        $this->assertSame(0xFF, $this->joypad->readByte(0xFF01));
        $this->assertSame(0xFF, $this->joypad->readByte(0xFF0F));
        $this->assertSame(0xFF, $this->joypad->readByte(0xFFFF));
    }

    #[Test]
    public function it_ignores_writes_to_non_joyp_addresses(): void
    {
        // These should not cause errors
        $this->joypad->writeByte(0xFF01, 0x00);
        $this->joypad->writeByte(0xFF0F, 0x00);
        $this->joypad->writeByte(0xFFFF, 0x00);

        // JOYP should still be readable normally
        $this->assertSame(0xFF, $this->joypad->readByte(0xFF00));
    }
}
