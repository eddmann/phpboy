<?php

declare(strict_types=1);

namespace Tests\Unit\Interrupts;

use Gb\Interrupts\InterruptController;
use Gb\Interrupts\InterruptType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InterruptControllerTest extends TestCase
{
    #[Test]
    public function it_initializes_with_correct_defaults(): void
    {
        $controller = new InterruptController();

        // IF should read 0xE0 (upper 3 bits always 1)
        $this->assertSame(0xE0, $controller->readByte(0xFF0F));
        // IE should read 0x00
        $this->assertSame(0x00, $controller->readByte(0xFFFF));
    }

    #[Test]
    public function it_reads_and_writes_if_register(): void
    {
        $controller = new InterruptController();

        // Write 0x1F (all 5 interrupts)
        $controller->writeByte(0xFF0F, 0x1F);
        // Upper 3 bits should always read as 1
        $this->assertSame(0xFF, $controller->readByte(0xFF0F));

        // Write 0x01 (VBlank only)
        $controller->writeByte(0xFF0F, 0x01);
        $this->assertSame(0xE1, $controller->readByte(0xFF0F));
    }

    #[Test]
    public function it_reads_and_writes_ie_register(): void
    {
        $controller = new InterruptController();

        // Write 0x1F (enable all interrupts)
        $controller->writeByte(0xFFFF, 0x1F);
        $this->assertSame(0x1F, $controller->readByte(0xFFFF));

        // Write 0x05 (enable VBlank and Timer)
        $controller->writeByte(0xFFFF, 0x05);
        $this->assertSame(0x05, $controller->readByte(0xFFFF));
    }

    #[Test]
    public function it_masks_ie_to_5_bits(): void
    {
        $controller = new InterruptController();

        // Write 0xFF, should mask to 0x1F
        $controller->writeByte(0xFFFF, 0xFF);
        $this->assertSame(0x1F, $controller->readByte(0xFFFF));
    }

    #[Test]
    public function it_requests_interrupts(): void
    {
        $controller = new InterruptController();

        $controller->requestInterrupt(InterruptType::VBlank);
        $this->assertSame(0xE1, $controller->readByte(0xFF0F)); // Bit 0 set

        $controller->requestInterrupt(InterruptType::Timer);
        $this->assertSame(0xE5, $controller->readByte(0xFF0F)); // Bits 0 and 2 set
    }

    #[Test]
    public function it_returns_no_pending_interrupt_when_none_requested(): void
    {
        $controller = new InterruptController();

        $this->assertNull($controller->getPendingInterrupt());
    }

    #[Test]
    public function it_returns_no_pending_interrupt_when_not_enabled(): void
    {
        $controller = new InterruptController();

        // Request VBlank but don't enable it
        $controller->requestInterrupt(InterruptType::VBlank);
        $this->assertNull($controller->getPendingInterrupt());
    }

    #[Test]
    public function it_returns_pending_interrupt_when_requested_and_enabled(): void
    {
        $controller = new InterruptController();

        // Enable and request VBlank
        $controller->writeByte(0xFFFF, 0x01); // Enable VBlank
        $controller->requestInterrupt(InterruptType::VBlank);

        $interrupt = $controller->getPendingInterrupt();
        $this->assertNotNull($interrupt);
        $this->assertSame(InterruptType::VBlank, $interrupt);
    }

    #[Test]
    public function it_respects_interrupt_priority(): void
    {
        $controller = new InterruptController();

        // Enable all interrupts
        $controller->writeByte(0xFFFF, 0x1F);

        // Request Timer (bit 2) and VBlank (bit 0)
        $controller->requestInterrupt(InterruptType::Timer);
        $controller->requestInterrupt(InterruptType::VBlank);

        // VBlank should have priority (bit 0 > bit 2)
        $interrupt = $controller->getPendingInterrupt();
        $this->assertSame(InterruptType::VBlank, $interrupt);
    }

    #[Test]
    public function it_returns_highest_priority_among_multiple_pending(): void
    {
        $controller = new InterruptController();

        // Enable all interrupts
        $controller->writeByte(0xFFFF, 0x1F);

        // Request multiple interrupts in reverse priority order
        $controller->requestInterrupt(InterruptType::Joypad);  // Priority 5
        $controller->requestInterrupt(InterruptType::Serial);  // Priority 4
        $controller->requestInterrupt(InterruptType::Timer);   // Priority 3
        $controller->requestInterrupt(InterruptType::LcdStat); // Priority 2
        $controller->requestInterrupt(InterruptType::VBlank);  // Priority 1 (highest)

        // Should return VBlank (highest priority)
        $this->assertSame(InterruptType::VBlank, $controller->getPendingInterrupt());
    }

    #[Test]
    public function it_acknowledges_interrupts(): void
    {
        $controller = new InterruptController();

        // Request VBlank and Timer
        $controller->requestInterrupt(InterruptType::VBlank);
        $controller->requestInterrupt(InterruptType::Timer);
        $this->assertSame(0xE5, $controller->readByte(0xFF0F)); // Bits 0 and 2 set

        // Acknowledge VBlank
        $controller->acknowledgeInterrupt(InterruptType::VBlank);
        $this->assertSame(0xE4, $controller->readByte(0xFF0F)); // Only bit 2 set

        // Acknowledge Timer
        $controller->acknowledgeInterrupt(InterruptType::Timer);
        $this->assertSame(0xE0, $controller->readByte(0xFF0F)); // No interrupts
    }

    #[Test]
    public function it_checks_for_any_pending_interrupt(): void
    {
        $controller = new InterruptController();

        // No interrupts requested
        $this->assertFalse($controller->hasAnyPendingInterrupt());

        // Request an interrupt (doesn't need to be enabled)
        $controller->requestInterrupt(InterruptType::Timer);
        $this->assertTrue($controller->hasAnyPendingInterrupt());
    }

    #[Test]
    public function it_masks_interrupts_via_ie_register(): void
    {
        $controller = new InterruptController();

        // Enable only VBlank
        $controller->writeByte(0xFFFF, 0x01);

        // Request Timer (not enabled)
        $controller->requestInterrupt(InterruptType::Timer);
        $this->assertNull($controller->getPendingInterrupt());

        // Request VBlank (enabled)
        $controller->requestInterrupt(InterruptType::VBlank);
        $this->assertSame(InterruptType::VBlank, $controller->getPendingInterrupt());
    }
}
