<?php

declare(strict_types=1);

namespace Gb\Cartridge;

use Gb\Bus\DeviceInterface;

/**
 * Game Boy Cartridge
 *
 * Handles ROM and RAM access for Game Boy cartridges with MBC support.
 * Delegates to the appropriate MBC implementation based on cartridge type.
 *
 * Memory layout:
 * - 0x0000-0x3FFF: ROM Bank 0 (16KB, fixed)
 * - 0x4000-0x7FFF: ROM Bank N (16KB, switchable with MBC)
 * - 0xA000-0xBFFF: External RAM (8KB, switchable with MBC)
 */
final class Cartridge implements DeviceInterface
{
    /** @var CartridgeHeader Parsed cartridge header */
    private readonly CartridgeHeader $header;

    /** @var MbcInterface MBC implementation */
    private MbcInterface $mbc;

    /**
     * @param array<int, int> $romData ROM data loaded from .gb file
     */
    public function __construct(array $romData)
    {
        // Parse cartridge header
        $this->header = CartridgeHeader::fromRom($romData);

        // Create appropriate MBC based on cartridge type
        $this->mbc = $this->createMbc($romData);
    }

    /**
     * Create MBC implementation based on cartridge type.
     *
     * @param array<int, int> $rom ROM data
     * @return MbcInterface MBC implementation
     */
    private function createMbc(array $rom): MbcInterface
    {
        $type = $this->header->cartridgeType;
        $romSize = $this->header->getRomSize();
        $ramSize = $this->header->getRamSize();
        $hasBattery = $type->hasBattery();

        $mbcType = $type->getMbcType();

        return match ($mbcType) {
            'MBC1' => new Mbc1($rom, $romSize, $ramSize, $hasBattery),
            'MBC3' => new Mbc3($rom, $romSize, $ramSize, $hasBattery, $type->hasTimer()),
            'MBC5' => new Mbc5($rom, $romSize, $ramSize, $hasBattery, $type->hasRumble()),
            default => new NoMbc($rom, $ramSize, $hasBattery),
        };
    }

    /**
     * Read a byte from the cartridge.
     *
     * @param int $address Address within cartridge range (0x0000-0x7FFF for ROM, 0xA000-0xBFFF for RAM)
     * @return int Byte value (0x00-0xFF)
     */
    public function readByte(int $address): int
    {
        return $this->mbc->readByte($address);
    }

    /**
     * Write a byte to the cartridge.
     *
     * @param int $address Address within cartridge range
     * @param int $value Byte value to write (0x00-0xFF)
     */
    public function writeByte(int $address, int $value): void
    {
        $this->mbc->writeByte($address, $value);
    }

    /**
     * Step the cartridge (for RTC, etc.).
     *
     * @param int $cycles Number of cycles elapsed
     */
    public function step(int $cycles): void
    {
        $this->mbc->step($cycles);
    }

    /**
     * Load ROM data from a byte array.
     *
     * @param array<int, int> $romData ROM data
     */
    public static function fromRom(array $romData): self
    {
        return new self($romData);
    }

    /**
     * Get the parsed cartridge header.
     *
     * @return CartridgeHeader
     */
    public function getHeader(): CartridgeHeader
    {
        return $this->header;
    }

    /**
     * Get the MBC implementation.
     *
     * @return MbcInterface
     */
    public function getMbc(): MbcInterface
    {
        return $this->mbc;
    }

    /**
     * Get external RAM for saving.
     *
     * @return array<int, int> RAM data
     */
    public function getRam(): array
    {
        return $this->mbc->getRam();
    }

    /**
     * Set external RAM (for loading saves).
     *
     * @param array<int, int> $ram RAM data
     */
    public function setRam(array $ram): void
    {
        $this->mbc->setRam($ram);
    }

    /**
     * Check if cartridge has battery-backed RAM.
     *
     * @return bool True if RAM should be persisted
     */
    public function hasBatteryBackedRam(): bool
    {
        return $this->mbc->hasBatteryBackedRam();
    }

    /**
     * Get RTC state (if MBC3 with RTC).
     *
     * @return array<string, int>|null RTC state, or null if no RTC
     */
    public function getRtcState(): ?array
    {
        if ($this->mbc instanceof Mbc3) {
            return $this->mbc->getRtcState();
        }
        return null;
    }

    /**
     * Set RTC state (if MBC3 with RTC).
     *
     * @param array<string, int> $state RTC state
     */
    public function setRtcState(array $state): void
    {
        if ($this->mbc instanceof Mbc3) {
            $this->mbc->setRtcState($state);
        }
    }

    /**
     * Get current ROM bank number (for savestates).
     * Returns 0 for cartridges without MBC.
     */
    public function getCurrentRomBank(): int
    {
        return $this->mbc->getCurrentRomBank();
    }

    /**
     * Get current RAM bank number (for savestates).
     * Returns 0 for cartridges without RAM banking.
     */
    public function getCurrentRamBank(): int
    {
        return $this->mbc->getCurrentRamBank();
    }

    /**
     * Check if RAM is currently enabled (for savestates).
     */
    public function isRamEnabled(): bool
    {
        return $this->mbc->isRamEnabled();
    }

    /**
     * Set current ROM bank (for savestates).
     */
    public function setCurrentRomBank(int $bank): void
    {
        $this->mbc->setCurrentRomBank($bank);
    }

    /**
     * Set current RAM bank (for savestates).
     */
    public function setCurrentRamBank(int $bank): void
    {
        $this->mbc->setCurrentRamBank($bank);
    }

    /**
     * Set RAM enabled state (for savestates).
     */
    public function setRamEnabled(bool $enabled): void
    {
        $this->mbc->setRamEnabled($enabled);
    }

    /**
     * Get RAM data as base64-encoded string (for savestates).
     */
    public function getRamData(): string
    {
        return base64_encode(pack('C*', ...$this->getRam()));
    }

    /**
     * Load RAM data from base64-encoded string (for savestates).
     */
    public function loadRamData(string $data): void
    {
        $ram = array_values(unpack('C*', base64_decode($data)));
        $this->setRam($ram);
    }
}
