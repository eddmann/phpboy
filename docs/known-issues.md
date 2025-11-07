# PHPBoy Known Issues

This document tracks known compatibility issues, bugs, and limitations.

**Last Updated:** 2025-11-07
**PHPBoy Version:** Step 13 (Test ROM Integration)

## Critical Issues

### CPU-001: AF/Flags Register Synchronization
- **Severity:** Critical
- **Status:** ✅ **FIXED**
- **Affected Components:** CPU, FlagRegister, InstructionSet
- **Symptoms:** Many arithmetic and logic operations appeared to set flags incorrectly
- **Impact:** Game logic failures, incorrect branching, ALU operations
- **Test ROMs Affected:**
  - 01-special.gb (DAA with POP AF)
  - 04-op r,imm.gb (immediate operations)
  - 05-op rp.gb (16-bit operations)
  - 07-jr,jp,call,ret,rst.gb (conditional jumps/calls)
  - 09-op r,r.gb (register operations)
  - 10-bit ops.gb (BIT instruction)
- **Root Cause:**
  - CPU maintained two separate flag storages: AF register and FlagRegister object
  - POP AF updated AF register but not FlagRegister
  - Subsequent instructions read stale flags from FlagRegister
- **Fix Applied:**
  - Linked FlagRegister to AF Register16 via constructor parameter
  - Added syncToAF() called after every flag modification
  - Added syncFromAF() called after POP AF
- **Result:** 10/11 Blargg CPU tests now passing (90.9%)
- **Commit:** Current session

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

### CPU-003: Memory Operations Performance
- **Severity:** High
- **Status:** Open
- **Affected Components:** CPU, Performance
- **Symptoms:** Test ROM 11-op a,(hl).gb times out after 30 seconds with no output
- **Impact:** Test never completes, suggests performance issue
- **Test ROMs Affected:**
  - 11-op a,(hl).gb (timeout)
- **Suspected Cause:**
  - Possible performance degradation from flag synchronization overhead
  - May be infinite loop in test ROM initialization
  - Could be (HL) addressing mode performance issue
- **Next Steps:** Profile execution, optimize sync mechanism if needed

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
- **Status:** ✅ **FIXED**
- **Affected Components:** CPU, FlagRegister
- **Symptoms:** BIT n,r instruction appeared to not set flags correctly
- **Impact:** Bit testing operations
- **Test ROMs Affected:**
  - 10-bit ops.gb (NOW PASSING)
- **Root Cause:** AF/Flags synchronization bug (see CPU-001)
- **Fix Applied:** AF/Flags synchronization fix resolved this issue
- **Result:** All BIT instruction flag tests now pass

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
| Blargg CPU Instructions | 90.9% (10/11) | ✅ Excellent | +8 tests (was 18.2%) |
| Blargg Instruction Timing | 0% (0/1) | ❌ Below target | Timing issues only |
| **Overall** | **83.3% (10/12)** | ✅ **Excellent** | **+66.6% (was 16.7%)** |

### Step 13 Requirements

Per PLAN.md Step 13, we need:
- ✅ Test ROM harness implemented
- ✅ 90%+ Blargg CPU tests passing (currently 90.9% - **REQUIREMENT MET!**)
- ⏳ 10+ Mooneye tests run (not started)
- ⏳ Acid tests run (not started)
- ⏳ 3+ commercial ROMs playable 5min (ready to test now that CPU is 90%+ accurate)

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
