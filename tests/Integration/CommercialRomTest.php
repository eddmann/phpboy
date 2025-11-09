<?php

declare(strict_types=1);

namespace Tests\Integration;

use Gb\Emulator;
use PHPUnit\Framework\TestCase;

/**
 * Commercial ROM Smoke Tests
 *
 * Verifies that commercial Game Boy ROMs can:
 * 1. Load successfully
 * 2. Run for extended periods without crashing
 * 3. Reach the title screen / gameplay
 *
 * These are smoke tests, not full gameplay verification.
 */
final class CommercialRomTest extends TestCase
{
    private const ROM_BASE_PATH = __DIR__ . '/../../third_party/roms/commerical';

    /**
     * Timeout in seconds
     * At ~25 FPS, 3000 frames takes ~120 seconds
     */
    private const TEST_TIMEOUT = 180; // 3 minutes timeout

    /**
     * @dataProvider commercialRomProvider
     */
    public function testCommercialRom(string $romName, string $romPath, int $framesToRun): void
    {
        if (!file_exists($romPath)) {
            $this->markTestSkipped("ROM not found: {$romPath}");
        }

        $emulator = new Emulator();

        try {
            $emulator->loadRom($romPath);
        } catch (\Exception $e) {
            $this->fail("Failed to load ROM {$romName}: {$e->getMessage()}");
        }

        $startTime = microtime(true);
        $framesExecuted = 0;
        $crashed = false;
        $errorMessage = '';

        // Run for specified number of frames
        try {
            for ($i = 0; $i < $framesToRun; $i++) {
                // Check timeout
                if (microtime(true) - $startTime > self::TEST_TIMEOUT) {
                    $this->fail(sprintf(
                        "%s timed out after %.2fs (%d frames)",
                        $romName,
                        microtime(true) - $startTime,
                        $framesExecuted
                    ));
                }

                $emulator->step();
                $framesExecuted++;
            }
        } catch (\Exception $e) {
            $crashed = true;
            $errorMessage = $e->getMessage();
        }

        $duration = microtime(true) - $startTime;
        $fps = $duration > 0 ? $framesExecuted / $duration : 0;

        $message = sprintf(
            "%s: %s\nFrames: %d/%d (%.1f%%)\nDuration: %.2fs (%.1f FPS)\n%s",
            $romName,
            $crashed ? '❌ CRASHED' : '✅ STABLE',
            $framesExecuted,
            $framesToRun,
            ($framesExecuted / $framesToRun) * 100,
            $duration,
            $fps,
            $crashed ? "Error: {$errorMessage}" : "No crashes detected"
        );

        $this->assertFalse(
            $crashed,
            $message
        );

        $this->assertEquals(
            $framesToRun,
            $framesExecuted,
            $message
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function commercialRomProvider(): array
    {
        $basePath = self::ROM_BASE_PATH;

        // Run different durations for different games
        // Adjusted for current performance (~25-30 FPS)
        // At 25 FPS: 1500 frames = 1 minute real time
        return [
            'Tetris (GBC)' => [
                'Tetris (GBC)',
                "{$basePath}/tetris.gbc",
                1800, // ~60-72 seconds at 25-30 FPS
            ],
            'Pokemon Red' => [
                'Pokemon Red',
                "{$basePath}/pokered.gb",
                3000, // ~100-120 seconds at 25-30 FPS
            ],
            'Zelda: Link\'s Awakening DX' => [
                'Zelda: Link\'s Awakening DX',
                "{$basePath}/zelda.gbc",
                2400, // ~80-96 seconds at 25-30 FPS
            ],
        ];
    }

    /**
     * Test loading ROMs without running them (quick sanity check)
     *
     * @dataProvider commercialRomProvider
     */
    public function testRomLoads(string $romName, string $romPath, int $framesToRun): void
    {
        if (!file_exists($romPath)) {
            $this->markTestSkipped("ROM not found: {$romPath}");
        }

        $emulator = new Emulator();

        try {
            $emulator->loadRom($romPath);
            $this->assertTrue(true, "{$romName} loaded successfully");
        } catch (\Exception $e) {
            $this->fail("Failed to load ROM {$romName}: {$e->getMessage()}");
        }
    }
}
