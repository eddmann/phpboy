# PHPBoy Test ROM Results

This document tracks the emulator's compatibility with various test ROM suites.

**Last Updated:** 2025-11-07
**PHPBoy Version:** Step 13 (Test ROM Integration - In Progress)

## Summary

| Test Suite | Pass | Fail | Total | Pass Rate |
|------------|------|------|-------|-----------|
| Blargg CPU Instructions | 10 | 1 | 11 | 90.9% |
| Blargg Instruction Timing | 0 | 1 | 1 | 0% |
| **Overall** | **10** | **2** | **12** | **83.3%** |

**Progress from initial state:**
- Initial: 16.7% (2/12 tests passing)
- After DAA/SP fixes: 41.7% (5/12 tests passing)
- After AF/Flags sync fix: 83.3% (10/12 tests passing)
- Total improvement: +66.6% (+8 tests)

## Blargg CPU Instruction Tests

Blargg's CPU instruction tests verify the correctness of CPU instruction implementation.

| Test ROM | Status | Duration | Notes |
|----------|--------|----------|-------|
| 01-special.gb | ✅ PASS | ~4.4s | **FIXED** - DAA and POP AF now working correctly |
| 02-interrupts.gb | ✅ PASS | ~0.7s | All interrupt handling tests pass |
| 03-op sp,hl.gb | ✅ PASS | ~0.7s | **FIXED** - ADD SP,e8 and LD HL,SP+e8 flags correct |
| 04-op r,imm.gb | ✅ PASS | ~0.8s | **FIXED** - Immediate arithmetic operations |
| 05-op rp.gb | ✅ PASS | ~1.0s | **FIXED** - 16-bit register pair operations |
| 06-ld r,r.gb | ✅ PASS | ~0.7s | All 8-bit register loads pass |
| 07-jr,jp,call,ret,rst.gb | ✅ PASS | ~0.6s | **FIXED** - Jump/call/return instructions |
| 08-misc instrs.gb | ✅ PASS | ~0.7s | Miscellaneous instructions pass |
| 09-op r,r.gb | ✅ PASS | ~2.9s | **FIXED** - Register-to-register operations |
| 10-bit ops.gb | ✅ PASS | ~4.2s | **FIXED** - BIT instruction flag handling |
| 11-op a,(hl).gb | ⏱️  TIMEOUT | 30s | Times out - performance issue with (HL) operations |

### Blargg Instruction Timing

| Test ROM | Status | Duration | Notes |
|----------|--------|----------|-------|
| instr_timing.gb | ❌ FAIL | ~1.5s | CB-prefixed BIT instructions have off-by-1 cycle timing |

## Detailed Failure Analysis

### 11-op a,(hl).gb - Memory Operations (TIMEOUT)
- **Issue:** Test times out after 30 seconds with no output
- **Impact:** Memory indirect operations testing incomplete
- **Priority:** Medium (10/11 tests pass, likely performance issue)
- **Notes:** Test never completes, suggesting severe performance degradation or infinite loop
- **Next steps:** Profile execution, check (HL) addressing mode performance

### instr_timing.gb - Cycle Timing
- **Issue:** CB-prefixed BIT instructions report off-by-one cycle counts
- **Examples from test output:**
  - CB 46 (BIT 0,(HL)): Expected 3, got 4 cycles
  - CB 4E (BIT 1,(HL)): Expected 3, got 4 cycles
  - CB 56 (BIT 2,(HL)): Expected 3, got 4 cycles
  - And all other BIT b,(HL) instructions (8 total)
- **Impact:** Timing-sensitive code (PPU synchronization, audio)
- **Priority:** Low (functional but may cause glitches in timing-critical games)
- **Root Cause:** BIT b,(HL) instructions are taking 16 cycles (4 machine cycles) instead of 12 cycles (3 machine cycles)

## Root Cause Analysis

The massive improvement from 41.7% to 90.9% pass rate was achieved by fixing a critical **AF/Flags register synchronization bug**:

### The Bug
The CPU maintained two separate flag storage systems with no synchronization:
1. `$af` (Register16) - the AF register pair
2. `$flags` (FlagRegister) - separate flags object

**Problem:** When `POP AF` loaded flags from memory, it updated the AF register but not the FlagRegister object. Subsequent instructions (like DAA) read flags from the stale FlagRegister, causing widespread test failures.

### The Fix
1. Modified FlagRegister to maintain a reference to the AF Register16
2. Added automatic synchronization: all flag modifications now update AF's low byte
3. Added `syncFromAF()` called after `POP AF` to sync flags from AF register
4. Added `syncToAF()` called after all flag modifications to sync flags to AF register

This single architectural fix resolved flag handling issues across all ALU operations, conditional branches, and bit operations.

## Next Steps

To achieve 100% Blargg CPU test pass rate:

1. **Investigate 11-op a,(hl).gb Timeout** (Priority: High)
   - Profile execution to find performance bottleneck
   - Check if synchronization overhead is causing slowdown
   - May need to optimize flag sync mechanism

2. **Fix CB BIT Timing** (Priority: Low)
   - Adjust BIT b,(HL) instructions from 16 to 12 cycles
   - Verify against Pan Docs cycle counts
   - Simple one-line fix per instruction

## Test Environment

- **Platform:** Linux 4.4.0
- **PHP Version:** 8.4.14
- **PHPUnit Version:** 10.5.58
- **Test Timeout:** 30 seconds
- **Execution Mode:** Headless (no display)

## References

- [Blargg's Test ROMs](https://github.com/retrio/gb-test-roms)
- [Pan Docs - Instruction Set](https://gbdev.io/pandocs/CPU_Instruction_Set.html)
- [Game Boy CPU Manual](http://marc.rawer.de/Gameboy/Docs/GBCPUman.pdf)
