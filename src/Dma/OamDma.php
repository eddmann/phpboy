<?php

declare(strict_types=1);

namespace Gb\Dma;

use Gb\Bus\BusInterface;
use Gb\Bus\DeviceInterface;

/**
 * OAM DMA (Direct Memory Access) Controller for the Game Boy.
 *
 * The OAM DMA register (0xFF46) triggers a 160-byte transfer from
 * source address XX00-XX9F to OAM (0xFE00-0xFE9F), where XX is the
 * value written to the DMA register.
 *
 * During DMA transfer (160 M-cycles):
 * - CPU is stalled and cannot access most memory
 * - Only HRAM (0xFF80-0xFFFE) is accessible
 * - Used to quickly copy sprite data to OAM
 *
 * Example: Writing 0xC1 to 0xFF46 copies 0xC100-0xC19F to 0xFE00-0xFE9F
 *
 * Reference: Pan Docs - OAM DMA Transfer
 */
final class OamDma implements DeviceInterface
{
    private const DMA_ADDRESS = 0xFF46;
    private const OAM_START = 0xFE00;
    private const TRANSFER_LENGTH = 160; // 160 bytes (40 sprites × 4 bytes each)

    /**
     * DMA register value (source address high byte).
     */
    private int $dmaRegister = 0x00;

    /**
     * Whether a DMA transfer is currently in progress.
     */
    private bool $dmaActive = false;

    /**
     * Current byte being transferred (0-159).
     */
    private int $dmaProgress = 0;

    /**
     * Source address for the current DMA transfer.
     */
    private int $dmaSource = 0x0000;

    /**
     * Startup delay in M-cycles before first byte transfer.
     * DMA has a 1 M-cycle delay after trigger before transferring bytes.
     */
    private int $dmaDelay = 0;

    /**
     * @param BusInterface $bus Memory bus for reading source and writing to OAM
     */
    public function __construct(
        private readonly BusInterface $bus,
    ) {
    }

    /**
     * Read a byte from the DMA register.
     *
     * @param int $address Memory address (0xFF46)
     * @return int Byte value (0x00-0xFF)
     */
    public function readByte(int $address): int
    {
        return match ($address) {
            self::DMA_ADDRESS => $this->dmaRegister,
            default => 0xFF,
        };
    }

    /**
     * Write a byte to the DMA register.
     *
     * Writing to 0xFF46 starts a DMA transfer from (value << 8) to OAM.
     *
     * @param int $address Memory address (0xFF46)
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $address, int $value): void
    {
        if ($address === self::DMA_ADDRESS) {
            $this->dmaRegister = $value & 0xFF;
            $this->startDmaTransfer($value);
        }
    }

    /**
     * Start a DMA transfer from the specified source page.
     *
     * @param int $sourcePage Source address high byte (0x00-0xFF)
     */
    private function startDmaTransfer(int $sourcePage): void
    {
        $this->dmaSource = ($sourcePage << 8) & 0xFF00;
        $this->dmaActive = true;
        $this->dmaProgress = 0;
        $this->dmaDelay = 1; // 1 M-cycle delay before first byte transfer
    }

    /**
     * Check if a DMA transfer is currently active.
     *
     * @return bool True if DMA is in progress
     */
    public function isDmaActive(): bool
    {
        return $this->dmaActive;
    }

    /**
     * Update the DMA transfer by the specified number of CPU cycles.
     *
     * Each M-cycle (4 T-cycles) transfers one byte. DMA completes after 160 M-cycles.
     *
     * @param int $cycles Number of T-cycles (CPU cycles) elapsed
     */
    public function tick(int $cycles): void
    {
        if (!$this->dmaActive) {
            return;
        }

        // Convert T-cycles to M-cycles (1 M-cycle = 4 T-cycles)
        $mCycles = intdiv($cycles, 4);

        // Handle startup delay (1 M-cycle before first byte transfer)
        if ($this->dmaDelay > 0) {
            $delayToProcess = min($this->dmaDelay, $mCycles);
            $this->dmaDelay -= $delayToProcess;
            $mCycles -= $delayToProcess;

            if ($mCycles <= 0) {
                return; // Still in delay phase
            }
        }

        // Transfer one byte per M-cycle
        for ($i = 0; $i < $mCycles; $i++) {
            if ($this->dmaProgress >= self::TRANSFER_LENGTH) {
                $this->dmaActive = false;
                break;
            }

            // Read from source and write to OAM
            $sourceAddress = $this->dmaSource + $this->dmaProgress;
            $destAddress = self::OAM_START + $this->dmaProgress;
            $value = $this->bus->readByte($sourceAddress);
            $this->bus->writeByte($destAddress, $value);

            $this->dmaProgress++;

            // Check if transfer is complete after incrementing
            if ($this->dmaProgress >= self::TRANSFER_LENGTH) {
                $this->dmaActive = false;
                break;
            }
        }
    }

    /**
     * Get DMA active state (for savestate serialization).
     *
     * @return bool True if DMA is active
     */
    public function getDmaActive(): bool
    {
        return $this->dmaActive;
    }

    /**
     * Get DMA progress (for savestate serialization).
     *
     * @return int Current byte being transferred (0-159)
     */
    public function getDmaProgress(): int
    {
        return $this->dmaProgress;
    }

    /**
     * Get DMA delay (for savestate serialization).
     *
     * @return int Startup delay in M-cycles
     */
    public function getDmaDelay(): int
    {
        return $this->dmaDelay;
    }

    /**
     * Get DMA source address (for savestate serialization).
     *
     * @return int Source address for DMA transfer
     */
    public function getDmaSource(): int
    {
        return $this->dmaSource;
    }

    /**
     * Set DMA active state (for savestate deserialization).
     *
     * @param bool $active True if DMA is active
     */
    public function setDmaActive(bool $active): void
    {
        $this->dmaActive = $active;
    }

    /**
     * Set DMA progress (for savestate deserialization).
     *
     * @param int $progress Current byte being transferred (0-159)
     */
    public function setDmaProgress(int $progress): void
    {
        $this->dmaProgress = $progress;
    }

    /**
     * Set DMA delay (for savestate deserialization).
     *
     * @param int $delay Startup delay in M-cycles
     */
    public function setDmaDelay(int $delay): void
    {
        $this->dmaDelay = $delay;
    }

    /**
     * Set DMA source address (for savestate deserialization).
     *
     * @param int $source Source address for DMA transfer
     */
    public function setDmaSource(int $source): void
    {
        $this->dmaSource = $source;
    }
}
