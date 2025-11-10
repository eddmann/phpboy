# SDL2 Frontend Usage Guide

This guide covers using the SDL2 native frontend for PHPBoy emulation.

## Overview

The SDL2 frontend provides a native desktop application experience with:

- **Hardware-accelerated rendering** - Direct GPU access for smooth 60fps
- **Native window** - True desktop application, no browser required
- **Low latency input** - Direct keyboard access
- **VSync support** - Tear-free rendering
- **Cross-platform** - Works on Linux, macOS, and Windows

## Prerequisites

Before using the SDL2 frontend, ensure you have:

1. SDL2 PHP extension installed (see [SDL2 Setup Guide](sdl2-setup.md))
2. PHPBoy dependencies installed (`make install`)

## Quick Start

### 1. Verify SDL2 Installation

```bash
make check-sdl
```

This will verify the SDL2 extension is loaded and show the version.

### 2. Test SDL2

```bash
make test-sdl
```

A test window should appear briefly, confirming SDL2 is working.

### 3. Run a ROM

```bash
make run-sdl-host ROM=path/to/your/rom.gb
```

Or directly with PHP:

```bash
php bin/phpboy.php path/to/rom.gb --frontend=sdl
```

## Command-Line Options

### Frontend Selection

```bash
# SDL2 native frontend (hardware accelerated)
php bin/phpboy.php rom.gb --frontend=sdl

# CLI terminal frontend (ANSI colors)
php bin/phpboy.php rom.gb --frontend=cli

# Headless mode (no display)
php bin/phpboy.php rom.gb --headless
```

### SDL2-Specific Options

```bash
# Set window scale (1-8, default 4)
php bin/phpboy.php rom.gb --frontend=sdl --scale=3

# Disable VSync (for testing/benchmarking)
php bin/phpboy.php rom.gb --frontend=sdl --no-vsync

# Custom window title
php bin/phpboy.php rom.gb --frontend=sdl --title="My Game"
```

## Keyboard Controls

### Default Mapping

| Game Boy Button | Keyboard Keys |
|----------------|---------------|
| **D-Pad Up** | Up Arrow |
| **D-Pad Down** | Down Arrow |
| **D-Pad Left** | Left Arrow |
| **D-Pad Right** | Right Arrow |
| **A Button** | Z or A |
| **B Button** | X or S |
| **Start** | Enter/Return |
| **Select** | Right Shift or Space |

### Viewing Current Mappings

The key mappings are displayed when the emulator starts, or you can check them programmatically:

```php
$input = new \Gb\Frontend\Sdl\SdlInput();
$input->printKeyMappings();
```

### Custom Key Mappings

You can customize key mappings in your code:

```php
use Gb\Frontend\Sdl\SdlInput;
use Gb\Input\Button;

$input = new SdlInput();

// Map different keys
$input->setKeyMapping(SDL_SCANCODE_W, Button::Up);
$input->setKeyMapping(SDL_SCANCODE_S, Button::Down);
$input->setKeyMapping(SDL_SCANCODE_A, Button::Left);
$input->setKeyMapping(SDL_SCANCODE_D, Button::Right);

// Action buttons on number keys
$input->setKeyMapping(SDL_SCANCODE_1, Button::A);
$input->setKeyMapping(SDL_SCANCODE_2, Button::B);
```

## Performance

### Frame Rate

The SDL2 frontend targets 60fps with VSync enabled by default. This matches the Game Boy's native refresh rate (59.73 Hz).

To check actual performance:

```bash
# Run with performance stats
php bin/phpboy.php rom.gb --frontend=sdl --stats
```

### Benchmarking

For performance testing without rendering overhead:

```bash
# Benchmark with headless mode
make benchmark ROM=rom.gb FRAMES=3600

# Compare SDL2 vs CLI frontend
php bin/phpboy.php rom.gb --frontend=sdl --frames=1000 --benchmark
php bin/phpboy.php rom.gb --frontend=cli --frames=1000 --benchmark
```

### Optimization Tips

1. **Enable JIT** - PHP 8.4 JIT can improve performance:
   ```bash
   php -d opcache.jit_buffer_size=100M -d opcache.jit=tracing bin/phpboy.php rom.gb --frontend=sdl
   ```

2. **Use VSync** - Prevents wasted CPU cycles rendering faster than display refresh

3. **Disable debugging** - Remove `--debug` and `--trace` flags in production

## Advanced Usage

### Integration with Existing Code

The SDL2 frontend implements the standard `FramebufferInterface`, making it a drop-in replacement:

```php
use Gb\Frontend\Sdl\SdlRenderer;
use Gb\Frontend\Sdl\SdlInput;
use Gb\Emulator;

// Create SDL2 components
$renderer = new SdlRenderer(
    scale: 4,           // 640x576 window (160x4, 144x4)
    vsync: true,        // Smooth 60fps
    windowTitle: 'PHPBoy - Tetris'
);

$input = new SdlInput();

// Create emulator with SDL2 frontend
$emulator = new Emulator($romPath, $renderer, $input);

// Main loop
while ($renderer->isRunning()) {
    // Poll input and window events
    if (!$renderer->pollEvents()) {
        break;
    }

    // Run emulator frame
    $emulator->stepFrame();
}
```

### Screenshots

Save the current framebuffer to PNG:

```php
$renderer->saveToPng('screenshot.png');
```

Or via command line (if implemented):

```bash
php bin/phpboy.php rom.gb --frontend=sdl --screenshot=output.png
```

### Event Handling

Handle SDL events in your application:

```php
while ($renderer->isRunning()) {
    // Poll events
    $event = new \SDL_Event();
    while (SDL_PollEvent($event)) {
        if ($event->type === SDL_QUIT) {
            break 2;
        }

        if ($event->type === SDL_KEYDOWN) {
            // Handle custom hotkeys
            if ($event->key->keysym->scancode === SDL_SCANCODE_ESCAPE) {
                break 2;
            }

            if ($event->key->keysym->scancode === SDL_SCANCODE_F11) {
                // Toggle fullscreen
            }

            if ($event->key->keysym->scancode === SDL_SCANCODE_F12) {
                // Take screenshot
                $renderer->saveToPng("screenshot_" . time() . ".png");
            }
        }
    }

    $emulator->stepFrame();
}
```

## Troubleshooting

### Window doesn't appear

1. Check SDL2 is installed:
   ```bash
   php -m | grep sdl
   ```

2. Test SDL2 directly:
   ```bash
   make test-sdl
   ```

3. Check display is available:
   ```bash
   echo $DISPLAY  # Should show :0 or similar on Linux
   ```

### Poor performance / Low FPS

1. **Check VSync** - Disable to test raw performance:
   ```bash
   php bin/phpboy.php rom.gb --frontend=sdl --no-vsync
   ```

2. **Enable JIT** - Significant performance boost:
   ```bash
   php -d opcache.jit=tracing bin/phpboy.php rom.gb --frontend=sdl
   ```

3. **Check GPU acceleration**:
   ```bash
   # Should show "accelerated" renderer
   php -r 'SDL_Init(SDL_INIT_VIDEO); $r = SDL_CreateRenderer(SDL_CreateWindow("t", 0, 0, 100, 100, 0), -1, SDL_RENDERER_ACCELERATED); var_dump($r);'
   ```

### Input lag / Unresponsive controls

1. **Disable VSync** temporarily to rule out timing issues
2. **Check polling rate** - Ensure `pollEvents()` is called every frame
3. **Try different key mappings** - Some keyboards have limitations

### "SDL_Init failed" error

1. Check SDL2 library is installed:
   ```bash
   sdl2-config --version
   ```

2. On Linux, ensure video subsystem is available:
   ```bash
   SDL_VIDEODRIVER=x11 php bin/phpboy.php rom.gb --frontend=sdl
   ```

3. On macOS, may need to run from Terminal (not via SSH)

### Building for Distribution

To create a distributable version with SDL2:

1. **Static PHP build** with SDL2 extension compiled in
2. **Bundle SDL2 library** with your application
3. **Create launcher script** that sets library paths

Example launcher (`phpboy.sh`):

```bash
#!/bin/bash
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export LD_LIBRARY_PATH="$SCRIPT_DIR/lib:$LD_LIBRARY_PATH"
"$SCRIPT_DIR/php" "$SCRIPT_DIR/phpboy.phar" "$@"
```

## Comparison with Other Frontends

| Feature | SDL2 | CLI (ANSI) | WASM (Browser) |
|---------|------|------------|----------------|
| **Performance** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Visual Quality** | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Input Latency** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Setup Complexity** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐ |
| **Distribution** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Native Feel** | ⭐⭐⭐⭐⭐ | ⭐ | ⭐⭐⭐ |

## Next Steps

- **Audio support**: Implement SDL2 audio for APU output
- **Joystick support**: Add gamepad/controller input
- **Fullscreen mode**: Toggle fullscreen with F11
- **Save states**: Quick save/load with hotkeys
- **Fast forward**: Hold key to run at 2x-8x speed

## See Also

- [SDL2 Setup Guide](sdl2-setup.md) - Installation instructions
- [Frontend Architecture](frontend-architecture.md) - How frontends work
- [Official SDL2 Documentation](https://wiki.libsdl.org/SDL2/FrontPage)
