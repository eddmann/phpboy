<?php

declare(strict_types=1);

namespace Tests\Unit\Rewind;

use Gb\Emulator;
use Gb\Rewind\RewindBuffer;
use PHPUnit\Framework\TestCase;

/**
 * RewindBuffer Unit Tests
 *
 * Tests rewind buffer functionality.
 */
final class RewindBufferTest extends TestCase
{
    public function testBufferRecording(): void
    {
        $emulator = new Emulator();
        $emulator->loadRom(__DIR__ . '/../../../third_party/roms/cpu_instrs/01-special.gb');

        $buffer = new RewindBuffer($emulator, maxSeconds: 5, framesPerSavestate: 60);

        $this->assertFalse($buffer->isAvailable());
        $this->assertEquals(0, $buffer->getAvailableSeconds());

        // Record for 60 frames (1 second)
        for ($i = 0; $i < 60; $i++) {
            $emulator->step();
            $buffer->recordFrame();
        }

        // Should have 1 second of history
        $this->assertTrue($buffer->isAvailable());
        $this->assertEquals(1, $buffer->getAvailableSeconds());

        // Record for another 60 frames
        for ($i = 0; $i < 60; $i++) {
            $emulator->step();
            $buffer->recordFrame();
        }

        // Should have 2 seconds of history
        $this->assertEquals(2, $buffer->getAvailableSeconds());
    }

    public function testRewind(): void
    {
        $emulator = new Emulator();
        $emulator->loadRom(__DIR__ . '/../../../third_party/roms/cpu_instrs/01-special.gb');

        $buffer = new RewindBuffer($emulator, maxSeconds: 5, framesPerSavestate: 60);

        // Record for 120 frames (2 seconds)
        for ($i = 0; $i < 120; $i++) {
            $emulator->step();
            $buffer->recordFrame();
        }

        // Get PC after 2 seconds
        $cpu = $emulator->getCpu();
        if ($cpu === null) {
            $this->fail('CPU is null');
        }
        $pc120 = $cpu->getPC()->get();

        // Run for another 60 frames
        for ($i = 0; $i < 60; $i++) {
            $emulator->step();
            $buffer->recordFrame();
        }

        // PC should have changed
        $pc180 = $cpu->getPC()->get();
        $this->assertNotEquals($pc120, $pc180);

        // Rewind 1 second
        $buffer->rewind(1);

        // PC should be back to where it was at 120 frames
        $pcAfterRewind = $cpu->getPC()->get();
        $this->assertEquals($pc120, $pcAfterRewind);
    }

    public function testInsufficientHistory(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient rewind history');

        $emulator = new Emulator();
        $emulator->loadRom(__DIR__ . '/../../../third_party/roms/cpu_instrs/01-special.gb');

        $buffer = new RewindBuffer($emulator, maxSeconds: 5);

        // Try to rewind with no history
        $buffer->rewind(1);
    }

    public function testClearBuffer(): void
    {
        $emulator = new Emulator();
        $emulator->loadRom(__DIR__ . '/../../../third_party/roms/cpu_instrs/01-special.gb');

        $buffer = new RewindBuffer($emulator, maxSeconds: 5, framesPerSavestate: 60);

        // Record some history
        for ($i = 0; $i < 60; $i++) {
            $emulator->step();
            $buffer->recordFrame();
        }

        $this->assertTrue($buffer->isAvailable());

        // Clear buffer
        $buffer->clear();

        $this->assertFalse($buffer->isAvailable());
        $this->assertEquals(0, $buffer->getAvailableSeconds());
    }
}
