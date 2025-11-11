<?php

declare(strict_types=1);

namespace Tests\Unit\System;

use Gb\Memory\Vram;
use Gb\Memory\Wram;
use Gb\System\CgbController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CgbControllerTest extends TestCase
{
    private CgbController $controller;
    private Vram $vram;
    private Wram $wram;

    protected function setUp(): void
    {
        $this->vram = new Vram();
        $this->wram = new Wram();
        $this->controller = new CgbController($this->vram, $this->wram);
    }

    #[Test]
    public function it_has_correct_key1_initial_value(): void
    {
        $value = $this->controller->readByte(0xFF4D);

        // Bit 7 should be 0 (normal speed), bit 0 should be 0 (no prepare)
        // Bits 1-6 should be 1
        $this->assertSame(0x7E, $value);
    }

    #[Test]
    public function it_prepares_speed_switch_with_key1(): void
    {
        $this->controller->writeByte(0xFF4D, 0x01);

        $value = $this->controller->readByte(0xFF4D);

        // Bit 0 should be set (prepare speed switch)
        $this->assertSame(0x7F, $value);
    }

    #[Test]
    public function it_toggles_speed_switch(): void
    {
        // Prepare speed switch
        $this->controller->writeByte(0xFF4D, 0x01);
        $this->assertTrue($this->controller->isSpeedSwitchPrepared());

        // Trigger switch
        $this->controller->triggerSpeedSwitch();

        // Should now be in double speed
        $this->assertTrue($this->controller->isDoubleSpeed());

        // Prepare bit should be cleared
        $this->assertFalse($this->controller->isSpeedSwitchPrepared());

        // Bit 7 should be set (double speed)
        $value = $this->controller->readByte(0xFF4D);
        $this->assertSame(0xFE, $value);
    }

    #[Test]
    public function it_switches_back_to_normal_speed(): void
    {
        // Switch to double speed
        $this->controller->writeByte(0xFF4D, 0x01);
        $this->controller->triggerSpeedSwitch();
        $this->assertTrue($this->controller->isDoubleSpeed());

        // Switch back to normal
        $this->controller->writeByte(0xFF4D, 0x01);
        $this->controller->triggerSpeedSwitch();
        $this->assertFalse($this->controller->isDoubleSpeed());

        $value = $this->controller->readByte(0xFF4D);
        $this->assertSame(0x7E, $value);
    }

    #[Test]
    public function it_does_nothing_on_speed_switch_without_prepare(): void
    {
        // Try to trigger without preparing
        $this->controller->triggerSpeedSwitch();

        // Should still be in normal speed
        $this->assertFalse($this->controller->isDoubleSpeed());
    }

    #[Test]
    public function it_reads_and_writes_vbk_register(): void
    {
        // VBK should control VRAM bank
        $this->controller->writeByte(0xFF4F, 0x01);

        // Should switch VRAM to bank 1
        $this->assertSame(1, $this->vram->getBank());

        // Read should return bank with upper bits set
        $value = $this->controller->readByte(0xFF4F);
        $this->assertSame(0xFF, $value);
    }

    #[Test]
    public function it_masks_vbk_register(): void
    {
        // Only bit 0 should be used for bank selection
        $this->controller->writeByte(0xFF4F, 0xFF);
        $this->assertSame(1, $this->vram->getBank());

        $this->controller->writeByte(0xFF4F, 0xFE);
        $this->assertSame(0, $this->vram->getBank());
    }

    #[Test]
    public function it_stubs_rp_register(): void
    {
        // RP register (infrared) should always return 0xFF
        $value = $this->controller->readByte(0xFF56);
        $this->assertSame(0xFF, $value);

        // Writes should be ignored
        $this->controller->writeByte(0xFF56, 0x00);
        $value = $this->controller->readByte(0xFF56);
        $this->assertSame(0xFF, $value);
    }
}
