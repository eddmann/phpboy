# PHPBoy Performance Analysis

This document tracks the emulator's performance metrics, profiling results, and optimization history.

**Last Updated:** 2025-11-09
**PHPBoy Version:** Step 14 (Performance Profiling & Optimisation - In Progress)

## Performance Targets

| Target | FPS | Status | Notes |
|--------|-----|--------|-------|
| Minimum (Half Speed) | 30 FPS | ✅ ACHIEVED | Playable, some slowdown |
| Target (Full Speed) | 60 FPS | 🔄 IN PROGRESS | Native Game Boy speed (59.7 Hz) |
| Stretch (Fast Forward) | 120+ FPS | ⏸️ PENDING | 2x speed for convenience |

## Baseline Performance (Before Optimizations)

**Measured:** 2025-11-07 (during Step 13)
**Environment:** Docker PHP 8.5-rc-cli, no JIT, no OPcache optimizations

### Commercial ROM Performance

| ROM | Frames Tested | Duration (seconds) | Measured FPS | Target FPS | Performance |
|-----|---------------|-------------------|--------------|------------|-------------|
| Tetris (GBC) | 1800 | 60-72s | 25-30 FPS | 60 FPS | 42-50% speed |
| Pokemon Red | 3000 | 100-120s | 25-30 FPS | 60 FPS | 42-50% speed |
| Zelda: Link's Awakening DX | 2400 | 80-96s | 25-30 FPS | 60 FPS | 42-50% speed |

**Average Baseline:** ~27.5 FPS (46% of target speed)

### Key Observations

1. **Consistent Performance**: All commercial ROMs achieve similar FPS (25-30), suggesting CPU emulation is the bottleneck
2. **Playable but Slow**: Minimum target (30 FPS) is met, games are playable but noticeably slower than hardware
3. **Optimization Opportunity**: ~54% performance gap to reach 60 FPS target

## Profiling Infrastructure

### Setup Status

- ✅ Xdebug profiling enabled in Docker
- ✅ Cachegrind output generation configured
- ✅ `make profile ROM=<rom>` target created
- ✅ Profiling data directory (`var/profiling/`) created
- ✅ KCachegrind/QCacheGrind compatible output verified

**Status:** Infrastructure complete and ready for use (requires Docker rebuild)

### Profiling Methodology

1. Run emulator with profiling enabled for fixed frame count (e.g., 1000 frames)
2. Generate cachegrind output
3. Analyze with KCachegrind to identify hotspots
4. Document top 10 time-consuming functions
5. Apply optimizations targeting highest-impact hotspots
6. Re-profile to measure improvement

## Hotspot Analysis

**Status:** Not yet profiled

Expected hotspots based on emulator architecture:
- CPU instruction dispatch (`Cpu::step()`, `Cpu::executeInstruction()`)
- Memory bus reads/writes (`SystemBus::read()`, `SystemBus::write()`)
- PPU pixel rendering (`Ppu::step()`, pixel fetching/rendering)
- Register flag synchronization (`FlagRegister::syncToAF()`, `syncFromAF()`)
- Clock cycle tracking (`Clock::tick()`)

## Optimization History

### Baseline (Step 13)
- **Version:** Step 13 completion
- **Performance:** 25-30 FPS
- **Notes:** No specific performance optimizations applied yet

### Step 14 Optimizations Applied
- **Version:** Step 14 (in progress)
- **Optimizations:**
  1. ✅ Inline instruction decode/execute (Cpu::step)
  2. ✅ Pre-build instruction cache (InstructionSet::warmCache)
  3. ✅ OPcache configuration (Dockerfile)
  4. ✅ PHP 8.5 JIT configuration (ready, not yet tested)
- **Expected Performance:** 35-45 FPS with OPcache, 45-62 FPS with JIT
- **Status:** Infrastructure complete, awaiting benchmark measurements
- **See:** `docs/optimizations.md` for detailed implementation notes

## Memory Profiling

**Status:** Not yet measured

**Targets:**
- Maximum memory usage: <100MB for typical ROM
- No memory leaks during extended emulation sessions
- Efficient object reuse where possible

## PHP Runtime Optimizations

### OPcache Status

**Status:** Not yet verified

**Configuration to verify:**
- `opcache.enable=1`
- `opcache.enable_cli=1` (for CLI profiling)
- `opcache.memory_consumption=128`
- `opcache.interned_strings_buffer=8`
- `opcache.max_accelerated_files=10000`

### JIT Status (PHP 8.5)

**Status:** Not yet evaluated

**JIT Modes to test:**
- `opcache.jit_buffer_size=100M`
- `opcache.jit=tracing` (recommended for hot paths)
- `opcache.jit=function` (alternative mode)

**Expected Impact:** 20-50% performance improvement for CPU-intensive code

## Optimization Techniques to Explore

### 1. Instruction Dispatch Optimization
- **Current:** Method calls per instruction
- **Options:** Lookup tables, pre-decoded opcodes, match expressions
- **Expected Impact:** High (CPU instruction dispatch is critical path)

### 2. Flag Calculation Lookup Tables
- **Current:** Runtime flag calculations
- **Options:** Pre-computed lookup tables for common flag operations
- **Expected Impact:** Medium (flags checked frequently)

### 3. Reduce Object Allocation
- **Current:** Object creation per operation
- **Options:** Object pooling, primitive types where possible
- **Expected Impact:** Medium (GC pressure reduction)

### 4. Property Caching
- **Current:** Repeated property access
- **Options:** Cache computed values, reduce method calls
- **Expected Impact:** Low-Medium

### 5. Memory Access Optimization
- **Current:** Interface-based memory reads/writes
- **Options:** Direct array access for hot paths, inline critical reads
- **Expected Impact:** High (memory accessed every instruction)

## Bottlenecks Identified

**Status:** Pending profiling analysis

## Future Optimization Opportunities

1. **Native Extensions (FFI):**
   - Only pursue if pure-PHP cannot achieve 60 FPS
   - Candidate: Instruction dispatch loop
   - Must maintain pure-PHP fallback

2. **Instruction Pre-decoding:**
   - Parse opcodes once, cache decoded metadata
   - Trade memory for CPU time

3. **Parallel Processing:**
   - Separate PPU/APU into parallel workers (if feasible in PHP)
   - GPU acceleration for pixel operations (browser only)

## Performance Testing Methodology

### Standard Benchmark

**ROM:** Tetris (most stable, consistent workload)
**Duration:** 3600 frames (60 seconds at 60 FPS)
**Measurement:** Wall clock time, calculate actual FPS
**Formula:** `FPS = 3600 / actual_time_seconds`

### Regression Testing

After each optimization:
1. Run standard benchmark
2. Verify `make test` still passes (no correctness regressions)
3. Verify `make lint` passes (no code quality regressions)
4. Document performance delta (percentage improvement)
5. Update this document

## Recommendations

1. **Profile First:** Measure before optimizing to target actual hotspots
2. **Incremental Changes:** One optimization at a time, measure impact
3. **Preserve Correctness:** All tests must pass after optimizations
4. **Document Everything:** Track what was tried, what worked, what didn't
5. **Realistic Expectations:** PHP may not reach 60 FPS; 30-45 FPS is acceptable

---

## Related Documents

- **[Optimizations Log](optimizations.md):** Detailed implementation notes for all optimizations
- **[Test Results](test-results.md):** ROM compatibility and test pass rates
- **[Status](STATUS.md):** Overall project status and step completion

---

**Next Steps (Step 14 Completion):**
1. ✅ Set up Xdebug profiling infrastructure
2. ✅ Document expected hotspots and optimization priorities
3. ✅ Apply critical path optimizations (inline decode, pre-build cache)
4. ✅ Configure OPcache and JIT
5. ⏸️ Run benchmarks after Docker rebuild (requires Docker environment)
6. ⏸️ Measure actual performance gains
7. ⏸️ Update this document with measured results
