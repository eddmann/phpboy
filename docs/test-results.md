# PHPBoy Test ROM Results

This document tracks the emulator's compatibility with various test ROM suites.

**Last Updated:** 2025-11-07
**PHPBoy Version:** Step 13 (Test ROM Integration - In Progress)

## Summary

| Test Suite | Pass | Fail | Total | Pass Rate |
|------------|------|------|-------|-----------|
| Blargg CPU Instructions | 5 | 6 | 11 | 45.5% |
| Blargg Instruction Timing | 0 | 1 | 1 | 0% |
| **Overall** | **5** | **7** | **12** | **41.7%** |

**Progress from initial state:**
- Initial: 16.7% (2/12 tests passing)
- Current: 41.7% (5/12 tests passing)
- Improvement: +25% (+3 tests)

## Blargg CPU Instruction Tests

Blargg's CPU instruction tests verify the correctness of CPU instruction implementation.

| Test ROM | Status | Duration | Notes |
|----------|--------|----------|-------|
| 01-special.gb | ✅ PASS | ~4.5s | **FIXED** - DAA instruction now working correctly |
| 02-interrupts.gb | ✅ PASS | ~4s | All interrupt handling tests pass |
| 03-op sp,hl.gb | ✅ PASS | ~4s | **FIXED** - ADD SP,e8 and LD HL,SP+e8 now correct |
| 04-op r,imm.gb | ❌ FAIL | 4.76s | Immediate arithmetic operations - subtle flag issues remain |
| 05-op rp.gb | ❌ FAIL | 6.32s | 16-bit register pair operations - ADD HL,rr half-carry |
| 06-ld r,r.gb | ✅ PASS | ~4s | All 8-bit register loads pass |
| 07-jr,jp,call,ret,rst.gb | ❌ FAIL | 1.47s | Jump/call instructions - timing or flag issues |
| 08-misc instrs.gb | ✅ PASS | ~4s | Miscellaneous instructions pass |
| 09-op r,r.gb | ❌ FAIL | 18.14s | Register-to-register operations - flag handling issues |
| 10-bit ops.gb | ❌ FAIL | 25.13s | BIT instruction - flag handling issues |
| 11-op a,(hl).gb | ❌ FAIL | 29.87s | **IMPROVED** - No longer times out, now reaching DAA test |

### Blargg Instruction Timing

| Test ROM | Status | Duration | Notes |
|----------|--------|----------|-------|
| instr_timing.gb | ❌ FAIL | 2.09s | Instruction cycle timing inaccuracies across multiple instructions |

## Detailed Failure Analysis

### 01-special.gb - DAA Instruction
- **Issue:** Decimal Adjust Accumulator (DAA) instruction not implemented correctly
- **Impact:** BCD arithmetic operations will fail
- **Priority:** High (common in games using score systems)

### 03-op sp,hl.gb - Stack Pointer Operations
- **Issue:** ADD SP,e8 and LD HL,SP+e8 flag handling incorrect
- **Impact:** Stack manipulation and frame pointer operations
- **Priority:** High (critical for function calls and local variables)

### 04-op r,imm.gb - Immediate Operations
- **Issue:** Arithmetic operations with immediate values have flag issues
- **Impact:** Common operations like ADD A,n, SUB A,n, etc.
- **Priority:** Critical (extremely common operations)

### 05-op rp.gb - 16-bit Operations
- **Issue:** ADD HL,rr flag handling (specifically half-carry and carry)
- **Impact:** 16-bit arithmetic operations
- **Priority:** High (used for address calculations)

### 07-jr,jp,call,ret,rst.gb - Control Flow
- **Issue:** Conditional jumps/calls not checking flags correctly
- **Impact:** All conditional branching
- **Priority:** Critical (breaks game logic)

### 09-op r,r.gb - Register Operations
- **Issue:** Register-to-register arithmetic has flag handling issues
- **Impact:** Most ALU operations
- **Priority:** Critical (extremely common)

### 10-bit ops.gb - Bit Test
- **Issue:** BIT instruction not setting flags correctly
- **Impact:** Bit testing operations
- **Priority:** High (common for status checks)

### 11-op a,(hl).gb - Memory Operations
- **Issue:** Timeout suggests infinite loop or missing instruction
- **Impact:** Memory indirect operations
- **Priority:** Critical (very common addressing mode)

### instr_timing.gb - Cycle Timing
- **Issue:** Many instructions have incorrect cycle counts
- **Examples:**
  - JR (relative jump): Off by 1 cycle
  - Conditional returns/calls: Off by 1-3 cycles
  - CB-prefixed bit operations: Off by 1 cycle
- **Impact:** Timing-sensitive code (PPU synchronization, audio)
- **Priority:** Medium (functional but may cause glitches)

## Common Patterns

The test failures reveal several systematic issues:

1. **Flag Handling:** Most failures involve incorrect flag (Z, N, H, C) computation
   - Half-carry flag (H) seems particularly problematic
   - Carry flag (C) handling in 16-bit operations

2. **Instruction Timing:** Off-by-one cycle errors in many instructions
   - Conditional branches don't account for taken/not-taken timing difference
   - Memory operations may have incorrect cycle counts

3. **ALU Operations:** Many arithmetic/logic operations have subtle bugs
   - Immediate value operations
   - Register-to-register operations
   - 16-bit arithmetic

## Next Steps

To improve compatibility, focus on:

1. **Fix Flag Computation** (Priority: Critical)
   - Review all ALU operations for correct Z, N, H, C flag setting
   - Special attention to half-carry flag in both 8-bit and 16-bit ops
   - Verify flag behavior against Pan Docs and other emulators

2. **Fix DAA Instruction** (Priority: High)
   - Implement proper BCD adjustment algorithm
   - Test with Blargg 01-special.gb

3. **Fix SP Operations** (Priority: High)
   - ADD SP,e8 flag handling
   - LD HL,SP+e8 flag handling

4. **Investigate Timeout** (Priority: High)
   - Debug 11-op a,(hl).gb to find infinite loop cause
   - Check for missing or incorrect (HL) operations

5. **Improve Instruction Timing** (Priority: Medium)
   - Add proper cycle counting for conditional branches
   - Verify CB-prefixed instruction timings
   - Add timing tests to prevent regressions

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
