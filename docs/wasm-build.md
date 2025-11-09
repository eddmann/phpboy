# PHPBoy WebAssembly Build Guide

This guide explains how to build and deploy PHPBoy for the browser using PHP-WASM.

## Overview

PHPBoy uses [php-wasm](https://github.com/seanmorris/php-wasm) to run the entire PHP-based emulator in the browser via WebAssembly. This eliminates the need for a backend server and allows the emulator to run entirely client-side.

## Architecture

The WebAssembly build consists of several components:

1. **PHP Source Code**: The core emulator logic written in PHP 8.5
2. **WASM I/O Adapters**: PHP classes that bridge between the emulator and JavaScript
3. **JavaScript Bridge**: Manages the PHP-WASM runtime and handles browser interactions
4. **Web UI**: HTML/CSS/JavaScript interface for loading ROMs and controlling the emulator

### WASM I/O Adapters

Three key interfaces are implemented for WASM compatibility:

- **WasmFramebuffer**: Buffers pixel data for Canvas rendering
- **WasmAudioSink**: Buffers audio samples for Web Audio API
- **WasmInput**: Receives keyboard/touch input from JavaScript

## Prerequisites

Before building for WASM, ensure you have:

1. PHP 8.4+ with Composer (for development)
2. Docker (recommended for consistent builds)
3. Node.js and npm (for serving the build)
4. Python 3 (alternative for serving)

## Building for WebAssembly

### Step 1: Install Dependencies

First, install PHP dependencies via Composer:

```bash
make install
```

### Step 2: Build WASM Distribution

Build the WASM distribution:

```bash
make build-wasm
```

This command:
- Creates a `dist/` directory
- Copies all web files (HTML, CSS, JavaScript)
- Copies PHP source code to `dist/php/src/`
- Copies Composer dependencies to `dist/php/vendor/`

### Step 3: Serve Locally

Serve the build locally for testing:

```bash
make serve-wasm
```

This starts an HTTP server on `http://localhost:8080`.

Alternatively, using npm:

```bash
npm install
npm run serve
```

Or using Python directly:

```bash
cd dist
python3 -m http.server 8080
```

### Step 4: Test in Browser

1. Open `http://localhost:8080` in your browser
2. Click "Choose ROM File" and select a .gb or .gbc ROM
3. The emulator should load and start running

## Build Output Structure

```
dist/
├── index.html              # Main HTML page
├── css/
│   └── styles.css          # Styling
├── js/
│   └── phpboy.js           # JavaScript bridge
├── phpboy-wasm.php         # PHP entry point
└── php/
    ├── src/                # PHP source code
    │   ├── Emulator.php
    │   ├── Cpu/
    │   ├── Ppu/
    │   ├── Apu/
    │   └── Frontend/
    │       └── Wasm/       # WASM adapters
    ├── vendor/             # Composer dependencies
    └── composer.json
```

## How It Works

### 1. PHP-WASM Integration

PHPBoy uses the `php-wasm` library to run PHP in the browser:

```javascript
import { PhpWeb } from 'php-wasm/PhpWeb.mjs';

const php = new PhpWeb();
await php.binary; // Wait for PHP runtime to load
```

### 2. Virtual Filesystem

ROMs are loaded into PHP's virtual filesystem:

```javascript
const romData = new Uint8Array(arrayBuffer);
await php.writeFile('/rom.gb', romData);
```

### 3. Emulation Loop

JavaScript drives the emulation loop:

```javascript
// Execute one frame
const result = await php.run(`<?php
    $emulator->step();
    $pixels = $framebuffer->getPixelsRGBA();
    $audio = $audioSink->getSamplesFlat();
    echo json_encode(['pixels' => $pixels, 'audio' => $audio]);
`);

// Render to canvas
const data = JSON.parse(result.body);
renderFrame(data.pixels);
queueAudio(data.audio);
```

### 4. Input Handling

Keyboard events are passed to PHP:

```javascript
document.addEventListener('keydown', async (e) => {
    await php.run(`<?php
        $input->setButtonState(${buttonCode}, true);
    `);
});
```

## Performance Considerations

### Frame Rate

- Target: 60 FPS (59.7 Hz for Game Boy accuracy)
- Actual performance depends on:
  - Browser (Chrome/Firefox/Safari)
  - Device CPU speed
  - PHP-WASM overhead

### Optimizations

1. **Use `requestAnimationFrame`** for smooth rendering
2. **Buffer audio samples** to prevent underruns
3. **Minimize PHP-JS bridge calls** by batching operations
4. **Use typed arrays** for pixel/audio data transfer

## Deployment

### Static Hosting

The `dist/` directory is fully static and can be deployed to:

- **GitHub Pages**
- **Netlify**
- **Vercel**
- **AWS S3 + CloudFront**
- Any static file host

### CORS Considerations

PHP-WASM loads WebAssembly files that require proper CORS headers:

```
Access-Control-Allow-Origin: *
Cross-Origin-Embedder-Policy: require-corp
Cross-Origin-Opener-Policy: same-origin
```

Most static hosts handle this automatically, but verify if you encounter loading issues.

## Troubleshooting

### PHP-WASM Fails to Load

**Problem**: "Failed to fetch" error when loading PHP runtime

**Solution**: Ensure you're serving from an HTTP server, not `file://`. Use `make serve-wasm` or similar.

### ROM Loading Errors

**Problem**: "ROM file not found" error

**Solution**: Ensure the ROM is being written to `/rom.gb` in the virtual filesystem:

```javascript
await php.writeFile('/rom.gb', romData);
```

### Poor Performance

**Problem**: Emulator runs slowly, below 60 FPS

**Solutions**:
- Test in different browsers (Chrome typically fastest)
- Reduce emulation speed multiplier
- Disable audio temporarily
- Check browser console for errors

### Audio Issues

**Problem**: No audio or crackling/stuttering

**Note**: Audio implementation is basic and may require additional buffering. Full Web Audio API integration is complex and beyond initial implementation.

## Browser Compatibility

Tested browsers:

- ✅ Chrome 90+ (best performance)
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+

WebAssembly and ES Modules are required.

## Limitations

Current WASM implementation has these limitations:

1. **Audio**: Basic implementation, may have quality issues
2. **Save Files**: Not persisted between sessions (TODO: localStorage)
3. **Performance**: Slower than native PHP CLI
4. **File I/O**: No direct filesystem access (uses virtual FS)

## Next Steps

Potential improvements:

- [ ] Implement persistent save files with localStorage
- [ ] Add save state functionality
- [ ] Improve audio buffering with AudioWorklet
- [ ] Add mobile touch controls
- [ ] Optimize PHP-JS bridge for better performance
- [ ] Add WebGL rendering for better scaling
- [ ] Implement multiplayer via WebRTC

## Resources

- [php-wasm GitHub](https://github.com/seanmorris/php-wasm)
- [php-wasm Documentation](https://php-wasm.com/)
- [WebAssembly](https://webassembly.org/)
- [Web Audio API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Audio_API)
- [Canvas API](https://developer.mozilla.org/en-US/docs/Web/API/Canvas_API)
