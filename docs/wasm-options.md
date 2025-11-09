# PHPBoy WebAssembly Options Evaluation

This document evaluates different approaches for running PHPBoy in the browser via WebAssembly.

## Overview

PHPBoy is written in PHP 8.5, which presents unique challenges for browser deployment. This evaluation explores three main approaches for bringing PHP code to the browser.

## Evaluated Options

### 1. php-wasm (seanmorris/php-wasm) ⭐ **SELECTED**

**Description**: Compiles the Zend Engine (PHP runtime) to WebAssembly, allowing native PHP execution in the browser.

**Pros**:
- ✅ Runs actual PHP code without transpilation
- ✅ Full PHP 8.x feature support (enums, readonly, typed properties, etc.)
- ✅ Active development and community
- ✅ Good documentation and examples
- ✅ Supports Composer dependencies
- ✅ Virtual filesystem for file operations
- ✅ Direct DOM manipulation via Vrzno bridge
- ✅ SQLite and database support built-in
- ✅ Works with major frameworks (Laravel, Drupal, etc.)

**Cons**:
- ⚠️ Large runtime size (~10-15 MB WASM file)
- ⚠️ Slower startup time (loading PHP runtime)
- ⚠️ Performance overhead vs. native JavaScript
- ⚠️ Limited debugging tools in browser
- ⚠️ Some PHP extensions unavailable

**Performance**:
- Startup: 2-5 seconds (loading WASM runtime)
- Execution: ~2-3x slower than native PHP
- Memory: ~20-50 MB baseline
- Frame rate: 40-60 FPS expected

**Integration Complexity**: ⭐⭐⭐ Medium
- Requires virtual filesystem setup
- JavaScript bridge for I/O
- Async API for PHP calls

**Verdict**: **Best choice** for PHPBoy. Preserves PHP code as-is, supports all language features, and provides acceptable performance.

---

### 2. Uniter (PHP-to-JavaScript Transpiler)

**Description**: Transpiles PHP to JavaScript, which then runs natively in the browser.

**Pros**:
- ✅ Fast execution (native JavaScript performance)
- ✅ Smaller bundle size than WASM
- ✅ Better debugging (JavaScript source maps)
- ✅ No runtime loading overhead

**Cons**:
- ❌ Limited PHP version support (PHP 5.x-7.x)
- ❌ No PHP 8.x features (enums, readonly, match, etc.)
- ❌ Incomplete language coverage
- ❌ Not actively maintained
- ❌ Would require rewriting large portions of PHPBoy
- ❌ Type system incompatibilities

**Performance**:
- Startup: Fast (<1 second)
- Execution: Near-native JavaScript speed
- Memory: Lower than WASM
- Frame rate: 60 FPS expected

**Integration Complexity**: ⭐⭐⭐⭐⭐ Very High
- Requires significant code changes
- PHP 8.5 features must be rewritten
- Enum, readonly, typed properties unsupported

**Verdict**: **Not suitable**. PHPBoy heavily uses PHP 8.5 features that Uniter doesn't support. Would require complete rewrite.

---

### 3. wasmerio/php-wasm (Alternative WASM Implementation)

**Description**: Another WASM-based PHP runtime, part of the Wasmer ecosystem.

**Pros**:
- ✅ Runs native PHP code
- ✅ Part of larger Wasmer ecosystem
- ✅ Good performance

**Cons**:
- ⚠️ Less documentation than seanmorris/php-wasm
- ⚠️ Smaller community
- ⚠️ Fewer examples and tutorials
- ⚠️ Less frequent updates
- ⚠️ Limited browser-specific features

**Performance**: Similar to seanmorris/php-wasm

**Integration Complexity**: ⭐⭐⭐⭐ Medium-High
- Less documentation for browser integration
- Fewer community resources
- May require more custom bridge code

**Verdict**: **Not selected**. While viable, seanmorris/php-wasm has better documentation and community support.

---

### 4. Custom Rewrite in JavaScript/TypeScript

**Description**: Rewrite the entire emulator in JavaScript or TypeScript.

**Pros**:
- ✅ Best performance (native browser code)
- ✅ Smallest bundle size
- ✅ Best debugging experience
- ✅ No runtime loading
- ✅ Direct DOM/Canvas/WebAudio access

**Cons**:
- ❌ Requires complete rewrite (~10,000+ lines of code)
- ❌ Loses PHP implementation (main goal of PHPBoy)
- ❌ Months of development time
- ❌ Would need to maintain two codebases

**Verdict**: **Not suitable**. Defeats the purpose of PHPBoy, which is to showcase PHP for emulation.

---

## Decision Matrix

| Option | PHP 8.5 Support | Performance | Bundle Size | Dev Effort | Maintenance |
|--------|----------------|-------------|-------------|------------|-------------|
| **php-wasm** | ✅ Full | ⭐⭐⭐ Good | Large | Low | Low |
| **Uniter** | ❌ None | ⭐⭐⭐⭐⭐ Excellent | Small | Very High | High |
| **wasmerio** | ✅ Full | ⭐⭐⭐ Good | Large | Medium | Medium |
| **JS Rewrite** | ❌ N/A | ⭐⭐⭐⭐⭐ Excellent | Small | Very High | High |

## Selected Approach: php-wasm (seanmorris/php-wasm)

### Rationale

**php-wasm** is the clear choice for PHPBoy because:

1. **Preserves PHP Code**: The primary goal of PHPBoy is demonstrating PHP for game emulation. php-wasm allows us to use the exact same PHP code in the browser as in CLI.

2. **PHP 8.5 Support**: PHPBoy extensively uses PHP 8.5 features:
   - Enums for opcodes and state machines
   - Readonly properties
   - Typed class constants
   - Property hooks
   - Strict types
   - Match expressions

3. **Minimal Changes**: Only I/O layer needs adaptation (framebuffer, audio, input). Core emulation logic remains unchanged.

4. **Active Development**: Regular updates and responsive maintainer.

5. **Good Documentation**: Clear examples and community resources.

6. **Acceptable Performance**: While slower than native JS, it's fast enough for Game Boy emulation (60 FPS achievable).

### Implementation Strategy

1. **I/O Abstraction**: Create WASM-specific implementations of:
   - `WasmFramebuffer` - buffers pixels for Canvas
   - `WasmAudioSink` - buffers audio for WebAudio
   - `WasmInput` - receives keyboard/touch input

2. **JavaScript Bridge**: Create `phpboy.js` to:
   - Initialize php-wasm runtime
   - Load ROM into virtual filesystem
   - Drive emulation loop via `requestAnimationFrame`
   - Transfer pixel data to Canvas
   - Transfer audio data to WebAudio
   - Pass input events to PHP

3. **Build System**: Create `make build-wasm` target to:
   - Copy web files (HTML, CSS, JS)
   - Copy PHP source and dependencies
   - Generate distribution directory

### Performance Optimization

To achieve 60 FPS:

1. **Minimize PHP-JS calls**: Batch operations where possible
2. **Use typed arrays**: For efficient pixel/audio data transfer
3. **Optimize emulation loop**: Call PHP once per frame, not per instruction
4. **Pre-warm caches**: Initialize instruction set upfront

### Trade-offs Accepted

- **Larger Bundle**: ~15 MB for PHP runtime (acceptable for modern web)
- **Startup Time**: 2-5 seconds to load PHP (one-time cost)
- **Performance**: ~40-60 FPS vs. 60+ FPS in CLI (acceptable for web demo)

## Proof of Concept

A minimal "Hello World" proof-of-concept was created to validate the approach:

```javascript
import { PhpWeb } from 'php-wasm/PhpWeb.mjs';

const php = new PhpWeb();
await php.binary; // Wait for runtime

const result = await php.run(`<?php
    echo "Hello from PHP in the browser!";
`);

console.log(result.body); // "Hello from PHP in the browser!"
```

✅ **Success**: PHP runtime loads and executes successfully in Chrome, Firefox, and Safari.

## Conclusion

**php-wasm** is the optimal choice for bringing PHPBoy to the browser. It preserves the PHP implementation, supports all language features, and provides acceptable performance for Game Boy emulation. The development effort is minimal (mainly I/O adapters), and the result is a true demonstration of PHP running a game emulator in the browser.

## References

- [php-wasm GitHub](https://github.com/seanmorris/php-wasm)
- [php-wasm Documentation](https://php-wasm.com/)
- [Uniter GitHub](https://github.com/asmblah/uniter)
- [Wasmer PHP](https://wasmer.io/wasmer/php)
- [WebAssembly](https://webassembly.org/)
