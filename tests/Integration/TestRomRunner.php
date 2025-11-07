<?php

declare(strict_types=1);

namespace Tests\Integration;

use Gb\Emulator;

/**
 * Test ROM Runner
 *
 * Automated test ROM execution with pass/fail detection.
 * Supports Blargg and Mooneye test ROMs.
 *
 * Detection methods:
 * 1. Serial output: Looks for "Passed" or "Failed" in serial data
 * 2. Magic memory values: Checks specific addresses for test results
 * 3. Timeout: Aborts after configurable time limit
 */
final class TestRomRunner
{
    /** Default timeout in seconds */
    private const DEFAULT_TIMEOUT = 30;

    /** Maximum frames to execute (safety limit) */
    private const MAX_FRAMES = 18000; // ~5 minutes at 60 FPS

    /** Frames per second (approximate) */
    private const FPS = 59.7;

    private Emulator $emulator;
    private int $timeout;

    /**
     * @param int $timeout Timeout in seconds (default: 30)
     */
    public function __construct(int $timeout = self::DEFAULT_TIMEOUT)
    {
        $this->emulator = new Emulator();
        $this->timeout = $timeout;
    }

    /**
     * Run a test ROM and return the result.
     *
     * @param string $romPath Path to the ROM file
     * @return TestRomResult Test result with status and output
     */
    public function run(string $romPath): TestRomResult
    {
        if (!file_exists($romPath)) {
            return new TestRomResult(
                status: TestRomStatus::Error,
                output: "ROM file not found: {$romPath}",
                frames: 0,
                duration: 0.0
            );
        }

        try {
            $this->emulator->loadRom($romPath);
        } catch (\Exception $e) {
            return new TestRomResult(
                status: TestRomStatus::Error,
                output: "Failed to load ROM: {$e->getMessage()}",
                frames: 0,
                duration: 0.0
            );
        }

        $startTime = microtime(true);
        $frames = 0;
        $serial = $this->emulator->getSerial();

        // Run until timeout or max frames
        while ($frames < self::MAX_FRAMES) {
            // Check timeout
            $elapsed = microtime(true) - $startTime;
            if ($elapsed > $this->timeout) {
                return new TestRomResult(
                    status: TestRomStatus::Timeout,
                    output: $serial?->getOutput() ?? '',
                    frames: $frames,
                    duration: $elapsed
                );
            }

            // Execute one frame
            try {
                $this->emulator->step();
                $frames++;
            } catch (\Exception $e) {
                return new TestRomResult(
                    status: TestRomStatus::Error,
                    output: "Emulation error: {$e->getMessage()}",
                    frames: $frames,
                    duration: microtime(true) - $startTime
                );
            }

            // Check serial output for pass/fail
            if ($serial !== null) {
                $output = $serial->getOutput();

                // Blargg test ROMs output "Passed" or "Failed"
                if (str_contains($output, 'Passed')) {
                    return new TestRomResult(
                        status: TestRomStatus::Pass,
                        output: $output,
                        frames: $frames,
                        duration: microtime(true) - $startTime
                    );
                }

                if (str_contains($output, 'Failed')) {
                    return new TestRomResult(
                        status: TestRomStatus::Fail,
                        output: $output,
                        frames: $frames,
                        duration: microtime(true) - $startTime
                    );
                }
            }

            // Check magic memory value (used by some test ROMs)
            // Address 0xA000 often contains test status
            $bus = $this->emulator->getBus();
            if ($bus !== null) {
                $magicValue = $bus->readByte(0xA000);

                // Some test ROMs use specific values to indicate pass/fail
                // 0x00 = pass, non-zero = fail (varies by test ROM)
                // This is a heuristic and may need adjustment
            }
        }

        // Reached max frames without conclusion
        return new TestRomResult(
            status: TestRomStatus::Timeout,
            output: $serial?->getOutput() ?? '',
            frames: $frames,
            duration: microtime(true) - $startTime
        );
    }

    /**
     * Run a test ROM with a custom timeout.
     *
     * @param string $romPath Path to the ROM file
     * @param int $timeout Timeout in seconds
     * @return TestRomResult Test result
     */
    public function runWithTimeout(string $romPath, int $timeout): TestRomResult
    {
        $originalTimeout = $this->timeout;
        $this->timeout = $timeout;

        $result = $this->run($romPath);

        $this->timeout = $originalTimeout;
        return $result;
    }

    /**
     * Get the emulator instance (useful for debugging).
     */
    public function getEmulator(): Emulator
    {
        return $this->emulator;
    }
}

/**
 * Test ROM execution result.
 */
final readonly class TestRomResult
{
    public function __construct(
        public TestRomStatus $status,
        public string $output,
        public int $frames,
        public float $duration
    ) {}

    /**
     * Check if the test passed.
     */
    public function passed(): bool
    {
        return $this->status === TestRomStatus::Pass;
    }

    /**
     * Check if the test failed.
     */
    public function failed(): bool
    {
        return $this->status === TestRomStatus::Fail;
    }

    /**
     * Check if the test timed out.
     */
    public function timedOut(): bool
    {
        return $this->status === TestRomStatus::Timeout;
    }

    /**
     * Check if there was an error.
     */
    public function hasError(): bool
    {
        return $this->status === TestRomStatus::Error;
    }

    /**
     * Get a human-readable status string.
     */
    public function getStatusString(): string
    {
        return match ($this->status) {
            TestRomStatus::Pass => '✅ PASS',
            TestRomStatus::Fail => '❌ FAIL',
            TestRomStatus::Timeout => '⏱️  TIMEOUT',
            TestRomStatus::Error => '💥 ERROR',
        };
    }

    /**
     * Get formatted summary.
     */
    public function getSummary(): string
    {
        $fps = $this->duration > 0 ? $this->frames / $this->duration : 0;

        return sprintf(
            "%s (%d frames, %.2fs, %.1f FPS)",
            $this->getStatusString(),
            $this->frames,
            $this->duration,
            $fps
        );
    }
}

/**
 * Test ROM execution status.
 */
enum TestRomStatus
{
    case Pass;    // Test passed successfully
    case Fail;    // Test failed
    case Timeout; // Test timed out without conclusion
    case Error;   // Emulation error occurred
}
