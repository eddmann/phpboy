# PHP to WebAssembly Options for PHPBoy

**Research Date:** November 9, 2025
**Project:** PHPBoy - Game Boy Color Emulator
**Goal:** Run PHPBoy emulator in browser without server-side PHP

---

## Executive Summary

After evaluating three approaches for running PHP in the browser, **WordPress Playground's php-wasm approach** (using Emscripten to compile PHP interpreter to WebAssembly) is the recommended solution for PHPBoy. This provides a full PHP 8.3+ runtime with minimal modifications to existing code.

**Recommendation:** Use **php-wasm** (WordPress Playground / seanmorris approach)

---

## Option 1: WordPress Playground / php-wasm (Emscripten)

### Overview
Compiles the official PHP interpreter (Zend Engine) to WebAssembly using Emscripten. This is the most mature and battle-tested approach, used by WordPress.org for WordPress Playground.

### Projects
- **WordPress Playground**: https://github.com/WordPress/wordpress-playground
- **seanmorris/php-wasm**: https://github.com/seanmorris/php-wasm (active, PHP 8.3.11 & 8.4.1 support)

### How It Works
1. Compiles PHP interpreter source code using Emscripten
2. Produces `.wasm` binary (e.g., `php_8_3.wasm`)
3. JavaScript wrapper provides `php.run()` API
4. VRZNO extension enables PHP-to-JavaScript interop (DOM access, etc.)

### Technical Details

#### Build Process
- Uses Emscripten toolchain
- Requires minimal PHP source patches
- Configuration variables forced for browser environment
- Standard PHP build process otherwise unchanged

#### I/O Handling
- **Filesystem**: Virtual filesystem in memory (MEMFS via Emscripten)
- **Stdin/Stdout**: Captured via JavaScript APIs
- **File uploads**: Supported via `writeFile()` method
- **Networking**: Custom solutions (see below)

#### Networking
- **Node.js**: WebSocket-to-TCP proxy + Asyncify + PHP internal patches
- **Browser**:
  - Fast path: `wp_safe_remote_get()` → `fetch()`
  - Slow path: TLS parsing → `fetch()` translation
- PHPBoy doesn't need networking (ROM loading only)

#### Version Support
- PHP 8.0, 8.1, 8.2, 8.3, 8.4 (as of 2024)
- Multiple PHP versions as separate `.wasm` files

#### Browser Integration
```html
<script type="text/php">
<?php
  vrzno_run('alert', ['Hello from PHP!']);
?>
</script>
```

```javascript
php.addEventListener('ready', () => {
  php.run('<?php echo "Hello!";').then(retVal => {
    console.log('PHP returned:', retVal);
  });
});
```

### Pros
✅ **Full PHP compatibility** - runs real PHP 8.3+ code
✅ **Mature & production-ready** - powers WordPress Playground
✅ **Active development** - recent 2024 updates
✅ **Minimal code changes** - existing PHPBoy code works as-is
✅ **Good performance** - 3x speedup with Opcache enabled
✅ **Browser APIs available** - VRZNO extension for DOM/Canvas/WebAudio access
✅ **Multiple PHP versions** - easy version switching

### Cons
❌ **Large binary size** - PHP interpreter is ~5-10 MB (gzipped: ~2-3 MB)
❌ **Build complexity** - requires Emscripten toolchain setup
❌ **Synchronous PHP in async JS** - requires careful event loop handling
❌ **Memory overhead** - full PHP runtime in browser memory

### Performance Characteristics
- WordPress without Opcache: ~620ms per page render
- WordPress with Opcache: ~205ms per page render (3x speedup)
- PHPBoy target: 60 FPS = ~16ms per frame (should be achievable)

### PHPBoy Integration Effort
**Estimated: 2-4 days**

1. **Setup** (4-6 hours)
   - Install/configure Emscripten
   - Build php-wasm from source or use prebuilt binaries
   - Create `make build-wasm` target

2. **I/O Abstraction** (2-4 hours)
   - `WasmFramebuffer.php` - buffer pixels for JavaScript
   - `WasmAudioSink.php` - buffer audio samples for WebAudio
   - `WasmInput.php` - receive input from JavaScript

3. **JavaScript Bridge** (6-8 hours)
   - `web/js/phpboy.js` - WASM/PHP interaction layer
   - Frame loop: call PHP `emulator->step()`, retrieve framebuffer/audio
   - Canvas rendering (160×144 → 640×576)
   - WebAudio integration
   - Keyboard event handling

4. **Web UI** (4-6 hours)
   - `web/index.html` - file picker, canvas, controls
   - ROM loading from file input
   - Play/Pause, Reset, Speed control

5. **Testing & Optimization** (4-6 hours)
   - Verify 60 FPS performance
   - Cross-browser testing (Chrome, Firefox, Safari)
   - Debug any timing/synchronization issues

---

## Option 2: Wasmer (WebAssembly Runtime for PHP)

### Overview
Wasmer provides a WebAssembly runtime **for PHP**, allowing PHP code to execute WASM modules. This is the **opposite direction** of what we need.

### Projects
- **wasmerio/wasmer-php**: https://github.com/wasmerio/wasmerio-php

### How It Works
1. PHP extension runs on server/CLI
2. PHP code can instantiate and call WASM modules
3. Used for running compiled languages (Rust, C, etc.) from PHP

### Pros
✅ Fast WASM execution from PHP
✅ Mature project

### Cons
❌ **Wrong direction** - runs WASM in PHP, not PHP in browser
❌ Not applicable to PHPBoy browser deployment

### Verdict
**Not suitable for PHPBoy.** This runs WASM modules from PHP, but we need to run PHP in the browser.

---

## Option 3: Uniter (PHP to JavaScript Transpiler)

### Overview
Transpiles PHP code to JavaScript at runtime or build time. Reimplements PHP runtime in JavaScript.

### Projects
- **uniter/phptojs**: https://github.com/uniter/phptojs
- **asmblah/uniter**: https://github.com/asmblah/uniter

### How It Works
1. **phptoast**: Parses PHP code → AST
2. **phptojs**: Transpiles AST → JavaScript
3. **phpcore**: Minimal runtime (basic functionality)
4. **phpruntime**: Extended runtime (builtin functions like `array_merge()`)

### Transpilation Approach
```php
// PHP
function greet($name) {
    return "Hello, $name!";
}
```

```javascript
// Generated JavaScript
function greet(name) {
    return "Hello, " + name + "!";
}
```

### Pros
✅ **Small bundle size** - only transpiled code + runtime
✅ **Native JavaScript performance** - no interpreter overhead
✅ **Direct DOM access** - JavaScript can call browser APIs naturally

### Cons
❌ **Incomplete PHP compatibility** - subset of PHP features
❌ **PHP 7.0 target** - no PHP 8.x features
❌ **No opcache/JIT** - loses PHP optimization benefits
❌ **Requires extensive code review** - ensure all PHPBoy features supported
❌ **Runtime library gaps** - may lack needed functions
❌ **Type system differences** - PHP 8.x types may not transpile correctly
❌ **Last major activity: June 2024** - unclear maintenance status

### PHPBoy Compatibility Concerns
- Uses PHP 8.3 enums (e.g., `Button`, `InterruptType`, `PpuMode`)
- Uses PHP 8.x type declarations extensively
- Uses PHP 8.0+ union types, nullable types
- Uniter targets PHP 7.0 - compatibility unknown

### PHPBoy Integration Effort
**Estimated: 1-3 weeks** (high uncertainty)

1. **Compatibility Audit** (8-16 hours)
   - Test transpilation of all PHPBoy source files
   - Identify unsupported features (enums, readonly, union types, etc.)
   - Check runtime library for missing functions (bitwise ops, etc.)

2. **Code Refactoring** (variable, 20-60 hours)
   - Replace unsupported PHP 8.x features
   - Work around runtime library gaps
   - Test each refactored component

3. **Build System** (4-6 hours)
   - Integrate Uniter transpilation into build
   - Create `make build-js` target

4. **JavaScript Bridge** (simpler than php-wasm, 4-6 hours)
   - Direct function calls to transpiled code
   - No WASM boundary to cross

5. **Testing & Debugging** (10-20 hours)
   - Debug transpilation edge cases
   - Fix runtime behavior differences
   - Verify emulator accuracy maintained

### Verdict
**Not recommended.** High risk of compatibility issues, extensive code refactoring required, and no clear advantage over php-wasm for PHPBoy's use case.

---

## Comparison Matrix

| Criterion | WordPress Playground / php-wasm | Wasmer | Uniter |
|-----------|--------------------------------|--------|--------|
| **Direction** | PHP → Browser ✅ | WASM → PHP ❌ | PHP → Browser ✅ |
| **PHP Version** | 8.3, 8.4 ✅ | N/A | 7.0 ⚠️ |
| **Compatibility** | 100% PHP ✅ | N/A | ~70% PHP ⚠️ |
| **Bundle Size** | Large (2-3 MB) ⚠️ | N/A | Small (500 KB) ✅ |
| **Performance** | Good (Opcache) ✅ | N/A | Excellent ✅ |
| **Code Changes** | Minimal ✅ | N/A | Extensive ❌ |
| **Maturity** | Production ✅ | N/A | Experimental ⚠️ |
| **Maintenance** | Active 2024 ✅ | Active | June 2024 ⚠️ |
| **Integration Effort** | 2-4 days ✅ | N/A | 1-3 weeks ❌ |
| **Risk Level** | Low ✅ | N/A | High ❌ |

---

## Recommendation: Use php-wasm (WordPress Playground Approach)

### Rationale

1. **Full PHP 8.3 Compatibility**: PHPBoy code runs unchanged
2. **Production-Ready**: Powers WordPress.org Playground (millions of users)
3. **Active Development**: Recent 2024 updates, PHP 8.4 support
4. **Predictable Performance**: Opcache brings 3x speedups
5. **Low Risk**: Proven technology, clear documentation
6. **Reasonable Timeline**: 2-4 days implementation vs. 1-3 weeks for Uniter

### Trade-offs Accepted

- **Bundle size**: 2-3 MB gzipped is acceptable for an emulator (ROMs are 32 KB - 8 MB)
- **Build complexity**: One-time Emscripten setup, then automated
- **Memory overhead**: Modern browsers handle 50-100 MB PHP runtime easily

### Implementation Path

#### Phase 1: Proof of Concept (Day 1)
- Install seanmorris/php-wasm via npm or use prebuilt binaries
- Create minimal `web/poc.html` that runs "Hello World" PHP
- Verify browser compatibility (Chrome, Firefox, Safari)

#### Phase 2: I/O Abstraction (Day 1-2)
- Implement `WasmFramebuffer.php` (expose pixel buffer)
- Implement `WasmAudioSink.php` (expose audio buffer)
- Implement `WasmInput.php` (receive button state from JS)

#### Phase 3: JavaScript Bridge (Day 2-3)
- Create `web/js/phpboy.js` WASM interaction layer
- Frame loop: `php.run('Emulator::step()')` @ 60 FPS
- Canvas rendering via ImageData
- WebAudio sample queueing
- Keyboard event mapping

#### Phase 4: Web UI (Day 3-4)
- Build `web/index.html` with file picker, canvas, controls
- ROM loading: FileReader API → PHP memory
- UI controls: Play/Pause, Reset, Speed (1x, 2x, 4x), Volume
- FPS counter display

#### Phase 5: Testing & Polish (Day 4)
- Load Tetris, verify gameplay
- Cross-browser testing
- Performance profiling (target: 60 FPS sustained)
- Documentation: `docs/wasm-build.md`, `docs/browser-usage.md`

---

## Alternative Considered: Compile-to-Native-JS

A fourth option not deeply explored: compile PHP bytecode to optimized JavaScript using a custom toolchain. This would require:

- PHP → Opcache bytecode
- Bytecode → SSA/IR
- IR → optimized JavaScript
- Custom runtime for PHP semantics

**Effort**: 3-6 months of compiler development.
**Verdict**: Not feasible for Step 15 timeline.

---

## References

### WordPress Playground
- GitHub: https://github.com/WordPress/wordpress-playground
- Architecture: https://wordpress.github.io/wordpress-playground/developers/architecture/wasm-php-overview/
- Blog: https://developer.wordpress.org/news/2024/04/introduction-to-playground-running-wordpress-in-the-browser/

### seanmorris/php-wasm
- GitHub: https://github.com/seanmorris/php-wasm
- Demo: https://php-wasm.seanmorr.is/
- Changelog: https://github.com/seanmorris/php-wasm/blob/master/CHANGELOG.md

### Uniter
- GitHub: https://github.com/asmblah/uniter
- Website: https://phptojs.com/
- npm: https://www.npmjs.com/package/uniter

### Wasmer
- Blog: https://wasmer.io/posts/running-php-blazingly-fast-at-the-edge-with-wasm
- Article: https://wasmlabs.dev/articles/compiling-php-to-webassembly/

### Additional Resources
- Emscripten: https://emscripten.org/
- WebAssembly: https://webassembly.org/
- PHP-WASM on Fermyon: https://developer.fermyon.com/wasm-languages/php

---

## Next Steps (Step 15 Implementation)

1. ✅ **Research completed** - this document
2. ⏭️ Set up php-wasm build environment
3. ⏭️ Create proof-of-concept
4. ⏭️ Implement I/O abstractions
5. ⏭️ Build JavaScript bridge
6. ⏭️ Create web UI
7. ⏭️ Test and optimize
8. ⏭️ Document and commit

**Estimated total effort for Step 15**: 2-4 days of focused development.

---

**Document Status**: ✅ Complete
**Decision**: Proceed with **php-wasm (WordPress Playground approach)**
**Next Action**: Set up Emscripten and php-wasm build environment
