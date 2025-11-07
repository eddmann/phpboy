<?php

declare(strict_types=1);

namespace Gb\Serial;

use Gb\Bus\DeviceInterface;
use Gb\Interrupts\InterruptController;
use Gb\Interrupts\InterruptType;

/**
 * Serial Data Transfer (Link Cable)
 *
 * Handles serial communication used by test ROMs for output.
 * Registers:
 * - 0xFF01 (SB): Serial transfer data
 * - 0xFF02 (SC): Serial transfer control
 *
 * Test ROMs typically write characters to SB and trigger transfer via SC.
 */
final class Serial implements DeviceInterface
{
    private const SB = 0xFF01; // Serial transfer data
    private const SC = 0xFF02; // Serial transfer control

    private int $sb = 0x00; // Serial data
    private int $sc = 0x00; // Serial control

    /** @var array<int> Captured serial output bytes */
    private array $output = [];

    public function __construct(
        private readonly InterruptController $interruptController
    ) {}

    public function readByte(int $address): int
    {
        return match ($address) {
            self::SB => $this->sb,
            self::SC => $this->sc | 0x7E, // Unused bits read as 1
            default => 0xFF,
        };
    }

    public function writeByte(int $address, int $value): void
    {
        match ($address) {
            self::SB => $this->sb = $value & 0xFF,
            self::SC => $this->handleControlWrite($value),
            default => null,
        };
    }

    /**
     * Handle write to serial control register.
     * When bit 7 is set, transfer is triggered.
     */
    private function handleControlWrite(int $value): void
    {
        $this->sc = $value & 0xFF;

        // Check if transfer is requested (bit 7)
        if (($value & 0x80) !== 0) {
            // Capture the byte being transferred
            $this->output[] = $this->sb;

            // Clear transfer bit and request serial interrupt
            $this->sc &= 0x7F;
            $this->interruptController->requestInterrupt(InterruptType::Serial);
        }
    }

    /**
     * Get the captured serial output as a string.
     */
    public function getOutput(): string
    {
        return implode('', array_map('chr', $this->output));
    }

    /**
     * Get the captured serial output as raw bytes.
     *
     * @return array<int>
     */
    public function getOutputBytes(): array
    {
        return $this->output;
    }

    /**
     * Clear the captured output.
     */
    public function clearOutput(): void
    {
        $this->output = [];
    }

    /**
     * Check if output contains a specific string.
     */
    public function hasOutput(string $needle): bool
    {
        return str_contains($this->getOutput(), $needle);
    }
}
