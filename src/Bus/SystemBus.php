<?php

declare(strict_types=1);

namespace Gb\Bus;

/**
 * System Bus
 *
 * Routes memory read/write operations to the appropriate devices.
 * Implements the complete Game Boy memory map with echo RAM, open bus, and special regions.
 *
 * Memory Map:
 * - 0x0000-0x3FFF: ROM Bank 0 (cartridge)
 * - 0x4000-0x7FFF: ROM Bank N (cartridge, switchable)
 * - 0x8000-0x9FFF: VRAM (8KB)
 * - 0xA000-0xBFFF: External RAM (cartridge)
 * - 0xC000-0xCFFF: WRAM Bank 0 (4KB)
 * - 0xD000-0xDFFF: WRAM Bank 1 (4KB, switchable in CGB mode)
 * - 0xE000-0xFDFF: Echo RAM (mirrors 0xC000-0xDDFF)
 * - 0xFE00-0xFE9F: OAM (Sprite Attribute Table)
 * - 0xFEA0-0xFEFF: Prohibited area (open bus, returns 0xFF)
 * - 0xFF00-0xFF7F: I/O Registers
 * - 0xFF80-0xFFFE: HRAM (High RAM, 127 bytes)
 * - 0xFFFF: IE Register (Interrupt Enable)
 */
final class SystemBus implements BusInterface
{
    /** @var array<string, array{device: DeviceInterface, start: int, end: int}> */
    private array $devices = [];

    /** @var array<int, DeviceInterface> I/O register device mapping */
    private array $ioDevices = [];

    /** Component references for M-cycle accurate timing */
    private ?\Gb\Timer\Timer $timer = null;
    private ?\Gb\Dma\OamDma $oamDma = null;

    /**
     * Attach a device to the bus at a specific address range.
     *
     * @param string $name Device name (for identification)
     * @param DeviceInterface $device Device instance
     * @param int $startAddr Start address (inclusive)
     * @param int $endAddr End address (inclusive)
     */
    public function attachDevice(string $name, DeviceInterface $device, int $startAddr, int $endAddr): void
    {
        $this->devices[$name] = [
            'device' => $device,
            'start' => $startAddr,
            'end' => $endAddr,
        ];
    }

    /**
     * Attach an I/O device that handles specific register addresses.
     *
     * @param DeviceInterface $device Device instance
     * @param int ...$addresses Register addresses this device handles
     */
    public function attachIoDevice(DeviceInterface $device, int ...$addresses): void
    {
        foreach ($addresses as $address) {
            $this->ioDevices[$address] = $device;
        }
    }

    /**
     * Read a byte from the bus.
     *
     * @param int $address Memory address (0x0000-0xFFFF)
     * @return int Byte value (0x00-0xFF)
     */
    public function readByte(int $address): int
    {
        $address &= 0xFFFF; // Ensure 16-bit address

        // Cartridge ROM (0x0000-0x7FFF)
        if ($address <= 0x7FFF) {
            return $this->readFromDevice('cartridge', $address);
        }

        // VRAM (0x8000-0x9FFF)
        if ($address <= 0x9FFF) {
            return $this->readFromDevice('vram', $address - 0x8000);
        }

        // External RAM (0xA000-0xBFFF)
        if ($address <= 0xBFFF) {
            return $this->readFromDevice('cartridge', $address);
        }

        // WRAM (0xC000-0xDFFF)
        if ($address <= 0xDFFF) {
            return $this->readFromDevice('wram', $address - 0xC000);
        }

        // Echo RAM (0xE000-0xFDFF) - mirrors 0xC000-0xDDFF
        if ($address <= 0xFDFF) {
            $mirrorAddr = $address - 0x2000; // 0xE000 -> 0xC000
            return $this->readFromDevice('wram', $mirrorAddr - 0xC000);
        }

        // OAM (0xFE00-0xFE9F)
        if ($address <= 0xFE9F) {
            return $this->readFromDevice('oam', $address - 0xFE00);
        }

        // Prohibited area (0xFEA0-0xFEFF) - open bus behavior
        if ($address <= 0xFEFF) {
            return 0xFF; // Open bus returns 0xFF
        }

        // I/O Registers (0xFF00-0xFF7F)
        if ($address <= 0xFF7F) {
            if (isset($this->ioDevices[$address])) {
                return $this->ioDevices[$address]->readByte($address);
            }
            // Unimplemented I/O register, return 0xFF (open bus)
            return 0xFF;
        }

        // HRAM (0xFF80-0xFFFE)
        if ($address <= 0xFFFE) {
            return $this->readFromDevice('hram', $address - 0xFF80);
        }

        // IE Register (0xFFFF) - handled by InterruptController
        if (isset($this->ioDevices[0xFFFF])) {
            return $this->ioDevices[0xFFFF]->readByte(0xFFFF);
        }
        return 0xFF;
    }

    /**
     * Write a byte to the bus.
     *
     * @param int $address Memory address (0x0000-0xFFFF)
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $address, int $value): void
    {
        $address &= 0xFFFF; // Ensure 16-bit address
        $value &= 0xFF; // Ensure 8-bit value

        // Cartridge ROM/RAM (0x0000-0x7FFF)
        if ($address <= 0x7FFF) {
            $this->writeToDevice('cartridge', $address, $value);
            return;
        }

        // VRAM (0x8000-0x9FFF)
        if ($address <= 0x9FFF) {
            $this->writeToDevice('vram', $address - 0x8000, $value);
            return;
        }

        // External RAM (0xA000-0xBFFF)
        if ($address <= 0xBFFF) {
            $this->writeToDevice('cartridge', $address, $value);
            return;
        }

        // WRAM (0xC000-0xDFFF)
        if ($address <= 0xDFFF) {
            $this->writeToDevice('wram', $address - 0xC000, $value);
            return;
        }

        // Echo RAM (0xE000-0xFDFF) - mirrors 0xC000-0xDDFF
        if ($address <= 0xFDFF) {
            $mirrorAddr = $address - 0x2000; // 0xE000 -> 0xC000
            $this->writeToDevice('wram', $mirrorAddr - 0xC000, $value);
            return;
        }

        // OAM (0xFE00-0xFE9F)
        if ($address <= 0xFE9F) {
            $this->writeToDevice('oam', $address - 0xFE00, $value);
            return;
        }

        // Prohibited area (0xFEA0-0xFEFF) - writes ignored
        if ($address <= 0xFEFF) {
            return;
        }

        // I/O Registers (0xFF00-0xFF7F)
        if ($address <= 0xFF7F) {
            if (isset($this->ioDevices[$address])) {
                $this->ioDevices[$address]->writeByte($address, $value);
            }
            // Unimplemented I/O register, ignore write
            return;
        }

        // HRAM (0xFF80-0xFFFE)
        if ($address <= 0xFFFE) {
            $this->writeToDevice('hram', $address - 0xFF80, $value);
            return;
        }

        // IE Register (0xFFFF) - handled by InterruptController
        if (isset($this->ioDevices[0xFFFF])) {
            $this->ioDevices[0xFFFF]->writeByte(0xFFFF, $value);
        }
    }

    /**
     * Read from a specific device.
     *
     * @param string $name Device name
     * @param int $address Address within device's range
     * @return int Byte value (0x00-0xFF)
     */
    private function readFromDevice(string $name, int $address): int
    {
        if (!isset($this->devices[$name])) {
            // Device not attached, return open bus value
            return 0xFF;
        }

        return $this->devices[$name]['device']->readByte($address);
    }

    /**
     * Write to a specific device.
     *
     * @param string $name Device name
     * @param int $address Address within device's range
     * @param int $value Byte value to write
     */
    private function writeToDevice(string $name, int $address, int $value): void
    {
        if (!isset($this->devices[$name])) {
            // Device not attached, ignore write
            return;
        }

        $this->devices[$name]['device']->writeByte($address, $value);
    }

    /**
     * Get a device by name (for testing and PPU access).
     *
     * @param string $name Device name
     * @return DeviceInterface|null Device instance or null if not found
     */
    public function getDevice(string $name): ?DeviceInterface
    {
        return $this->devices[$name]['device'] ?? null;
    }

    /**
     * Set component references for M-cycle accurate timing.
     *
     * Timer and OamDma are ticked at M-cycle granularity during CPU memory operations.
     * PPU and APU are stepped in bulk after instruction execution.
     *
     * @param \Gb\Timer\Timer $timer Timer component
     * @param \Gb\Dma\OamDma $oamDma OAM DMA component
     */
    public function setComponents(
        \Gb\Timer\Timer $timer,
        \Gb\Dma\OamDma $oamDma
    ): void {
        $this->timer = $timer;
        $this->oamDma = $oamDma;
    }

    /**
     * Tick timing-sensitive components at M-cycle granularity.
     *
     * Called by CPU during memory operations to ensure Timer and OamDma
     * observe state changes at exact M-cycle boundaries.
     *
     * @param int $cycles Number of T-cycles (typically 4 for 1 M-cycle)
     */
    public function tickComponents(int $cycles): void
    {
        $this->timer?->tick($cycles);
        $this->oamDma?->tick($cycles);
    }
}
