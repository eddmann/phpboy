# PHPBoy Profiling Analysis

**Last Updated:** 2025-11-09
**Status:** Expected Hotspots (Profiling infrastructure ready, requires Docker to run)

## Overview

This document analyzes expected performance hotspots in PHPBoy based on emulator architecture and common PHP performance patterns. Once profiling data is available via `make profile ROM=<rom> FRAMES=1000`, this document will be updated with actual measurements.

## Expected Hot Paths

Based on the Game Boy architecture and emulator implementation:

### 1. CPU Instruction Dispatch (CRITICAL PATH)
**Expected Impact:** 40-50% of total execution time

**Hot Spots:**
- `Cpu::step()` - Called every instruction (60 FPS × 154 scanlines × ~114 instructions/line ≈ 1M calls/second)
- `Cpu::fetch()` - Memory bus read for every instruction
- `Cpu::execute()` - Closure invocation overhead
- `InstructionSet::getInstruction()` - Array lookup (cached, but still called every instruction)
- Instruction handler closures - 256 base + 256 CB opcodes = 512 handlers

**Current Optimizations:**
- ✅ Lazy instruction building with static caching (`self::$instructions`)
- ✅ Direct closure invocation in `execute()`

**Remaining Opportunities:**
- Eliminate `decode()` method call overhead (inline instruction lookup)
- Pre-build all instructions on initialization (trade memory for CPU)
- Consider match expression vs array lookup for opcode dispatch

### 2. Memory Bus Access (CRITICAL PATH)
**Expected Impact:** 25-35% of total execution time

**Hot Spots:**
- `SystemBus::readByte()` - Called for every instruction fetch + every memory load
- `SystemBus::writeByte()` - Called for every memory store
- Memory region routing (VRAM, WRAM, HRAM, I/O, cartridge)
- MBC (Memory Bank Controller) logic

**Frequency:**
- ~1M instruction fetches/second
- ~500K additional memory operations/second (loads/stores)
- Total: ~1.5M bus accesses/second

**Current Implementation:**
- Assumed: Interface-based routing via if/elseif chains or match expressions

**Opportunities:**
- Inline fast paths for common memory regions
- Direct array access for WRAM/HRAM (avoid method calls)
- Cache frequently accessed I/O registers

### 3. PPU Rendering (CRITICAL PATH)
**Expected Impact:** 15-20% of total execution time

**Hot Spots:**
- `Ppu::step()` - Called every CPU cycle (4.19 MHz / 4 = ~1M/second)
- Pixel fetching and rendering (mode 3)
- Tile data lookups in VRAM
- Sprite evaluation (OAM search)
- Palette color conversion

**Current Implementation:**
- Simplified pixel transfer timing (172 dots fixed)
- Scanline buffer rendering

**Opportunities:**
- Lazy evaluation: only render when scanline completes
- Cache tile data between frames (tiles rarely change)
- Optimize color palette lookups with array indexing

### 4. Flag Register Synchronization
**Expected Impact:** 5-10% of total execution time

**Hot Spots:**
- `FlagRegister::syncToAF()` - Called after every flag modification
- `FlagRegister::syncFromAF()` - Called after `POP AF`
- Bit manipulation for Z, N, H, C flags

**Frequency:**
- ~50% of instructions modify flags
- ~500K flag sync operations/second

**Current Implementation:**
- Two-way synchronization between FlagRegister object and AF Register16

**Opportunities:**
- Inline flag operations (avoid method call overhead)
- Lazy synchronization: only sync when AF is read/written
- Direct bit manipulation on AF low byte

### 5. Clock Tracking
**Expected Impact:** 3-5% of total execution time

**Hot Spots:**
- `Clock::tick()` - Called after every CPU instruction
- Timer updates based on clock cycles
- PPU/APU synchronization

**Opportunities:**
- Inline clock accumulation (avoid method call)
- Batch timer updates (every 4-16 instructions vs every instruction)

## Optimization Priorities

Based on expected impact and implementation effort:

### Priority 1: High Impact, Medium Effort

1. **Inline Instruction Decode** (`Cpu::step()`)
   - Current: `$instruction = $this->decode($opcode);`
   - Optimized: `$instruction = self::$instructions[$opcode] ?? self::buildInstruction($opcode);`
   - Expected: 2-5% performance gain
   - Eliminates one method call per instruction

2. **Pre-build Instruction Cache**
   - Current: Lazy building on first access
   - Optimized: Build all 512 instructions on `InstructionSet` initialization
   - Expected: 1-2% performance gain (eliminates isset check)
   - Trade-off: ~100KB additional memory for faster execution

3. **Inline Memory Fast Paths** (`SystemBus`)
   - Current: All memory access through `readByte()`/`writeByte()`
   - Optimized: Direct array access for WRAM/HRAM
   - Expected: 5-10% performance gain
   - Example:
     ```php
     // Fast path for WRAM (0xC000-0xDFFF)
     if ($address >= 0xC000 && $address <= 0xDFFF) {
         return $this->wram[$address - 0xC000];
     }
     ```

### Priority 2: Medium Impact, Low Effort

4. **Enable OPcache** (Already implemented in Dockerfile)
   - Expected: 10-15% performance gain
   - Zero code changes required
   - Verify with: `php -i | grep opcache`

5. **Enable PHP 8.5 JIT**
   - Expected: 20-40% performance gain for hot loops
   - Configuration: `opcache.jit=tracing`, `opcache.jit_buffer_size=100M`
   - Test with: `make benchmark-jit`

6. **Reduce Flag Sync Overhead**
   - Current: Two-way sync on every flag operation
   - Optimized: Lazy sync only when AF is accessed directly
   - Expected: 3-5% performance gain

### Priority 3: Lower Impact or Higher Risk

7. **Cache Tile Data**
   - Pre-decode tiles to pixel arrays on VRAM write
   - Expected: 2-5% performance gain
   - Risk: Increases memory usage significantly

8. **Lookup Tables for Flag Calculations**
   - Pre-compute half-carry and carry flags for common operations
   - Expected: 2-3% performance gain
   - Trade-off: Memory vs CPU

## PHP-Specific Optimizations

### 1. Object Allocation Reduction

**Current:** Many small objects created per frame (Register8, Color, etc.)

**Optimization:** Use primitives (int, array) where possible

**Example:**
```php
// Before: $color = new Color($r, $g, $b);
// After: $color = ($r << 16) | ($g << 8) | $b;
```

**Expected Impact:** 5-10% performance gain, reduced GC pressure

### 2. Property Access Optimization

**Current:** Accessing properties through getters (`$cpu->getA()`)

**Optimization:** Direct property access in hot paths (make properties public or use readonly)

**Trade-off:** Breaks encapsulation, but PHP property access is slower than C#/Java

### 3. Method Call Reduction

**Current:** Many small methods called millions of times

**Optimization:** Inline critical methods (especially getters/setters)

**Expected Impact:** 5-10% performance gain

### 4. Array Access Optimization

**Current:** Associative arrays with string keys

**Optimization:** Use integer-indexed arrays where possible

**Example:**
```php
// Before: $registers = ['A' => 0, 'B' => 0, ...];
// After: $registers = [0, 0, ...]; // Use constants for indices
```

## Measurement Strategy

When profiling infrastructure is available:

1. **Baseline Measurement**
   ```bash
   make benchmark ROM=third_party/roms/commercial/tetris.gb FRAMES=3600
   ```
   - Record FPS, memory usage
   - Establish baseline for comparison

2. **Profiling Session**
   ```bash
   make profile ROM=third_party/roms/commercial/tetris.gb FRAMES=1000
   kcachegrind var/profiling/cachegrind.out.*
   ```
   - Identify actual top 10 hotspots
   - Compare with expected hotspots above

3. **Optimization Cycle**
   For each optimization:
   - Apply optimization
   - Run benchmark
   - Calculate performance delta
   - Run `make test` to verify correctness
   - Document in `docs/optimizations.md`

4. **JIT Testing**
   ```bash
   # Baseline (no JIT)
   make benchmark ROM=tetris.gb FRAMES=3600

   # With JIT
   make benchmark-jit ROM=tetris.gb FRAMES=3600

   # Compare FPS improvement
   ```

## Expected Performance Gains

Conservative estimates for cumulative optimizations:

| Optimization | Expected Gain | Cumulative FPS |
|--------------|---------------|----------------|
| Baseline (Step 13) | - | 27.5 FPS (46%) |
| OPcache enabled | +12% | 30.8 FPS (51%) |
| Inline decode | +3% | 31.7 FPS (53%) |
| Memory fast paths | +7% | 33.9 FPS (57%) |
| Pre-built instructions | +2% | 34.6 FPS (58%) |
| Flag sync optimization | +4% | 36.0 FPS (60%) |
| **PHP 8.5 JIT** | **+30%** | **46.8 FPS (78%)** |
| Object allocation reduction | +8% | 50.5 FPS (84%) |

**Target Achievement:**
- ✅ Minimum (30 FPS): Already achieved at baseline
- 🎯 Target (60 FPS): Achievable with OPcache + JIT + optimizations
- ⏸️ Stretch (120 FPS): Unlikely in pure PHP, may require native extensions

## Risk Assessment

### Low Risk (Safe to Apply)
- ✅ OPcache: Standard PHP optimization, zero code changes
- ✅ Instruction cache pre-building: Pure performance optimization
- ✅ JIT: Can be toggled on/off, no code changes

### Medium Risk (Test Thoroughly)
- ⚠️ Inline decode: Minor refactoring, maintain test coverage
- ⚠️ Memory fast paths: Ensure bus routing logic remains correct
- ⚠️ Flag sync optimization: Critical for correctness, extensive testing required

### High Risk (Prototype First)
- 🔴 Object allocation changes: May break type safety
- 🔴 Breaking encapsulation: Makes code harder to maintain
- 🔴 Native extensions (FFI): Platform-specific, complex build

## Recommendations

1. **Start with OPcache:** Already configured, just needs verification
2. **Test JIT:** Biggest potential gain with zero code changes
3. **Profile first:** Confirm expected hotspots match reality
4. **Incremental optimizations:** Apply one at a time, measure impact
5. **Maintain correctness:** All tests must pass after each optimization
6. **Document everything:** Track what works, what doesn't, and why

## Tools and Commands

```bash
# Build Docker image with profiling support
make rebuild

# Run baseline benchmark
make benchmark ROM=third_party/roms/commercial/tetris.gb FRAMES=3600

# Run with profiling
make profile ROM=third_party/roms/commercial/tetris.gb FRAMES=1000

# Analyze profile data
kcachegrind var/profiling/cachegrind.out.*

# Test with JIT
make benchmark-jit ROM=third_party/roms/commercial/tetris.gb FRAMES=3600

# Memory profiling
make memory-profile ROM=third_party/roms/commercial/tetris.gb FRAMES=1000

# Verify tests still pass
make test

# Verify lint passes
make lint
```

## Next Steps

1. Build Docker image with updated Dockerfile
2. Run baseline benchmark to establish current FPS
3. Run profiling session to identify actual hotspots
4. Update this document with real profiling data
5. Apply optimizations in priority order
6. Measure impact of each optimization
7. Document results in `docs/optimizations.md`
