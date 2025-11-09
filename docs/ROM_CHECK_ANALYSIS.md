# PHPBoy ROM Check Test Analysis

**Date:** 2025-11-08
**Current Status:** 9/39 Mooneye tests passing (23%), 12/12 Blargg tests passing (100%)

## Executive Summary

PHPBoy has excellent **instruction correctness** (100% Blargg pass rate) but lacks **timing accuracy** (23% Mooneye pass rate). The main issues are:

1. **DMA runs 4x too fast** - treating T-cycles as M-cycles
2. **RETI doesn't enable interrupts** - missing IME enable
3. **Timer uses wrong model** - counter accumulation vs bit-selection
4. **CPU executes atomically** - no M-cycle boundaries

Quick wins (Fixes 1-3) can improve from 23% to 41-46% with ~5 hours of work.

---

## Current Test Results

### Blargg CPU Instruction Tests: 12/12 ✅
- 01-special.gb ✅
- 02-interrupts.gb ✅
- 03-op sp,hl.gb ✅
- 04-op r,imm.gb ✅
- 05-op rp.gb ✅
- 06-ld r,r.gb ✅
- 07-jr,jp,call,ret,rst.gb ✅
- 08-misc instrs.gb ✅
- 09-op r,r.gb ✅
- 10-bit ops.gb ✅
- 11-op a,(hl).gb ✅
- instr_timing.gb ✅

### Mooneye Acceptance Tests: 9/39 (23%)

**Passing (9 tests):**
- di_timing-GS ✅
- halt_ime0_ei ✅
- halt_ime0_nointr_timing ✅
- halt_ime1_timing ✅
- if_ie_registers ✅
- intr_timing ✅
- instr/daa ✅
- timer/tim00_div_trigger ✅
- timer/tim01 ✅
- timer/tim11_div_trigger ✅

**Failing by Category:**

**Instruction Timing (12 tests):**
- add_sp_e_timing ❌
- call_cc_timing ❌
- call_timing ❌
- jp_cc_timing ❌
- jp_timing ❌
- ld_hl_sp_e_timing ❌
- pop_timing ❌
- push_timing ❌
- ret_cc_timing ❌
- ret_timing ❌
- reti_timing ❌
- rst_timing ❌

**Interrupt/EI Timing (4 tests):**
- ei_sequence ❌
- ei_timing ❌
- rapid_di_ei ❌
- reti_intr_timing ❌

**OAM DMA Timing (3 tests):**
- oam_dma_restart ❌
- oam_dma_start ❌
- oam_dma_timing ❌

**Timer Edge Cases (13 tests):**
- timer/div_write ❌
- timer/rapid_toggle ❌
- timer/tim00 ❌
- timer/tim01_div_trigger ❌
- timer/tim10 ❌
- timer/tim10_div_trigger ❌
- timer/tim11 ❌
- timer/tima_reload ❌
- timer/tima_write_reloading ❌
- timer/tma_write_reloading ❌

---

## Root Cause Analysis

### 1. OAM DMA Running 4x Too Fast (CRITICAL BUG)

**Location:** `src/Dma/OamDma.php:119`

**Problem:** DMA `tick()` receives T-cycles from CPU but treats them as M-cycles, making DMA complete in 160 T-cycles instead of 640 T-cycles (160 M-cycles).

**Current Implementation:**
```php
public function tick(int $cycles): void
{
    if (!$this->dmaActive) {
        return;
    }

    // Transfer one byte per M-cycle
    for ($i = 0; $i < $cycles; $i++) {  // ← Treats $cycles as M-cycles
        if ($this->dmaProgress >= self::TRANSFER_LENGTH) {
            $this->dmaActive = false;
            break;
        }
        // ... transfer logic
    }
}
```

**Call Site (Emulator.php:309-310):**
```php
$cycles = $this->cpu->step();  // Returns T-cycles (e.g., 4, 8, 12)
$this->oamDma?->tick($cycles);  // ← Passes T-cycles
```

**Issue:** The loop iterates `$cycles` times (T-cycles), but should iterate `$cycles / 4` times (M-cycles).

**Impact:** Fixes ~2-3 tests
- oam_dma_timing ❌
- oam_dma_start ❌

---

### 2. RETI Doesn't Enable Interrupts (CRITICAL BUG)

**Location:** `src/Cpu/InstructionSet.php:3407-3422`

**Problem:** RETI instruction returns from interrupt but doesn't set IME flag.

**Current Implementation:**
```php
0xD9 => new Instruction(
    opcode: 0xD9,
    mnemonic: 'RETI',
    cycles: 16,
    handler: static function (Cpu $cpu): int {
        $low = $cpu->getBus()->readByte($cpu->getSP()->get());
        $cpu->getSP()->increment();
        $high = $cpu->getBus()->readByte($cpu->getSP()->get());
        $cpu->getSP()->increment();
        $cpu->getPC()->set(($high << 8) | $low);
        // Missing: Should enable IME immediately!
        return 16;
    },
),
```

**SameBoy Implementation:**
```c
static void reti(GB_gameboy_t *gb, uint8_t opcode) {
    ret(gb, opcode);
    gb->ime = true;  // ← Immediate enable
}
```

**Our Implementation:** Missing the `gb->ime = true` line!

**Issue:** RETI should enable interrupts immediately (not with 1-instruction delay like EI).

**Impact:** Fixes 2 tests
- reti_timing ❌
- reti_intr_timing ❌

---

### 3. DMA Start Delay Missing

**Location:** `src/Dma/OamDma.php:119-146`

**Problem:** DMA starts transferring bytes immediately in the same M-cycle that DMA is triggered. According to Pan Docs, there should be a 1 M-cycle delay before first byte transfer.

**Expected Behavior:**
1. **Write to 0xFF46:** Triggers DMA
2. **Delay:** 1 M-cycle delay before first byte transfer
3. **Transfer:** 160 M-cycles to transfer 160 bytes
4. **Total:** 161 M-cycles from trigger to completion

**Current Behavior:** Transfer starts immediately, no startup delay.

**Impact:** Fixes 0-1 tests
- oam_dma_start ❌ (may already be fixed by Fix #1)

---

### 4. Timer Uses Wrong Architecture

**Location:** `src/Timer/Timer.php`

**Problem:** Uses cycle accumulation instead of bit-selection from DIV counter.

**Current Approach:**
```php
$this->timaCounter += $cycles;
if ($this->timaCounter >= $frequency) {
    $this->incrementTima();
}
```

**SameBoy Approach:**
- Uses bit-selection: `TAC_TRIGGER_BITS[] = {512, 8, 32, 128}`
- Detects falling edge: "TIMA increases when a specific high-bit becomes a low-bit"
- Implements 3-state reload machine (RUNNING → RELOADING → RELOADED)

**Why This Matters:**
- Writing to DIV can trigger TIMA increment (glitch behavior)
- Changing TAC can cause immediate TIMA increment if selected bit was high
- TIMA reload takes 4 M-cycles, during which writes to TIMA/TMA have edge cases

**Impact:** Affects 13 timer tests (requires medium-hard effort)

---

### 5. CPU Executes Instructions Atomically

**Location:** `src/Cpu/InstructionSet.php` (entire file)

**Problem:** Instructions execute all-at-once and return total cycles. Real hardware performs operations incrementally over M-cycles.

**Example - CALL instruction:**
- **Current:** Reads address, pushes PC, jumps → returns 24 cycles atomically
- **Real hardware:** 6 M-cycles with state changes at specific boundaries

**SameBoy Approach:**
- Uses `cycle_read()` and `cycle_write()` with `pending_cycles = 4`
- Memory operations happen at specific M-cycle boundaries
- Enables accurate modeling of register access conflicts

**Impact:** Affects 12 instruction timing tests (requires major refactor)

---

## Comparison with SameBoy

| Feature | PHPBoy | SameBoy | Impact |
|---------|--------|---------|--------|
| **Instruction execution** | Atomic | M-cycle stepping | 12 timing tests |
| **RETI enables IME** | ❌ No | ✅ Immediate | 2 tests |
| **DMA speed** | ❌ 4x too fast | ✅ Correct | 3 tests |
| **DMA start delay** | ❌ No delay | ✅ 1 M-cycle | 1 test |
| **Timer model** | Counter accumulation | Bit-selection + edge detection | 13 tests |
| **TIMA reload** | Instant | 4 M-cycle state machine | 3 tests |
| **Cycle tracking** | T-cycles | T-cycles with M-cycle conversion | Foundation |

---

## Prioritized Fix Roadmap

### Quick Wins (4-5 hours work, +7-8 tests)

**Fix 1: OAM DMA Speed** ⭐ Easy (30 min, +2-3 tests)
- **File:** `src/Dma/OamDma.php:119`
- **Change:** Convert T-cycles to M-cycles before transferring
- **Expected:** 11-12/39 tests passing (28-31%)

**Fix 2: RETI IME Enable** ⭐ Easy (15 min, +2 tests)
- **Files:** `src/Cpu/Cpu.php`, `src/Cpu/InstructionSet.php:3418`
- **Change:** Add `setIMEImmediate()` and call in RETI
- **Expected:** 13-14/39 tests passing (33-36%)

**Fix 3: DMA Start Delay** ⭐⭐ Medium (1 hour, +0-1 tests)
- **File:** `src/Dma/OamDma.php`
- **Change:** Add 1 M-cycle delay before first byte transfer
- **Expected:** 14-17/39 tests passing (36-44%)

**Combined Expected Result:** 16-18/39 tests passing (41-46%)

---

### Medium Effort (8 hours work, +10-11 tests)

**Fix 4: TIMA Reload Delay** ⭐⭐ Medium (2 hours, +3 tests)
- **File:** `src/Timer/Timer.php`
- **Change:** Implement 4 M-cycle reload state machine
- **Expected:** +3 tests (tima_reload, tima_write_reloading, tma_write_reloading)

**Fix 5: Timer Bit-Selection** ⭐⭐⭐ Hard (4 hours, +4 tests)
- **File:** `src/Timer/Timer.php`
- **Change:** Rewrite timer to use 16-bit DIV counter with bit-selection
- **Expected:** +4 tests (tim*_div_trigger tests)

**Combined Expected Result:** 20-22/39 tests passing (51-56%)

---

### Major Refactor (24+ hours work, +12 tests)

**Fix 6: M-Cycle Accurate CPU** ⭐⭐⭐⭐ Very Hard (16+ hours, +12 tests)
- **File:** `src/Cpu/InstructionSet.php` (complete rewrite)
- **Change:** Implement M-cycle stepping for all 256 instructions
- **Expected:** All 12 instruction timing tests

**Expected Result:** 32-34/39 tests passing (82-87%)

---

## Recommended Implementation Order

**Phase 1 (Today):** Fixes 1-3 → Get to 41-46% with minimal risk

**Phase 2 (This week):** Fixes 4-5 → Get to 51-56% with moderate effort

**Phase 3 (Next sprint):** Fix 6 → Get to 82-87% with architectural changes

**Remaining gaps:**
- PPU timing edge cases
- APU timing (not tested by current suite)
- Hardware quirks/glitches

---

## Specific Code Changes

### Fix 1: OAM DMA Speed

**File:** `src/Dma/OamDma.php:119`

```php
public function tick(int $cycles): void
{
    if (!$this->dmaActive) {
        return;
    }

    // Convert T-cycles to M-cycles (1 M-cycle = 4 T-cycles)
    $mCycles = intdiv($cycles, 4);

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
    }
}
```

---

### Fix 2: RETI IME Enable

**File 1:** `src/Cpu/Cpu.php` (add new method)

```php
/**
 * Enable interrupts immediately (used by RETI).
 * Unlike setIME(true), this enables IME without a 1-instruction delay.
 */
public function setIMEImmediate(): void
{
    $this->ime = true;
    $this->imeDelay = 0;
}
```

**File 2:** `src/Cpu/InstructionSet.php:3407-3422` (update RETI handler)

```php
0xD9 => new Instruction(
    opcode: 0xD9,
    mnemonic: 'RETI',
    cycles: 16,
    handler: static function (Cpu $cpu): int {
        $low = $cpu->getBus()->readByte($cpu->getSP()->get());
        $cpu->getSP()->increment();
        $high = $cpu->getBus()->readByte($cpu->getSP()->get());
        $cpu->getSP()->increment();
        $cpu->getPC()->set(($high << 8) | $low);

        // RETI enables interrupts immediately (not delayed like EI)
        $cpu->setIMEImmediate();

        return 16;
    },
),
```

---

### Fix 3: DMA Start Delay

**File:** `src/Dma/OamDma.php` (update class)

```php
final class OamDma
{
    private const OAM_START = 0xFE00;
    private const TRANSFER_LENGTH = 160;

    private bool $dmaActive = false;
    private int $dmaSource = 0x0000;
    private int $dmaProgress = 0;
    private int $dmaDelay = 0;  // ← Add delay counter

    // ... other methods ...

    private function startDmaTransfer(int $sourcePage): void
    {
        $this->dmaSource = ($sourcePage << 8) & 0xFF00;
        $this->dmaActive = true;
        $this->dmaProgress = 0;
        $this->dmaDelay = 1;  // ← 1 M-cycle delay before first byte
    }

    public function tick(int $cycles): void
    {
        if (!$this->dmaActive) {
            return;
        }

        // Convert T-cycles to M-cycles (1 M-cycle = 4 T-cycles)
        $mCycles = intdiv($cycles, 4);

        // Handle startup delay
        if ($this->dmaDelay > 0) {
            $delayToProcess = min($this->dmaDelay, $mCycles);
            $this->dmaDelay -= $delayToProcess;
            $mCycles -= $delayToProcess;

            if ($mCycles <= 0) {
                return;  // Still in delay phase
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
        }
    }
}
```

---

## Testing Strategy

### After Each Fix

```bash
# Test DMA fixes
make test -- --filter="oam_dma"

# Test RETI fix
make test -- --filter="reti"

# Full Mooneye suite
make test -- tests/Integration/MooneyeTestRomsTest.php

# Check overall progress
make test -- tests/Integration/
```

### Track Progress

- **Baseline:** 9/39 (23%)
- **After Fix 1:** ~11-12/39 (28-31%)
- **After Fix 2:** ~13-14/39 (33-36%)
- **After Fix 3:** ~14-17/39 (36-44%)
- **Target (Quick Wins):** 16-18/39 (41-46%)

---

## References

### SameBoy Implementation Study

**Timer (bit-selection model):**
- Uses `TAC_TRIGGER_BITS[] = {512, 8, 32, 128}` for bit positions
- Detects falling edge: "TIMA increases when a specific high-bit becomes a low-bit"
- Implements 3-state reload machine via `advance_tima_state_machine()`

**CPU Timing:**
- Uses `cycle_read()` and `cycle_write()` with `pending_cycles = 4`
- M-cycle stepping, not atomic execution
- Hardware conflict simulation for accurate register access timing

**RETI Instruction:**
```c
static void reti(GB_gameboy_t *gb, uint8_t opcode) {
    ret(gb, opcode);
    gb->ime = true;  // Immediate enable
}
```

**DMA:**
- Explicit M-cycle tracking
- Startup delay before first byte transfer
- 160 M-cycles (640 T-cycles) for full transfer

### Documentation

- **Pan Docs:** https://gbdev.io/pandocs/
- **SameBoy Source:** https://github.com/LIJI32/SameBoy
- **PHPBoy Research:** `docs/research.md`

---

## Conclusion

PHPBoy has a solid foundation with 100% Blargg test pass rate, proving instruction correctness. The Mooneye failures are all **timing accuracy** issues, not logic bugs.

The quick wins (Fixes 1-3) are low-risk, high-impact changes that can nearly double the Mooneye pass rate in a single afternoon. These fixes align PHPBoy's timing model with SameBoy's proven approach.

**Next Steps:**
1. Implement Fix 1 (DMA speed) - 30 minutes
2. Implement Fix 2 (RETI IME) - 15 minutes
3. Implement Fix 3 (DMA delay) - 1 hour
4. Run full test suite and measure improvement
5. Commit with detailed conventional commit message

**Long-term:**
- Medium effort fixes (4-5) → 51-56% pass rate
- Major CPU refactor (6) → 82-87% pass rate
- Final edge cases → 90-100% pass rate
