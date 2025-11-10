# PHPBoy - Game Boy Color Emulator

A readable, well-architected Game Boy Color (GBC) emulator written in PHP 8.5 with multiple frontend options: native SDL2 desktop, CLI terminal, and browser via WebAssembly.

## Features

- **Modern PHP 8.5 RC**: Leverages the latest PHP 8.5 release candidate features including strict types, readonly properties, enums, typed class constants, and property hooks
- **Multiple Frontends**:
  - **SDL2 Native Desktop**: Hardware-accelerated rendering with true native performance ⭐ **NEW!**
  - **Browser (WebAssembly)**: Runs in the browser via php-wasm - no backend required!
  - **CLI Terminal**: ANSI color rendering in your terminal
- **Fully Dockerized Development**: All PHP/Composer/testing tools run exclusively in Docker containers for consistency
- **Comprehensive Testing**: PHPUnit 10 for unit and integration tests
- **Static Analysis**: PHPStan at maximum level (9) for type safety
- **Modular Architecture**: Clean separation of concerns with dedicated namespaces for CPU, PPU, APU, Bus, and Frontend

## Requirements

- Docker
- Docker Compose
- Make (for convenient task automation)

**Important**: All PHP, Composer, PHPUnit, and PHPStan commands must run through Docker. Never run these tools directly on the host machine.

## Getting Started

### Initial Setup

1. Clone the repository:
```bash
git clone <repository-url>
cd phpboy
```

2. Build the Docker image:
```bash
make setup
```

3. Install PHP dependencies:
```bash
make install
```

### Development Workflow

All development tasks are managed through the Makefile and run inside Docker containers. **Never run PHP, Composer, PHPUnit, or PHPStan directly on the host machine.**

#### Available Commands

- `make help` - Show available commands
- `make setup` - Build Docker image with PHP 8.5 RC4
- `make rebuild` - Rebuild Docker image from scratch (no cache)
- `make install` - Install Composer dependencies in Docker
- `make test` - Run PHPUnit tests in Docker
- `make lint` - Run PHPStan static analysis in Docker
- `make shell` - Open bash shell in Docker container
- `make run ROM=path/to/rom.gb` - Run emulator with specified ROM in Docker
- `make build-wasm` - Build WebAssembly version for browser
- `make serve-wasm` - Serve WASM build locally on port 8080
- `make clean` - Remove vendor directory and composer.lock
- `make clean-docker` - Remove Docker containers and images

#### Running Tests

```bash
make test
```

#### Static Analysis

```bash
make lint
```

#### Opening a Shell

For debugging or manual operations:
```bash
make shell
```

### Running with SDL2 Native Frontend

PHPBoy supports true native desktop rendering using SDL2 for hardware-accelerated, low-latency gameplay.

#### Prerequisites

1. Install SDL2 development libraries:
   ```bash
   # Ubuntu/Debian
   sudo apt-get install libsdl2-dev

   # macOS
   brew install sdl2
   ```

2. Install SDL2 PHP extension:
   ```bash
   sudo pecl install sdl-beta
   echo "extension=sdl.so" | sudo tee -a $(php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||")
   ```

3. Verify installation:
   ```bash
   make check-sdl
   ```

#### Running a ROM

```bash
# Run with SDL2 frontend (on host machine)
make run-sdl-host ROM=path/to/rom.gb

# Or directly with PHP
php bin/phpboy.php path/to/rom.gb --frontend=sdl
```

**Features**:
- ✅ Hardware-accelerated rendering (GPU-based)
- ✅ VSync support for smooth 60fps
- ✅ Native desktop window
- ✅ Low-latency keyboard input
- ✅ Pixel-perfect integer scaling
- ✅ Cross-platform (Linux, macOS, Windows)

**Default Controls**:
- Arrow Keys: D-pad
- Z or A: A button
- X or S: B button
- Enter: Start
- Right Shift: Select

**Documentation**:
- [SDL2 Setup Guide](docs/sdl2-setup.md) - Installation instructions
- [SDL2 Usage Guide](docs/sdl2-usage.md) - Usage and customization

### Running in the Browser

PHPBoy can run entirely in the browser via WebAssembly using [php-wasm](https://github.com/seanmorris/php-wasm).

#### Build for Browser

1. Build the WASM distribution:
```bash
make build-wasm
```

2. Serve locally:
```bash
make serve-wasm
```

3. Open `http://localhost:8080` in your browser

4. Load a ROM file and play!

**Features**:
- ✅ Full emulation in the browser
- ✅ No backend server required
- ✅ Keyboard controls
- ✅ Speed control
- ✅ Pause/Resume
- ✅ Works offline after first load

**Browser Requirements**:
- Chrome 90+, Firefox 88+, Safari 14+, or Edge 90+
- WebAssembly support required

**Documentation**:
- [WASM Build Guide](docs/wasm-build.md) - How to build and deploy
- [Browser Usage Guide](docs/browser-usage.md) - How to use in browser
- [WASM Options Evaluation](docs/wasm-options.md) - Technical decisions

## Project Structure

```
phpboy/
├── bin/                    # CLI entry point
├── docs/                   # Documentation
│   ├── research.md        # Game Boy hardware research
│   ├── sdl2-setup.md      # SDL2 native frontend setup
│   ├── sdl2-usage.md      # SDL2 usage guide
│   ├── wasm-build.md      # WebAssembly build guide
│   ├── browser-usage.md   # Browser usage guide
│   └── wasm-options.md    # WASM implementation options
├── src/                   # Source code
│   ├── Apu/              # Audio Processing Unit
│   ├── Bus/              # Memory bus
│   ├── Cartridge/        # ROM/MBC handling
│   ├── Cpu/              # CPU emulation
│   ├── Frontend/         # Multiple frontend implementations
│   │   ├── Cli/         # CLI terminal frontend
│   │   ├── Sdl/         # SDL2 native desktop frontend
│   │   └── Wasm/        # WebAssembly browser frontend
│   ├── Ppu/              # Pixel Processing Unit
│   └── Support/          # Utilities and helpers
├── tests/                # Test suite
│   ├── Integration/      # Integration tests
│   └── Unit/            # Unit tests
├── third_party/          # External resources
│   ├── references/       # Technical documentation
│   └── roms/            # Test ROMs
├── web/                  # Browser frontend
│   ├── index.html       # Main page
│   ├── css/             # Stylesheets
│   ├── js/              # JavaScript bridge
│   └── phpboy-wasm.php  # PHP entry point
├── composer.json         # PHP dependencies
├── package.json         # npm dependencies (for php-wasm)
├── Dockerfile           # Docker image definition
├── docker-compose.yml   # Docker services
├── Makefile            # Task automation
├── phpstan.neon        # PHPStan configuration
├── phpunit.xml         # PHPUnit configuration
└── PLAN.md             # Development roadmap
```

## Development Philosophy

PHPBoy follows a step-by-step development approach, implementing each Game Boy subsystem incrementally. Each step includes:

- Historical context about the Game Boy hardware
- Clear implementation tasks
- Comprehensive tests
- Documentation updates
- Verification criteria

See `PLAN.md` for the complete development roadmap.

## Testing

PHPBoy uses industry-standard test ROMs to verify accuracy:

- **Blargg's test suite**: CPU instruction validation
- **Mooneye test suite**: Hardware behavior verification
- **dmg-acid2/cgb-acid2**: PPU rendering accuracy

## License

MIT License

## Contributing

Contributions are welcome! Please ensure:

1. All code passes PHPStan level 9
2. Tests are included for new features
3. Follow conventional commits format
4. Use the Makefile for all operations
