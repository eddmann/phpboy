<?php

declare(strict_types=1);

namespace Gb\Dma;

use Gb\Bus\BusInterface;
use Gb\Bus\DeviceInterface;

/**
 * HDMA (H-Blank DMA) Controller for Game Boy Color.
 *
 * Provides two DMA modes:
 * 1. General Purpose DMA: Immediate transfer of up to 2040 bytes
 * 2. H-Blank DMA: Transfers 16 bytes per H-Blank period
 *
 * Registers:
 * - HDMA1 (0xFF51): Source high byte
 * - HDMA2 (0xFF52): Source low byte (lower 4 bits ignored)
 * - HDMA3 (0xFF53): Destination high byte (only VRAM: 0x8000-0x9FFF)
 * - HDMA4 (0xFF54): Destination low byte (lower 4 bits ignored)
 * - HDMA5 (0xFF55): Length/Mode/Start
 *   - Bit 7: Mode (0=General Purpose, 1=H-Blank)
 *   - Bits 6-0: Length ((value + 1) * 16 bytes)
 *
 * During H-Blank DMA:
 * - Transfers 16 bytes per H-Blank
 * - Can be terminated by writing 0 to bit 7 of HDMA5
 *
 * Reference: Pan Docs - CGB Registers
 */
final class HdmaController implements DeviceInterface
{
    private const HDMA1_ADDRESS = 0xFF51; // Source high
    private const HDMA2_ADDRESS = 0xFF52; // Source low
    private const HDMA3_ADDRESS = 0xFF53; // Destination high
    private const HDMA4_ADDRESS = 0xFF54; // Destination low
    private const HDMA5_ADDRESS = 0xFF55; // Length/Mode/Start

    private int $sourceHigh = 0x00;
    private int $sourceLow = 0x00;
    private int $destHigh = 0x00;
    private int $destLow = 0x00;

    private bool $hdmaActive = false;
    private bool $hblankMode = false;
    private int $remainingBlocks = 0; // Number of 16-byte blocks remaining

    /**
     * @param BusInterface $bus Memory bus for DMA transfers
     */
    public function __construct(
        private readonly BusInterface $bus,
    ) {
    }

    /**
     * Read a byte from the HDMA registers.
     *
     * @param int $address Memory address (0xFF51-0xFF55)
     * @return int Byte value (0x00-0xFF)
     */
    public function readByte(int $address): int
    {
        return match ($address) {
            self::HDMA1_ADDRESS => $this->sourceHigh,
            self::HDMA2_ADDRESS => $this->sourceLow,
            self::HDMA3_ADDRESS => $this->destHigh,
            self::HDMA4_ADDRESS => $this->destLow,
            self::HDMA5_ADDRESS => $this->readHdma5(),
            default => 0xFF,
        };
    }

    /**
     * Write a byte to the HDMA registers.
     *
     * @param int $address Memory address (0xFF51-0xFF55)
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $address, int $value): void
    {
        match ($address) {
            self::HDMA1_ADDRESS => $this->sourceHigh = $value & 0xFF,
            self::HDMA2_ADDRESS => $this->sourceLow = $value & 0xF0, // Lower 4 bits ignored
            self::HDMA3_ADDRESS => $this->destHigh = $value & 0x1F,  // Only 0x80-0x9F valid
            self::HDMA4_ADDRESS => $this->destLow = $value & 0xF0,   // Lower 4 bits ignored
            self::HDMA5_ADDRESS => $this->writeHdma5($value),
            default => null,
        };
    }

    /**
     * Read HDMA5 register.
     *
     * Returns remaining blocks (bits 6-0) and inactive flag (bit 7).
     * Bit 7: 0=active, 1=inactive (inverted from write)
     *
     * @return int HDMA5 value
     */
    private function readHdma5(): int
    {
        if (!$this->hdmaActive) {
            return 0xFF; // Inactive
        }

        // Return remaining blocks (bit 7=0 during transfer)
        return $this->remainingBlocks & 0x7F;
    }

    /**
     * Write to HDMA5 register to start or stop a transfer.
     *
     * @param int $value Value written to HDMA5
     */
    private function writeHdma5(int $value): void
    {
        $mode = ($value & 0x80) !== 0; // Bit 7: 0=GP DMA, 1=H-Blank DMA
        $length = ($value & 0x7F) + 1; // Bits 6-0: (N+1) blocks of 16 bytes

        if ($this->hdmaActive && $this->hblankMode && !$mode) {
            // Writing 0 to bit 7 during H-Blank DMA terminates it
            $this->hdmaActive = false;
            return;
        }

        // Start new transfer
        $this->hblankMode = $mode;
        $this->remainingBlocks = $length;
        $this->hdmaActive = true;

        // If General Purpose DMA, transfer immediately
        if (!$this->hblankMode) {
            $this->performGeneralPurposeDma();
        }
    }

    /**
     * Perform a General Purpose DMA transfer (immediate, blocking).
     */
    private function performGeneralPurposeDma(): void
    {
        while ($this->remainingBlocks > 0) {
            $this->transferBlock();
        }
        $this->hdmaActive = false;
    }

    /**
     * Transfer one 16-byte block from source to destination.
     */
    private function transferBlock(): void
    {
        $source = ($this->sourceHigh << 8) | $this->sourceLow;
        $dest = 0x8000 | (($this->destHigh & 0x1F) << 8) | $this->destLow;

        for ($i = 0; $i < 16; $i++) {
            $value = $this->bus->readByte($source + $i);
            $this->bus->writeByte($dest + $i, $value);
        }

        // Update source and destination
        $this->sourceLow = ($this->sourceLow + 16) & 0xF0;
        if ($this->sourceLow === 0x00) {
            $this->sourceHigh = ($this->sourceHigh + 1) & 0xFF;
        }

        $this->destLow = ($this->destLow + 16) & 0xF0;
        if ($this->destLow === 0x00) {
            $this->destHigh = ($this->destHigh + 1) & 0x1F;
        }

        $this->remainingBlocks--;
    }

    /**
     * Check if HDMA is currently active.
     *
     * @return bool True if HDMA transfer is in progress
     */
    public function isHdmaActive(): bool
    {
        return $this->hdmaActive;
    }

    /**
     * Trigger H-Blank DMA transfer (called during H-Blank period).
     *
     * Transfers one 16-byte block if H-Blank DMA is active.
     */
    public function onHBlank(): void
    {
        if (!$this->hdmaActive || !$this->hblankMode) {
            return;
        }

        $this->transferBlock();

        if ($this->remainingBlocks === 0) {
            $this->hdmaActive = false;
        }
    }

    /**
     * Get HDMA active state (for savestate serialization).
     *
     * @return bool True if HDMA is active
     */
    public function getHdmaActive(): bool
    {
        return $this->hdmaActive;
    }

    /**
     * Get H-Blank mode state (for savestate serialization).
     *
     * @return bool True if in H-Blank mode
     */
    public function getHblankMode(): bool
    {
        return $this->hblankMode;
    }

    /**
     * Get remaining blocks (for savestate serialization).
     *
     * @return int Number of 16-byte blocks remaining
     */
    public function getRemainingBlocks(): int
    {
        return $this->remainingBlocks;
    }

    /**
     * Set HDMA active state (for savestate deserialization).
     *
     * @param bool $active True if HDMA is active
     */
    public function setHdmaActive(bool $active): void
    {
        $this->hdmaActive = $active;
    }

    /**
     * Set H-Blank mode state (for savestate deserialization).
     *
     * @param bool $mode True if in H-Blank mode
     */
    public function setHblankMode(bool $mode): void
    {
        $this->hblankMode = $mode;
    }

    /**
     * Set remaining blocks (for savestate deserialization).
     *
     * @param int $blocks Number of 16-byte blocks remaining
     */
    public function setRemainingBlocks(int $blocks): void
    {
        $this->remainingBlocks = $blocks;
    }
}
