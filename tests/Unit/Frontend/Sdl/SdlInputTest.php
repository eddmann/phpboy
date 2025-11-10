<?php

declare(strict_types=1);

namespace Tests\Unit\Frontend\Sdl;

use Gb\Frontend\Sdl\SdlInput;
use Gb\Input\Button;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SdlInputTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('sdl')) {
            $this->markTestSkipped('SDL extension not loaded');
        }
    }

    #[Test]
    public function it_initializes_with_default_key_mappings(): void
    {
        $input = new SdlInput();
        $mappings = $input->getKeyMappings();

        $this->assertNotEmpty($mappings);

        // Check key mappings exist for arrows
        $this->assertArrayHasKey(SDL_SCANCODE_UP, $mappings);
        $this->assertArrayHasKey(SDL_SCANCODE_DOWN, $mappings);
        $this->assertArrayHasKey(SDL_SCANCODE_LEFT, $mappings);
        $this->assertArrayHasKey(SDL_SCANCODE_RIGHT, $mappings);

        // Check action buttons
        $this->assertArrayHasKey(SDL_SCANCODE_Z, $mappings);
        $this->assertArrayHasKey(SDL_SCANCODE_X, $mappings);
    }

    #[Test]
    public function it_maps_arrow_keys_to_dpad(): void
    {
        $input = new SdlInput();
        $mappings = $input->getKeyMappings();

        $this->assertSame(Button::Up, $mappings[SDL_SCANCODE_UP]);
        $this->assertSame(Button::Down, $mappings[SDL_SCANCODE_DOWN]);
        $this->assertSame(Button::Left, $mappings[SDL_SCANCODE_LEFT]);
        $this->assertSame(Button::Right, $mappings[SDL_SCANCODE_RIGHT]);
    }

    #[Test]
    public function it_maps_action_buttons(): void
    {
        $input = new SdlInput();
        $mappings = $input->getKeyMappings();

        $this->assertSame(Button::A, $mappings[SDL_SCANCODE_Z]);
        $this->assertSame(Button::B, $mappings[SDL_SCANCODE_X]);
        $this->assertSame(Button::Start, $mappings[SDL_SCANCODE_RETURN]);
        $this->assertSame(Button::Select, $mappings[SDL_SCANCODE_RSHIFT]);
    }

    #[Test]
    public function it_allows_multiple_keys_for_same_button(): void
    {
        $input = new SdlInput();
        $mappings = $input->getKeyMappings();

        // Both Z and A map to A button
        $this->assertSame(Button::A, $mappings[SDL_SCANCODE_Z]);
        $this->assertSame(Button::A, $mappings[SDL_SCANCODE_A]);

        // Both X and S map to B button
        $this->assertSame(Button::B, $mappings[SDL_SCANCODE_X]);
        $this->assertSame(Button::B, $mappings[SDL_SCANCODE_S]);

        // Both RShift and Space map to Select
        $this->assertSame(Button::Select, $mappings[SDL_SCANCODE_RSHIFT]);
        $this->assertSame(Button::Select, $mappings[SDL_SCANCODE_SPACE]);
    }

    #[Test]
    public function it_allows_custom_key_mapping(): void
    {
        $input = new SdlInput();

        // Set custom mapping
        $input->setKeyMapping(SDL_SCANCODE_Q, Button::A);

        $mappings = $input->getKeyMappings();
        $this->assertSame(Button::A, $mappings[SDL_SCANCODE_Q]);
    }

    #[Test]
    public function it_can_clear_all_mappings(): void
    {
        $input = new SdlInput();

        $input->clearKeyMappings();
        $mappings = $input->getKeyMappings();

        $this->assertEmpty($mappings);
    }

    #[Test]
    public function it_can_reset_and_customize_mappings(): void
    {
        $input = new SdlInput();

        // Clear and set new mappings
        $input->clearKeyMappings();
        $input->setKeyMapping(SDL_SCANCODE_W, Button::Up);
        $input->setKeyMapping(SDL_SCANCODE_S, Button::Down);

        $mappings = $input->getKeyMappings();

        $this->assertCount(2, $mappings);
        $this->assertSame(Button::Up, $mappings[SDL_SCANCODE_W]);
        $this->assertSame(Button::Down, $mappings[SDL_SCANCODE_S]);
    }

    #[Test]
    public function it_generates_key_mapping_description(): void
    {
        $input = new SdlInput();
        $description = $input->getKeyMappingDescription();

        $this->assertStringContainsString('Keyboard Controls:', $description);
        $this->assertStringContainsString('Up', $description);
        $this->assertStringContainsString('Down', $description);
        $this->assertStringContainsString('Left', $description);
        $this->assertStringContainsString('Right', $description);
    }

    #[Test]
    public function it_handles_event_based_key_tracking(): void
    {
        $input = new SdlInput();

        // Create mock keydown event
        $event = new \SDL_Event();
        $event->type = SDL_KEYDOWN;
        $event->key = new \stdClass();
        $event->key->keysym = new \stdClass();
        $event->key->keysym->scancode = SDL_SCANCODE_Z;

        $input->handleKeyEvent($event);

        // Check button is now tracked as pressed
        $pressed = $input->getPressedButtons();
        $this->assertContains(Button::A, $pressed);
    }

    #[Test]
    public function it_handles_event_based_key_release(): void
    {
        $input = new SdlInput();

        // Press key
        $eventDown = new \SDL_Event();
        $eventDown->type = SDL_KEYDOWN;
        $eventDown->key = new \stdClass();
        $eventDown->key->keysym = new \stdClass();
        $eventDown->key->keysym->scancode = SDL_SCANCODE_Z;
        $input->handleKeyEvent($eventDown);

        // Release key
        $eventUp = new \SDL_Event();
        $eventUp->type = SDL_KEYUP;
        $eventUp->key = new \stdClass();
        $eventUp->key->keysym = new \stdClass();
        $eventUp->key->keysym->scancode = SDL_SCANCODE_Z;
        $input->handleKeyEvent($eventUp);

        // Button should no longer be pressed
        $pressed = $input->getPressedButtons();
        $this->assertEmpty($pressed);
    }

    #[Test]
    public function it_removes_duplicate_buttons_in_event_mode(): void
    {
        $input = new SdlInput();

        // Press both Z and A (both map to Button::A)
        $eventZ = new \SDL_Event();
        $eventZ->type = SDL_KEYDOWN;
        $eventZ->key = new \stdClass();
        $eventZ->key->keysym = new \stdClass();
        $eventZ->key->keysym->scancode = SDL_SCANCODE_Z;
        $input->handleKeyEvent($eventZ);

        $eventA = new \SDL_Event();
        $eventA->type = SDL_KEYDOWN;
        $eventA->key = new \stdClass();
        $eventA->key->keysym = new \stdClass();
        $eventA->key->keysym->scancode = SDL_SCANCODE_A;
        $input->handleKeyEvent($eventA);

        // Should only have Button::A once
        $pressed = $input->getPressedButtons();
        $this->assertCount(1, $pressed);
        $this->assertContains(Button::A, $pressed);
    }

    #[Test]
    public function it_ignores_unmapped_keys_in_events(): void
    {
        $input = new SdlInput();

        // Press unmapped key
        $event = new \SDL_Event();
        $event->type = SDL_KEYDOWN;
        $event->key = new \stdClass();
        $event->key->keysym = new \stdClass();
        $event->key->keysym->scancode = SDL_SCANCODE_F1; // Not mapped by default
        $input->handleKeyEvent($event);

        $pressed = $input->getPressedButtons();
        $this->assertEmpty($pressed);
    }

    #[Test]
    public function it_handles_events_without_key_data_gracefully(): void
    {
        $input = new SdlInput();

        // Event without key property
        $event = new \SDL_Event();
        $event->type = SDL_KEYDOWN;

        // Should not throw exception
        $input->handleKeyEvent($event);

        $pressed = $input->getPressedButtons();
        $this->assertEmpty($pressed);
    }
}
