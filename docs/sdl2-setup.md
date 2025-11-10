# SDL2 Native Frontend Setup

This guide covers setting up the SDL2 PHP extension for native desktop rendering in PHPBoy.

## Overview

The SDL2 frontend provides **true native rendering** using hardware acceleration through SDL2 (Simple DirectMedia Layer). This approach offers:

- ✅ Native desktop window with hardware-accelerated graphics
- ✅ Direct GPU rendering (60+ fps easily achievable)
- ✅ Keyboard and joystick input support
- ✅ Cross-platform (Linux, macOS, Windows)
- ✅ No browser/Electron wrapper needed
- ✅ Perfect for emulator development

## Prerequisites

- PHP 8.1+ with development headers
- SDL2 library (>= 2.0)
- C compiler and build tools
- Unix-like system (Linux, macOS, BSD)

## Installation

### Step 1: Install SDL2 Development Libraries

#### Ubuntu/Debian
```bash
sudo apt-get update
sudo apt-get install libsdl2-dev
```

#### macOS
```bash
brew install sdl2
```

#### Fedora/RHEL
```bash
sudo dnf install SDL2-devel
```

### Step 2: Install PHP SDL Extension

#### Option A: From PECL (Recommended)

```bash
pecl install sdl-beta
```

Then add to your `php.ini`:
```ini
extension=sdl.so
```

Verify installation:
```bash
php -m | grep sdl
```

#### Option B: Build from Source

```bash
# Clone the repository
git clone https://github.com/Ponup/php-sdl.git
cd php-sdl

# Build the extension
phpize
./configure --with-sdl
make
make test

# Install
sudo make install
```

Add to `php.ini`:
```ini
extension=sdl.so
```

### Step 3: Verify Installation

Create a test file `test-sdl.php`:

```php
<?php

if (!extension_loaded('sdl')) {
    die("SDL extension not loaded!\n");
}

echo "SDL version: " . SDL_GetVersion() . "\n";

SDL_Init(SDL_INIT_VIDEO);

$window = SDL_CreateWindow(
    "SDL Test",
    SDL_WINDOWPOS_CENTERED,
    SDL_WINDOWPOS_CENTERED,
    640,
    480,
    SDL_WINDOW_SHOWN
);

if ($window) {
    echo "✓ SDL2 working! Window created successfully.\n";
    sleep(2);
    SDL_DestroyWindow($window);
} else {
    echo "✗ Failed to create window: " . SDL_GetError() . "\n";
}

SDL_Quit();
```

Run:
```bash
php test-sdl.php
```

You should see a window appear briefly.

## Docker Support

For Docker environments, add SDL2 to your Dockerfile:

```dockerfile
FROM php:8.4-cli

# Install SDL2 dependencies
RUN apt-get update && apt-get install -y \
    libsdl2-dev \
    libsdl2-2.0-0 \
    && rm -rf /var/lib/apt/lists/*

# Install PHP SDL extension
RUN pecl install sdl-beta \
    && docker-php-ext-enable sdl

# Install Composer dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

COPY . .

CMD ["php", "bin/phpboy.php"]
```

**Note:** Running GUI applications in Docker requires X11 forwarding or similar display mechanisms.

## Running PHPBoy with SDL2

```bash
# Make sure SDL extension is loaded
php -m | grep sdl

# Run with SDL2 frontend
php bin/phpboy.php --frontend=sdl path/to/rom.gb
```

Or using Make:
```bash
make run-sdl ROM=path/to/rom.gb
```

## Troubleshooting

### SDL extension not found
```bash
# Check if extension is compiled
php -m | grep sdl

# Check php.ini location
php --ini

# Verify extension file exists
ls $(php-config --extension-dir)/sdl.so
```

### "undefined symbol" errors during build
Make sure you have SDL2 development headers:
```bash
# Ubuntu/Debian
dpkg -l | grep libsdl2-dev

# macOS
brew list sdl2
```

### Window not appearing
- Ensure `DISPLAY` environment variable is set (Linux)
- Check X11 is running
- Try running other SDL2 applications to verify SDL2 works

### "SDL_Init failed" errors
Check SDL2 video subsystem:
```bash
# Test SDL2 directly
sdl2-config --version
```

## Performance Notes

- SDL2 uses hardware acceleration by default (GPU rendering)
- Texture streaming is efficient for emulator framebuffers
- VSync can be enabled for smooth 60fps output
- Rendering 160x144 @ 60fps is trivial for modern GPUs

## API Documentation

For detailed SDL2 PHP API documentation, refer to:
- Official SDL2 C documentation (API is 1:1 with PHP bindings)
- https://wiki.libsdl.org/SDL2/FrontPage
- Examples in `php-sdl/examples/` directory

## Next Steps

- See [SDL2 Frontend Usage](sdl2-usage.md) for PHPBoy-specific usage
- Check `src/Frontend/Sdl/` for implementation details
- Review examples in the php-sdl repository
