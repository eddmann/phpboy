<?php

declare(strict_types=1);

namespace Gb\Cartridge;

/**
 * MBC3 (Memory Bank Controller 3)
 *
 * Features:
 * - ROM banking: up to 127 banks (2MB max)
 * - RAM banking: up to 4 banks (32KB max)
 * - Real-Time Clock (RTC) registers
 *
 * Register Layout:
 * - 0x0000-0x1FFF: RAM and RTC Enable (write 0x0A to enable)
 * - 0x2000-0x3FFF: ROM Bank Number (7 bits, 0x01-0x7F)
 * - 0x4000-0x5FFF: RAM Bank Number (0x00-0x03) or RTC Register Select (0x08-0x0C)
 * - 0x6000-0x7FFF: Latch Clock Data (write 0x00 then 0x01 to latch)
 *
 * RTC Registers (when selected via 0x4000-0x5FFF):
 * - 0x08: RTC Seconds (0-59)
 * - 0x09: RTC Minutes (0-59)
 * - 0x0A: RTC Hours (0-23)
 * - 0x0B: RTC Day Counter (lower 8 bits)
 * - 0x0C: RTC Day Counter (upper 1 bit) + Halt flag + Carry flag
 *
 * Reference: Pan Docs - MBC3
 */
final class Mbc3 implements MbcInterface
{
    /** @var array<int, int> ROM data */
    private array $rom;

    /** @var array<int, int> External RAM */
    private array $ram;

    /** @var int Number of ROM banks */
    private int $romBankCount;

    /** @var int RAM size in bytes */
    private int $ramSize;

    /** @var bool Whether RAM has battery backup */
    private bool $hasBattery;

    /** @var bool Whether RTC is present */
    private bool $hasRtc;

    /** @var bool RAM/RTC enable flag */
    private bool $ramRtcEnabled = false;

    /** @var int ROM bank number (1-127) */
    private int $romBank = 0x01;

    /** @var int RAM bank number (0-3) or RTC register select (0x08-0x0C) */
    private int $ramBankOrRtc = 0x00;

    /** @var int Latch state (for latching RTC) */
    private int $latchState = 0;

    // RTC registers (latched values)
    /** @var int RTC seconds (0-59) */
    private int $rtcSeconds = 0;

    /** @var int RTC minutes (0-59) */
    private int $rtcMinutes = 0;

    /** @var int RTC hours (0-23) */
    private int $rtcHours = 0;

    /** @var int RTC day counter (lower 8 bits) */
    private int $rtcDayLow = 0;

    /** @var int RTC day counter (upper bit) + flags */
    private int $rtcDayHigh = 0;

    // RTC internal (non-latched) state
    /** @var int Internal seconds counter */
    private int $internalSeconds = 0;

    /** @var int Internal minutes counter */
    private int $internalMinutes = 0;

    /** @var int Internal hours counter */
    private int $internalHours = 0;

    /** @var int Internal day counter (9 bits) */
    private int $internalDays = 0;

    /** @var bool RTC halt flag */
    private bool $rtcHalt = false;

    /** @var int Cycle accumulator for RTC ticking */
    private int $cycleAccumulator = 0;

    /** CPU cycles per second (for RTC ticking) */
    private const CYCLES_PER_SECOND = 4194304;

    /**
     * @param array<int, int> $rom ROM data
     * @param int $romSize ROM size in bytes
     * @param int $ramSize RAM size in bytes
     * @param bool $hasBattery Whether RAM has battery backup
     * @param bool $hasRtc Whether RTC is present
     */
    public function __construct(array $rom, int $romSize, int $ramSize, bool $hasBattery, bool $hasRtc)
    {
        $this->rom = $rom;
        $this->romBankCount = max(2, (int)($romSize / (16 * 1024)));
        $this->ramSize = $ramSize;
        $this->hasBattery = $hasBattery;
        $this->hasRtc = $hasRtc;

        // Initialize RAM
        $this->ram = array_fill(0, $ramSize, 0x00);
    }

    public function readByte(int $address): int
    {
        if ($address < 0x4000) {
            // ROM Bank 0 (0x0000-0x3FFF)
            return $this->rom[$address] ?? 0xFF;
        }

        if ($address < 0x8000) {
            // ROM Bank N (0x4000-0x7FFF)
            $offset = $this->romBank * 0x4000 + ($address - 0x4000);
            if ($offset < count($this->rom)) {
                return $this->rom[$offset];
            }
            return 0xFF;
        }

        if ($address >= 0xA000 && $address < 0xC000) {
            // External RAM or RTC (0xA000-0xBFFF)
            if (!$this->ramRtcEnabled) {
                return 0xFF; // RAM/RTC disabled
            }

            // Check if RTC register is selected
            if ($this->ramBankOrRtc >= 0x08 && $this->ramBankOrRtc <= 0x0C) {
                return $this->readRtcRegister($this->ramBankOrRtc);
            }

            // RAM bank read
            if ($this->ramSize === 0) {
                return 0xFF; // No RAM
            }

            $ramBank = $this->ramBankOrRtc & 0x03;
            $offset = $ramBank * 0x2000 + ($address - 0xA000);
            if ($offset < $this->ramSize) {
                return $this->ram[$offset];
            }
            return 0xFF;
        }

        return 0xFF; // Out of range
    }

    public function writeByte(int $address, int $value): void
    {
        if ($address < 0x2000) {
            // RAM and RTC Enable (0x0000-0x1FFF)
            $this->ramRtcEnabled = ($value & 0x0F) === 0x0A;
            return;
        }

        if ($address < 0x4000) {
            // ROM Bank Number (0x2000-0x3FFF)
            $this->romBank = $value & 0x7F;

            // Bank 0 is not selectable, use bank 1 instead
            if ($this->romBank === 0x00) {
                $this->romBank = 0x01;
            }

            // Clamp to available banks
            $this->romBank = $this->romBank % $this->romBankCount;
            return;
        }

        if ($address < 0x6000) {
            // RAM Bank Number or RTC Register Select (0x4000-0x5FFF)
            $this->ramBankOrRtc = $value;
            return;
        }

        if ($address < 0x8000) {
            // Latch Clock Data (0x6000-0x7FFF)
            // Write 0x00 then 0x01 to latch RTC
            if ($this->latchState === 0 && $value === 0x00) {
                $this->latchState = 1;
            } elseif ($this->latchState === 1 && $value === 0x01) {
                $this->latchRtc();
                $this->latchState = 0;
            } else {
                $this->latchState = 0;
            }
            return;
        }

        if ($address >= 0xA000 && $address < 0xC000) {
            // External RAM or RTC write (0xA000-0xBFFF)
            if (!$this->ramRtcEnabled) {
                return; // RAM/RTC disabled
            }

            // Check if RTC register is selected
            if ($this->ramBankOrRtc >= 0x08 && $this->ramBankOrRtc <= 0x0C) {
                $this->writeRtcRegister($this->ramBankOrRtc, $value);
                return;
            }

            // RAM bank write
            if ($this->ramSize === 0) {
                return; // No RAM
            }

            $ramBank = $this->ramBankOrRtc & 0x03;
            $offset = $ramBank * 0x2000 + ($address - 0xA000);
            if ($offset < $this->ramSize) {
                $this->ram[$offset] = $value & 0xFF;
            }
        }
    }

    /**
     * Read RTC register.
     *
     * @param int $register Register select (0x08-0x0C)
     * @return int Register value
     */
    private function readRtcRegister(int $register): int
    {
        if (!$this->hasRtc) {
            return 0xFF;
        }

        return match ($register) {
            0x08 => $this->rtcSeconds,
            0x09 => $this->rtcMinutes,
            0x0A => $this->rtcHours,
            0x0B => $this->rtcDayLow,
            0x0C => $this->rtcDayHigh,
            default => 0xFF,
        };
    }

    /**
     * Write RTC register.
     *
     * @param int $register Register select (0x08-0x0C)
     * @param int $value Value to write
     */
    private function writeRtcRegister(int $register, int $value): void
    {
        if (!$this->hasRtc) {
            return;
        }

        match ($register) {
            0x08 => $this->internalSeconds = $this->rtcSeconds = $value & 0x3F, // 0-59
            0x09 => $this->internalMinutes = $this->rtcMinutes = $value & 0x3F, // 0-59
            0x0A => $this->internalHours = $this->rtcHours = $value & 0x1F,   // 0-23
            0x0B => $this->internalDays = ($this->internalDays & 0x100) | $value,
            0x0C => $this->handleRtcDayHigh($value),
            default => null,
        };

        // Update latched day values when writing to day registers
        if ($register === 0x0B) {
            $this->rtcDayLow = $value;
        }
    }

    /**
     * Handle write to RTC day high register (0x0C).
     *
     * @param int $value Value to write
     */
    private function handleRtcDayHigh(int $value): void
    {
        // Bit 0: Most significant bit of day counter
        $this->internalDays = ($this->internalDays & 0xFF) | (($value & 0x01) << 8);

        // Bit 6: Halt flag (0 = active, 1 = halted)
        $this->rtcHalt = ($value & 0x40) !== 0;

        // Bit 7: Day counter carry (1 = overflow occurred)
        // This bit is set when day counter overflows from 511 to 0
        // Writing 1 to this bit clears it
        if (($value & 0x80) === 0) {
            $this->rtcDayHigh = $value & 0xC1;
        } else {
            $this->rtcDayHigh = $value & 0xC1;
        }
    }

    /**
     * Latch RTC registers (copy internal state to latched registers).
     */
    private function latchRtc(): void
    {
        if (!$this->hasRtc) {
            return;
        }

        $this->rtcSeconds = $this->internalSeconds;
        $this->rtcMinutes = $this->internalMinutes;
        $this->rtcHours = $this->internalHours;
        $this->rtcDayLow = $this->internalDays & 0xFF;
        $this->rtcDayHigh = (($this->internalDays >> 8) & 0x01) |
                           ($this->rtcHalt ? 0x40 : 0x00) |
                           ($this->rtcDayHigh & 0x80); // Preserve carry flag
    }

    public function step(int $cycles): void
    {
        if (!$this->hasRtc || $this->rtcHalt) {
            return; // RTC not present or halted
        }

        $this->cycleAccumulator += $cycles;

        // Tick RTC every second
        while ($this->cycleAccumulator >= self::CYCLES_PER_SECOND) {
            $this->cycleAccumulator -= self::CYCLES_PER_SECOND;
            $this->tickRtc();
        }
    }

    /**
     * Tick RTC by one second.
     */
    private function tickRtc(): void
    {
        $this->internalSeconds++;

        if ($this->internalSeconds >= 60) {
            $this->internalSeconds = 0;
            $this->internalMinutes++;

            if ($this->internalMinutes >= 60) {
                $this->internalMinutes = 0;
                $this->internalHours++;

                if ($this->internalHours >= 24) {
                    $this->internalHours = 0;
                    $this->internalDays++;

                    // Day counter overflow (512 days)
                    if ($this->internalDays >= 512) {
                        $this->internalDays = 0;
                        $this->rtcDayHigh |= 0x80; // Set carry flag
                    }
                }
            }
        }
    }

    public function getRam(): array
    {
        return $this->ram;
    }

    public function setRam(array $ram): void
    {
        $this->ram = $ram;
    }

    public function hasBatteryBackedRam(): bool
    {
        return $this->hasBattery && ($this->ramSize > 0 || $this->hasRtc);
    }

    /**
     * Get RTC state for saving.
     *
     * @return array<string, int> RTC state
     */
    public function getRtcState(): array
    {
        return [
            'seconds' => $this->internalSeconds,
            'minutes' => $this->internalMinutes,
            'hours' => $this->internalHours,
            'days' => $this->internalDays,
            'halt' => $this->rtcHalt ? 1 : 0,
            'dayHigh' => $this->rtcDayHigh,
        ];
    }

    /**
     * Set RTC state from save.
     *
     * @param array<string, int> $state RTC state
     */
    public function setRtcState(array $state): void
    {
        $this->internalSeconds = $state['seconds'] ?? 0;
        $this->internalMinutes = $state['minutes'] ?? 0;
        $this->internalHours = $state['hours'] ?? 0;
        $this->internalDays = $state['days'] ?? 0;
        $this->rtcHalt = ($state['halt'] ?? 0) !== 0;
        $this->rtcDayHigh = $state['dayHigh'] ?? 0;
    }
}
