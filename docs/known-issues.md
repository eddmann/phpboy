# PHPBoy Known Issues

This document tracks known compatibility issues, bugs, and limitations.

**Last Updated:** 2025-11-07
**PHPBoy Version:** Step 13 (Test ROM Integration)

## Critical Issues

### CPU-001: Incorrect Flag Handling in ALU Operations
- **Severity:** Critical
- **Status:** Open
- **Affected Components:** CPU, InstructionSet
- **Symptoms:** Many arithmetic and logic operations set flags incorrectly
- **Impact:** Game logic failures, incorrect branching
- **Test ROMs Affected:**
  - 04-op r,imm.gb (immediate operations)
  - 05-op rp.gb (16-bit operations)
  - 07-jr,jp,call,ret,rst.gb (conditional jumps/calls)
  - 09-op r,r.gb (register operations)
- **Suspected Cause:**
  - Half-carry flag (H) computation incorrect for various operations
  - Carry flag (C) not handled properly in 16-bit arithmetic
  - Zero flag (Z) may be incorrect in some edge cases

### CPU-002: Missing or Incorrect DAA Implementation
- **Severity:** High
- **Status:** ✅ **FIXED**
- **Affected Components:** CPU, InstructionSet
- **Symptoms:** DAA (Decimal Adjust Accumulator) instruction fails test #6
- **Impact:** BCD arithmetic broken, affects score displays and similar features
- **Test ROMs Affected:**
  - 01-special.gb test #6 (NOW PASSING)
- **Fix Applied:** Rewrote DAA to calculate correction before applying, properly handle both addition and subtraction modes
- **Commit:** 5708959

### CPU-003: Infinite Loop in Memory Operations
- **Severity:** Critical
- **Status:** ✅ **PARTIALLY FIXED**
- **Affected Components:** CPU, InstructionSet
- **Symptoms:** Test ROM 11-op a,(hl).gb times out after 30 seconds
- **Impact:** Emulator hangs, games may freeze
- **Test ROMs Affected:**
  - 11-op a,(hl).gb (no longer times out, but still fails on later tests)
- **Fix Applied:** DAA fix eliminated the infinite loop
- **Remaining:** Still fails on DAA test (opcode 27), but progresses much further

## High Priority Issues

### CPU-004: SP Operations Flag Handling
- **Severity:** High
- **Status:** ✅ **FIXED**
- **Affected Components:** CPU, InstructionSet
- **Symptoms:** ADD SP,e8 and LD HL,SP+e8 set flags incorrectly
- **Impact:** Stack frame operations unreliable
- **Test ROMs Affected:**
  - 03-op sp,hl.gb (NOW PASSING)
- **Fix Applied:** Corrected flag calculation to use unsigned 8-bit value for H/C flags
- **Commit:** 5708959

### CPU-005: BIT Instruction Flag Handling
- **Severity:** High
- **Status:** Open
- **Affected Components:** CPU, InstructionSet (CB-prefixed)
- **Symptoms:** BIT n,r instruction doesn't set flags correctly
- **Impact:** Bit testing operations fail
- **Test ROMs Affected:**
  - 10-bit ops.gb
- **Suspected Cause:** Z flag should be set if bit is 0, N should be 0, H should be 1

## Medium Priority Issues

### CPU-006: Instruction Cycle Timing Inaccuracies
- **Severity:** Medium
- **Status:** Open
- **Affected Components:** CPU, InstructionSet
- **Symptoms:** Many instructions have off-by-one cycle counts
- **Impact:** PPU synchronization issues, audio glitches, timing-sensitive bugs
- **Test ROMs Affected:**
  - instr_timing.gb (widespread timing errors)
- **Examples:**
  - JR cc,e8: Off by 1 cycle (should be 12 taken, 8 not taken)
  - RET cc: Off by 1-3 cycles
  - CALL cc,nn: Off by 1-3 cycles
  - CB-prefixed instructions: Off by 1 cycle
- **Notes:** Functional impact is low but causes observable glitches

### INT-001: Serial Interrupt Timing
- **Severity:** Low
- **Status:** Open
- **Affected Components:** Serial
- **Symptoms:** Serial interrupt timing may not match hardware
- **Impact:** Test ROMs work but real serial link cable communication may fail
- **Notes:** Serial interrupt should occur 8 clocks after transfer start

## Compatibility Issues

### Commercial ROM Compatibility: Unknown
- **Status:** Not Yet Tested
- **Impact:** Cannot yet verify commercial game compatibility
- **Required for Step 13:** Test at least 3 commercial ROMs for 5 minutes each
- **Blockers:** Need to fix critical CPU issues first

## Test ROM Pass Rates

| Test Suite | Pass Rate | Status | Progress |
|------------|-----------|--------|----------|
| Blargg CPU Instructions | 45.5% (5/11) | 🟡 Improving | +3 tests (was 18.2%) |
| Blargg Instruction Timing | 0% (0/1) | ❌ Below target | No change |
| **Overall** | **41.7% (5/12)** | 🟡 **Improving** | **+25% (was 16.7%)** |

### Step 13 Requirements

Per PLAN.md Step 13, we need:
- ✅ Test ROM harness implemented
- ❌ 90%+ Blargg tests passing (currently 18.2%)
- ⏳ 10+ Mooneye tests run (not started)
- ⏳ Acid tests run (not started)
- ⏳ 3+ commercial ROMs playable 5min (blocked on CPU fixes)

## Workarounds

Currently, no workarounds are available for the critical CPU issues. These must be fixed in the emulator core.

## Investigation Notes

### Flag Computation
The Game Boy CPU flag register (F) has specific bit positions:
- Bit 7: Z (Zero)
- Bit 6: N (Subtract/Add)
- Bit 5: H (Half Carry)
- Bit 4: C (Carry)
- Bits 3-0: Always 0

Many operations have nuanced flag behavior that differs from typical CPU architectures:
- **Half Carry (H):** Set if carry from bit 3 to 4 (8-bit) or bit 11 to 12 (16-bit)
- **DAA:** Extremely complex flag behavior based on previous operation
- **ADD SP,e8:** Always clears Z and N, sets H and C based on lower byte only

### Timing
Game Boy CPU timing is complex:
- Most instructions: 4 cycles per machine cycle
- Memory access: Adds cycles
- Conditional branches: Different cycles if taken vs not taken
- CB-prefixed: Usually +4 cycles vs non-prefixed equivalent

## References

- [Pan Docs - CPU Instruction Set](https://gbdev.io/pandocs/CPU_Instruction_Set.html)
- [Game Boy CPU Manual (PDF)](http://marc.rawer.de/Gameboy/Docs/GBCPUman.pdf)
- [Blargg's Test ROM Documentation](https://github.com/retrio/gb-test-roms)
- [Game Boy Opcode Table](https://www.pastraiser.com/cpu/gameboy/gameboy_opcodes.html)
