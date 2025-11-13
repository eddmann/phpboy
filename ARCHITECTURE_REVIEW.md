# PHPBoy Emulator - Comprehensive Architecture Review

**Date:** 2025-11-13
**Reviewer:** Expert PHP Developer
**Codebase:** PHPBoy - Game Boy Color Emulator (PHP 8.4)

---

## Executive Summary

PHPBoy is a well-structured Game Boy Color emulator demonstrating **strong architectural fundamentals**. The codebase shows:

**Strengths:**
- Clean separation of concerns with well-defined component boundaries
- Excellent use of modern PHP 8.4 features (readonly properties, enums, strict typing)
- M-cycle accurate timing architecture
- Comprehensive interface-driven design
- PHPStan Level 9 compliance
- Zero runtime dependencies

**Areas for Improvement:**
- God object pattern in `Emulator` class (687 lines, too many responsibilities)
- Lack of dependency injection container/service locator
- Tight coupling between components during initialization
- Missing architectural patterns (Event System, Service Layer)
- Limited use of value objects
- No clear separation between domain logic and infrastructure

**Overall Assessment:** 8.5/10 - Excellent foundation with room for enterprise-level improvements

---

## 1. Critical Architectural Issues

### 1.1 God Object Anti-Pattern in `Emulator` Class

**Location:** `src/Emulator.php` (687 lines)

**Problem:**
The `Emulator` class violates the Single Responsibility Principle by handling:
- System initialization
- ROM loading
- Configuration management
- State management (pause/resume/stop)
- Speed control
- Display mode configuration
- DMG colorization logic
- Savestate coordination
- Screenshot functionality
- Main emulation loop

**Impact:**
- Difficult to test individual responsibilities
- Hard to modify one aspect without affecting others
- Class becomes a change magnet (every feature touches it)
- Poor cohesion

**Recommendation:**
Refactor into multiple focused classes:

```php
// Proposed structure:
Gb\EmulatorEngine      // Core loop and timing
Gb\SystemBuilder       // System initialization
Gb\RomLoader          // ROM loading logic
Gb\ConfigManager      // Configuration handling
Gb\DisplayModeManager // PPU display mode configuration
Gb\StateManager       // Pause/resume/stop
Gb\SavestateService   // Savestate operations
```

**Example Refactoring:**

```php
<?php
// Current: 687-line monolith
final class Emulator { /* everything */ }

// Proposed: Focused components
final class EmulatorEngine
{
    public function __construct(
        private readonly SystemBus $bus,
        private readonly Cpu $cpu,
        private readonly Ppu $ppu,
        private readonly Apu $apu,
        private readonly Clock $clock,
    ) {}

    public function runFrame(): void
    {
        // Only responsible for executing one frame
    }
}

final class SystemBuilder
{
    public function build(Cartridge $cartridge, EmulatorConfig $config): EmulatedSystem
    {
        // Responsible only for creating and wiring components
        $isCgbMode = $this->determineCgbMode($cartridge, $config);

        // Build all components
        // ...

        return new EmulatedSystem($cpu, $ppu, $apu, $bus, ...);
    }
}

final class RomLoader
{
    public function load(string $path): Cartridge
    {
        // Only responsible for loading ROM files
    }
}
```

**Benefits:**
- Each class has one reason to change
- Easier to test individual responsibilities
- Better code reuse
- Clearer responsibilities

---

### 1.2 Missing Dependency Injection Container

**Problem:**
The `Emulator::initializeSystem()` method manually constructs 15+ dependencies with complex wiring logic:

```php
// Line 120-254: Manual dependency construction
private function initializeSystem(): void
{
    $this->interruptController = new InterruptController();
    $vram = new Vram();
    $wram = new Wram();
    // ... 15 more manual constructions
    $this->cpu = new Cpu($this->bus, $interruptController);
    // ... complex wiring logic
}
```

**Impact:**
- Hard to test (requires full system initialization)
- Difficult to swap implementations
- No control over object lifecycle
- Tight coupling to concrete implementations

**Recommendation:**
Introduce a lightweight DI container or service builder:

```php
<?php
namespace Gb\Container;

final class ServiceContainer
{
    private array $services = [];
    private array $factories = [];

    public function register(string $id, \Closure $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (!isset($this->services[$id])) {
            if (!isset($this->factories[$id])) {
                throw new \RuntimeException("Service not found: {$id}");
            }
            $this->services[$id] = ($this->factories[$id])($this);
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->services[$id]);
    }
}

// Usage:
final class EmulatorServiceProvider
{
    public static function register(ServiceContainer $container, bool $isCgbMode): void
    {
        $container->register('interrupt_controller', fn() => new InterruptController());

        $container->register('vram', fn() => new Vram());

        $container->register('cpu', fn($c) => new Cpu(
            $c->get('bus'),
            $c->get('interrupt_controller')
        ));

        // ... etc
    }
}
```

**Benefits:**
- Easy to swap implementations (testing, different frontends)
- Clear dependency graph
- Single place to manage object creation
- Supports lazy loading

---

### 1.3 Lack of Configuration Value Objects

**Problem:**
Configuration is scattered across primitive types and nullable fields:

```php
private ?string $dmgPalette = null;
private ?string $forcedHardwareMode = null;
private float $speedMultiplier = 1.0;
```

**Recommendation:**
Introduce immutable configuration value objects:

```php
<?php
namespace Gb\Config;

final readonly class EmulatorConfig
{
    public function __construct(
        public HardwareMode $hardwareMode,
        public ?DmgPalette $dmgPalette,
        public SpeedMultiplier $speed,
        public AudioConfig $audio,
        public VideoConfig $video,
    ) {}

    public static function default(): self
    {
        return new self(
            hardwareMode: HardwareMode::Auto,
            dmgPalette: null,
            speed: SpeedMultiplier::normal(),
            audio: AudioConfig::default(),
            video: VideoConfig::default(),
        );
    }
}

enum HardwareMode
{
    case Auto;
    case Dmg;
    case Cgb;
}

final readonly class SpeedMultiplier
{
    private function __construct(public float $value)
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException('Speed must be positive');
        }
    }

    public static function normal(): self { return new self(1.0); }
    public static function fast(): self { return new self(2.0); }
    public static function custom(float $multiplier): self { return new self($multiplier); }
}
```

**Benefits:**
- Type-safe configuration
- Validation at construction
- Immutability prevents accidental changes
- Self-documenting code
- Easy to serialize/deserialize

---

### 1.4 Missing Event System

**Problem:**
Components directly call methods on each other, creating tight coupling:

```php
// CPU directly requests interrupts:
$this->interruptController->requestInterrupt(InterruptType::VBlank);

// Emulator manually polls input:
if ($this->input !== null && $this->joypad !== null) {
    $pressedButtons = $this->input->poll();
    $this->joypad->updateFromInput($pressedButtons);
}
```

**Recommendation:**
Introduce an event dispatcher for decoupling:

```php
<?php
namespace Gb\Event;

interface EventInterface {}

final readonly class VBlankEvent implements EventInterface
{
    public function __construct(public int $frameNumber) {}
}

final readonly class InterruptRequestedEvent implements EventInterface
{
    public function __construct(public InterruptType $type) {}
}

interface EventListenerInterface
{
    public function handle(EventInterface $event): void;
}

final class EventDispatcher
{
    /** @var array<class-string, list<EventListenerInterface>> */
    private array $listeners = [];

    public function subscribe(string $eventClass, EventListenerInterface $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(EventInterface $event): void
    {
        $eventClass = get_class($event);

        if (!isset($this->listeners[$eventClass])) {
            return;
        }

        foreach ($this->listeners[$eventClass] as $listener) {
            $listener->handle($event);
        }
    }
}

// Usage in PPU:
final class Ppu
{
    public function __construct(
        // ... existing deps
        private readonly EventDispatcher $dispatcher,
    ) {}

    private function stepVBlank(): void
    {
        // ...
        if ($this->ly >= self::SCANLINES_PER_FRAME) {
            $this->dispatcher->dispatch(new VBlankEvent($this->frameNumber));
            // ...
        }
    }
}

// Interrupt controller listens:
final class InterruptListener implements EventListenerInterface
{
    public function __construct(private InterruptController $controller) {}

    public function handle(EventInterface $event): void
    {
        if ($event instanceof InterruptRequestedEvent) {
            $this->controller->requestInterrupt($event->type);
        }
    }
}
```

**Benefits:**
- Decouples components (PPU doesn't need to know about InterruptController)
- Easy to add new listeners without modifying existing code
- Supports debugging (log all events)
- Enables plugins/extensions

---

## 2. SOLID Principles Violations

### 2.1 Single Responsibility Principle (SRP)

**Violations Found:**

1. **Emulator class** (already discussed)

2. **Ppu class** (780 lines) - Multiple responsibilities:
   - PPU timing state machine
   - Rendering logic (background, window, sprites)
   - Palette management
   - I/O register handling
   - CGB mode switching
   - Savestate support

**Recommendation:**
Split rendering logic into separate renderer classes:

```php
<?php
// Current: One massive class
final class Ppu { /* 780 lines */ }

// Proposed: Separated concerns
final class Ppu
{
    public function __construct(
        private readonly PpuStateMachine $stateMachine,
        private readonly ScanlineRenderer $renderer,
        private readonly PpuRegisters $registers,
    ) {}

    public function step(int $cycles): void
    {
        $this->stateMachine->step($cycles);
    }
}

final class ScanlineRenderer
{
    public function __construct(
        private readonly BackgroundRenderer $bgRenderer,
        private readonly WindowRenderer $windowRenderer,
        private readonly SpriteRenderer $spriteRenderer,
    ) {}

    public function renderScanline(int $ly, PpuState $state): array
    {
        $buffer = $this->bgRenderer->render($ly, $state);
        $buffer = $this->windowRenderer->render($ly, $state, $buffer);
        $buffer = $this->spriteRenderer->render($ly, $state, $buffer);
        return $buffer;
    }
}

final class BackgroundRenderer
{
    public function render(int $ly, PpuState $state): array
    {
        // Only responsible for rendering background layer
    }
}
```

---

### 2.2 Open/Closed Principle (OCP)

**Violation:**
The `Emulator::configurePpuDisplayMode()` method uses multiple if-statements to determine display mode:

```php
private function configurePpuDisplayMode(bool $isCgbMode): void
{
    if ($this->forcedHardwareMode === 'dmg') {
        // ...
    }
    if ($this->dmgPalette !== null) {
        // ...
    }
    if ($isCgbMode) {
        // ...
    }
    // ...
}
```

**Recommendation:**
Use Strategy Pattern for display mode configuration:

```php
<?php
namespace Gb\Ppu\DisplayMode;

interface DisplayModeStrategy
{
    public function configure(Ppu $ppu, Cartridge $cartridge): void;
}

final class DmgGrayscaleMode implements DisplayModeStrategy
{
    public function configure(Ppu $ppu, Cartridge $cartridge): void
    {
        $ppu->enableDmgCompatibilityMode(false);
        $ppu->enableCgbMode(false);
    }
}

final class DmgColorizedMode implements DisplayModeStrategy
{
    public function __construct(private readonly string $paletteName) {}

    public function configure(Ppu $ppu, Cartridge $cartridge): void
    {
        $colorizer = new DmgColorizer($ppu->getColorPalette());
        $colorizer->colorize($cartridge->getHeader(), $this->paletteName);
        $ppu->enableDmgCompatibilityMode(true);
    }
}

final class CgbNativeMode implements DisplayModeStrategy
{
    public function configure(Ppu $ppu, Cartridge $cartridge): void
    {
        $ppu->enableCgbMode(true);
    }
}

final class DisplayModeFactory
{
    public static function create(
        ?string $forcedMode,
        ?string $dmgPalette,
        bool $isCgbCartridge
    ): DisplayModeStrategy {
        return match(true) {
            $forcedMode === 'dmg' => new DmgGrayscaleMode(),
            $dmgPalette !== null => new DmgColorizedMode($dmgPalette),
            $isCgbCartridge => new CgbNativeMode(),
            default => new DmgAutoMode(),
        };
    }
}
```

---

### 2.3 Dependency Inversion Principle (DIP)

**Violation:**
The `Emulator` class depends on concrete implementations instead of abstractions:

```php
private Clock $clock;
private ?Cartridge $cartridge = null;
private ?Cpu $cpu = null;
// etc.
```

**Recommendation:**
Introduce interfaces for major components:

```php
<?php
namespace Gb\Core;

interface CpuInterface
{
    public function step(): int;
    public function setCycleCallback(\Closure $callback): void;
    // ... essential methods only
}

interface PpuInterface
{
    public function step(int $cycles): void;
    public function enableCgbMode(bool $enabled): void;
}

interface ClockInterface
{
    public function tick(int $cycles): void;
    public function reset(): void;
    public function getElapsedCycles(): int;
}

// Then:
final class Emulator
{
    public function __construct(
        private readonly CpuInterface $cpu,
        private readonly PpuInterface $ppu,
        private readonly ClockInterface $clock,
        // ...
    ) {}
}
```

**Benefits:**
- Easy to create test doubles
- Swap implementations (optimized CPU, debug CPU, etc.)
- Better encapsulation

---

## 3. Design Pattern Recommendations

### 3.1 Factory Pattern for Component Creation

**Current Issue:**
Direct instantiation spreads throughout initialization code.

**Recommendation:**
Centralize object creation in factories:

```php
<?php
namespace Gb\Factory;

final class CpuFactory
{
    public function create(
        BusInterface $bus,
        InterruptController $interruptController
    ): Cpu {
        $cpu = new Cpu($bus, $interruptController);

        // Post-construction configuration
        InstructionSet::warmCache();

        return $cpu;
    }

    public function createWithInitialState(
        BusInterface $bus,
        InterruptController $interruptController,
        CpuState $initialState
    ): Cpu {
        $cpu = $this->create($bus, $interruptController);
        $cpu->setAF($initialState->af);
        $cpu->setBC($initialState->bc);
        // ...
        return $cpu;
    }
}

final class PpuFactory
{
    public function create(
        Vram $vram,
        Oam $oam,
        FramebufferInterface $framebuffer,
        InterruptController $interruptController,
        DisplayModeStrategy $displayMode
    ): Ppu {
        $ppu = new Ppu($vram, $oam, $framebuffer, $interruptController);
        $displayMode->configure($ppu);
        return $ppu;
    }
}
```

---

### 3.2 Builder Pattern for Complex Configuration

**Recommendation:**
Use builder for constructing configured emulator instances:

```php
<?php
namespace Gb;

final class EmulatorBuilder
{
    private ?string $romPath = null;
    private EmulatorConfig $config;
    private ?FramebufferInterface $framebuffer = null;
    private ?InputInterface $input = null;
    private ?AudioSinkInterface $audioSink = null;

    public function __construct()
    {
        $this->config = EmulatorConfig::default();
    }

    public function withRom(string $path): self
    {
        $this->romPath = $path;
        return $this;
    }

    public function withConfig(EmulatorConfig $config): self
    {
        $this->config = $config;
        return $this;
    }

    public function withFramebuffer(FramebufferInterface $framebuffer): self
    {
        $this->framebuffer = $framebuffer;
        return $this;
    }

    public function withInput(InputInterface $input): self
    {
        $this->input = $input;
        return $this;
    }

    public function withAudioSink(AudioSinkInterface $audioSink): self
    {
        $this->audioSink = $audioSink;
        return $this;
    }

    public function build(): Emulator
    {
        if ($this->romPath === null) {
            throw new \RuntimeException('ROM path is required');
        }

        $container = new ServiceContainer();
        EmulatorServiceProvider::register($container, $this->config);

        if ($this->framebuffer !== null) {
            $container->set('framebuffer', $this->framebuffer);
        }

        // ...

        $emulator = $container->get('emulator');
        $emulator->loadRom($this->romPath);

        return $emulator;
    }
}

// Usage:
$emulator = (new EmulatorBuilder())
    ->withRom('/path/to/game.gb')
    ->withConfig($config)
    ->withInput(new CliInput())
    ->withAudioSink(new SoxSink())
    ->build();
```

---

### 3.3 State Pattern for Emulator Lifecycle

**Current Issue:**
Boolean flags for state management:

```php
private bool $running = false;
private bool $paused = false;
```

**Recommendation:**
Use State pattern for clearer lifecycle management:

```php
<?php
namespace Gb\State;

interface EmulatorStateInterface
{
    public function start(EmulatorContext $context): void;
    public function stop(EmulatorContext $context): void;
    public function pause(EmulatorContext $context): void;
    public function resume(EmulatorContext $context): void;
    public function step(EmulatorContext $context): void;
}

final class StoppedState implements EmulatorStateInterface
{
    public function start(EmulatorContext $context): void
    {
        $context->setState(new RunningState());
    }

    public function stop(EmulatorContext $context): void
    {
        // Already stopped
    }

    public function pause(EmulatorContext $context): void
    {
        throw new \RuntimeException('Cannot pause: emulator is stopped');
    }

    public function resume(EmulatorContext $context): void
    {
        throw new \RuntimeException('Cannot resume: emulator is stopped');
    }

    public function step(EmulatorContext $context): void
    {
        // Execute one frame while stopped
        $context->executeFrame();
    }
}

final class RunningState implements EmulatorStateInterface
{
    public function start(EmulatorContext $context): void
    {
        // Already running
    }

    public function stop(EmulatorContext $context): void
    {
        $context->setState(new StoppedState());
    }

    public function pause(EmulatorContext $context): void
    {
        $context->setState(new PausedState());
    }

    public function resume(EmulatorContext $context): void
    {
        // Already running
    }

    public function step(EmulatorContext $context): void
    {
        $context->executeFrame();
    }
}

final class PausedState implements EmulatorStateInterface
{
    public function start(EmulatorContext $context): void
    {
        $context->setState(new RunningState());
    }

    public function stop(EmulatorContext $context): void
    {
        $context->setState(new StoppedState());
    }

    public function pause(EmulatorContext $context): void
    {
        // Already paused
    }

    public function resume(EmulatorContext $context): void
    {
        $context->setState(new RunningState());
    }

    public function step(EmulatorContext $context): void
    {
        // Execute single frame while paused
        $context->executeFrame();
    }
}
```

---

## 4. Code Organization Improvements

### 4.1 Domain-Driven Design Structure

**Current Structure:**
Components are organized by technical layer (Cpu/, Ppu/, Bus/).

**Recommendation:**
Introduce domain-driven organization with clear bounded contexts:

```
src/
├── Core/                    # Core emulation domain
│   ├── Cpu/
│   ├── Ppu/
│   ├── Apu/
│   ├── Bus/
│   └── Timing/
├── Cartridge/               # Cartridge domain
│   ├── Loader/
│   ├── Mbc/
│   ├── Header/
│   └── Save/
├── Input/                   # Input domain
│   ├── Joypad/
│   ├── Driver/
│   └── Mapping/
├── Display/                 # Display domain
│   ├── Framebuffer/
│   ├── Renderer/
│   └── ColorPalette/
├── Audio/                   # Audio domain
│   ├── Channel/
│   ├── Mixer/
│   └── Sink/
├── System/                  # System coordination
│   ├── Emulator.php
│   ├── EmulatorBuilder.php
│   ├── ServiceContainer.php
│   └── Configuration/
├── Frontend/                # Infrastructure layer
│   ├── Cli/
│   ├── Wasm/
│   └── Sdl/
└── Persistence/             # Persistence layer
    ├── Savestate/
    ├── Screenshot/
    └── Recording/
```

---

### 4.2 Separate Domain Logic from Infrastructure

**Current Issue:**
Domain logic mixed with infrastructure concerns:

```php
// Emulator.php - infrastructure concern
public function screenshot(string $path, string $format = 'ppm-binary'): void
{
    match($format) {
        'ppm' => \Gb\Support\Screenshot::savePPM($this->framebuffer, $path),
        // ...
    };
}
```

**Recommendation:**
Separate into layers:

```php
<?php
// Domain layer: Core emulation logic
namespace Gb\Core;

final class EmulatorEngine
{
    public function getFramebuffer(): FramebufferInterface
    {
        return $this->framebuffer;
    }
}

// Application layer: Use cases
namespace Gb\Application;

final class TakeScreenshotUseCase
{
    public function __construct(
        private readonly EmulatorEngine $emulator,
        private readonly ScreenshotRepository $repository,
    ) {}

    public function execute(TakeScreenshotCommand $command): void
    {
        $framebuffer = $this->emulator->getFramebuffer();
        $screenshot = Screenshot::fromFramebuffer($framebuffer);
        $this->repository->save($screenshot, $command->path, $command->format);
    }
}

// Infrastructure layer: File I/O
namespace Gb\Infrastructure\Persistence;

final class FilesystemScreenshotRepository implements ScreenshotRepository
{
    public function save(Screenshot $screenshot, string $path, ScreenshotFormat $format): void
    {
        $data = match($format) {
            ScreenshotFormat::Ppm => $this->encodePpm($screenshot),
            ScreenshotFormat::PpmBinary => $this->encodePpmBinary($screenshot),
            ScreenshotFormat::Text => $this->encodeText($screenshot),
        };

        file_put_contents($path, $data);
    }
}
```

---

## 5. Testing Architecture Improvements

### 5.1 Missing Unit Test Coverage for Complex Logic

**Observation:**
Integration tests exist (Blargg, Mooneye ROMs), but unit tests could be more comprehensive.

**Recommendation:**
Introduce test fixtures and builders for easier testing:

```php
<?php
namespace Tests\Fixtures;

final class CpuBuilder
{
    private int $af = 0x0000;
    private int $bc = 0x0000;
    private int $pc = 0x0100;
    private BusInterface $bus;
    private InterruptController $interruptController;

    public function __construct()
    {
        $this->bus = new MockBus();
        $this->interruptController = new InterruptController();
    }

    public function withAF(int $af): self
    {
        $this->af = $af;
        return $this;
    }

    public function withPC(int $pc): self
    {
        $this->pc = $pc;
        return $this;
    }

    public function withBus(BusInterface $bus): self
    {
        $this->bus = $bus;
        return $this;
    }

    public function build(): Cpu
    {
        $cpu = new Cpu($this->bus, $this->interruptController);
        $cpu->setAF($this->af);
        $cpu->setBC($this->bc);
        $cpu->setPC($this->pc);
        return $cpu;
    }
}

// Usage in tests:
final class CpuTest extends TestCase
{
    public function testInstructionExecution(): void
    {
        $bus = new MockBus([0x00 => 0x3E, 0x01 => 0x42]); // LD A, 42

        $cpu = (new CpuBuilder())
            ->withBus($bus)
            ->withPC(0x0000)
            ->build();

        $cpu->step();

        $this->assertSame(0x42, $cpu->getA());
        $this->assertSame(0x0002, $cpu->getPC()->get());
    }
}
```

---

### 5.2 Property-Based Testing for CPU Instructions

**Recommendation:**
Add property-based tests for instruction correctness:

```php
<?php
namespace Tests\Property;

use PHPUnit\Framework\TestCase;

final class CpuPropertyTest extends TestCase
{
    /**
     * Property: INC r8 followed by DEC r8 should return to original value
     */
    public function testIncDecInvariant(): void
    {
        for ($i = 0; $i < 256; $i++) {
            $cpu = (new CpuBuilder())
                ->withA($i)
                ->withBus($this->createBusWithInstructions([
                    0x3C, // INC A
                    0x3D, // DEC A
                ]))
                ->build();

            $cpu->step(); // INC A
            $cpu->step(); // DEC A

            $this->assertSame($i, $cpu->getA(), "INC/DEC failed for value {$i}");
        }
    }

    /**
     * Property: Any instruction should consume a non-zero number of cycles
     */
    public function testAllInstructionsConsumeCycles(): void
    {
        foreach (range(0x00, 0xFF) as $opcode) {
            $cpu = (new CpuBuilder())
                ->withBus($this->createBusWithInstructions([$opcode]))
                ->build();

            $cycles = $cpu->step();

            $this->assertGreaterThan(0, $cycles, sprintf(
                'Instruction 0x%02X consumed zero cycles',
                $opcode
            ));
        }
    }
}
```

---

## 6. Performance Optimization Recommendations

### 6.1 Reduce Function Call Overhead in Hot Paths

**Current Issue:**
The rendering path has many small function calls:

```php
private function renderScanline(): void
{
    $this->renderBackground();
    $this->renderWindow();
    $this->renderSprites();
}
```

**Recommendation:**
For critical paths, inline hot code or use static analysis to identify bottlenecks:

```php
<?php
// Add performance measurement:
namespace Gb\Support;

final class PerformanceProfiler
{
    private static array $timings = [];

    public static function measure(string $label, \Closure $fn): mixed
    {
        $start = hrtime(true);
        $result = $fn();
        $end = hrtime(true);

        self::$timings[$label] ??= [];
        self::$timings[$label][] = ($end - $start) / 1_000_000; // Convert to ms

        return $result;
    }

    public static function report(): array
    {
        $report = [];
        foreach (self::$timings as $label => $times) {
            $report[$label] = [
                'count' => count($times),
                'total_ms' => array_sum($times),
                'avg_ms' => array_sum($times) / count($times),
                'min_ms' => min($times),
                'max_ms' => max($times),
            ];
        }
        return $report;
    }
}

// Usage:
private function renderScanline(): void
{
    PerformanceProfiler::measure('render_scanline', function() {
        PerformanceProfiler::measure('render_bg', fn() => $this->renderBackground());
        PerformanceProfiler::measure('render_window', fn() => $this->renderWindow());
        PerformanceProfiler::measure('render_sprites', fn() => $this->renderSprites());
    });
}
```

---

### 6.2 Memory Optimization: Object Pooling for Scanline Buffers

**Current Issue:**
Arrays are recreated every scanline:

```php
// Line 247-249 in Ppu.php
$this->scanlineBuffer = array_fill(0, ArrayFramebuffer::WIDTH, Color::fromDmgShade(0));
$this->bgColorBuffer = array_fill(0, ArrayFramebuffer::WIDTH, 0);
$this->bgPriorityBuffer = array_fill(0, ArrayFramebuffer::WIDTH, false);
```

**Recommendation:**
Reuse buffers instead of recreating:

```php
<?php
final class Ppu
{
    private array $scanlineBuffer;
    private array $bgColorBuffer;
    private array $bgPriorityBuffer;

    public function __construct(/* ... */)
    {
        // Pre-allocate buffers once
        $this->scanlineBuffer = array_fill(0, ArrayFramebuffer::WIDTH, Color::fromDmgShade(0));
        $this->bgColorBuffer = array_fill(0, ArrayFramebuffer::WIDTH, 0);
        $this->bgPriorityBuffer = array_fill(0, ArrayFramebuffer::WIDTH, false);
    }

    private function renderScanline(): void
    {
        // Clear buffers (faster than array_fill)
        for ($i = 0; $i < ArrayFramebuffer::WIDTH; $i++) {
            $this->scanlineBuffer[$i] = Color::fromDmgShade(0);
            $this->bgColorBuffer[$i] = 0;
            $this->bgPriorityBuffer[$i] = false;
        }

        // ... render logic
    }
}
```

**Expected Improvement:** ~5-10% reduction in GC pressure

---

### 6.3 Optimize Color Object Creation

**Current Issue:**
Color objects created frequently:

```php
Color::fromDmgShade($shade);
```

**Recommendation:**
Use flyweight pattern to cache color instances:

```php
<?php
namespace Gb\Ppu;

final readonly class Color
{
    private static array $dmgCache = [];

    public static function fromDmgShade(int $shade): self
    {
        return self::$dmgCache[$shade] ??= new self(
            self::DMG_SHADES[$shade][0],
            self::DMG_SHADES[$shade][1],
            self::DMG_SHADES[$shade][2]
        );
    }
}
```

---

## 7. Additional Recommendations

### 7.1 Add Logging Infrastructure

**Recommendation:**
Introduce PSR-3 compatible logger:

```php
<?php
namespace Gb\Logging;

interface LoggerInterface
{
    public function debug(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
}

final class NullLogger implements LoggerInterface
{
    public function debug(string $message, array $context = []): void {}
    public function info(string $message, array $context = []): void {}
    public function warning(string $message, array $context = []): void {}
    public function error(string $message, array $context = []): void {}
}

final class FileLogger implements LoggerInterface
{
    public function __construct(private readonly string $logPath) {}

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    private function log(string $level, string $message, array $context): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        $line = "[{$timestamp}] {$level}: {$message} {$contextStr}\n";
        file_put_contents($this->logPath, $line, FILE_APPEND);
    }
}

// Usage:
final class Cpu
{
    public function __construct(
        private readonly BusInterface $bus,
        private readonly InterruptController $interruptController,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function step(): int
    {
        $opcode = $this->fetch();
        $this->logger->debug('CPU instruction fetch', [
            'pc' => $this->pc->get(),
            'opcode' => sprintf('0x%02X', $opcode),
        ]);
        // ...
    }
}
```

---

### 7.2 Introduce Metrics Collection

**Recommendation:**
Add metrics for monitoring emulator performance:

```php
<?php
namespace Gb\Metrics;

final class EmulatorMetrics
{
    private int $framesRendered = 0;
    private int $instructionsExecuted = 0;
    private float $avgFps = 0.0;
    private array $frameTimings = [];

    public function recordFrame(float $frameTimeMs): void
    {
        $this->framesRendered++;
        $this->frameTimings[] = $frameTimeMs;

        // Keep only last 60 frames for rolling average
        if (count($this->frameTimings) > 60) {
            array_shift($this->frameTimings);
        }

        $this->avgFps = 1000.0 / (array_sum($this->frameTimings) / count($this->frameTimings));
    }

    public function recordInstruction(): void
    {
        $this->instructionsExecuted++;
    }

    public function getReport(): array
    {
        return [
            'frames_rendered' => $this->framesRendered,
            'instructions_executed' => $this->instructionsExecuted,
            'avg_fps' => round($this->avgFps, 2),
            'instructions_per_frame' => $this->framesRendered > 0
                ? (int)($this->instructionsExecuted / $this->framesRendered)
                : 0,
        ];
    }
}
```

---

### 7.3 Add Debug Tooling

**Recommendation:**
Create debugging interfaces for development:

```php
<?php
namespace Gb\Debug;

interface DebuggerInterface
{
    public function setBreakpoint(int $address): void;
    public function step(): void;
    public function continue(): void;
    public function dumpRegisters(): array;
    public function dumpMemory(int $start, int $length): array;
}

final class Debugger implements DebuggerInterface
{
    private array $breakpoints = [];
    private bool $stepping = false;

    public function __construct(
        private readonly Cpu $cpu,
        private readonly BusInterface $bus,
    ) {}

    public function setBreakpoint(int $address): void
    {
        $this->breakpoints[$address] = true;
    }

    public function shouldBreak(): bool
    {
        $pc = $this->cpu->getPC()->get();
        return isset($this->breakpoints[$pc]) || $this->stepping;
    }

    public function dumpRegisters(): array
    {
        return [
            'AF' => sprintf('0x%04X', $this->cpu->getAF()->get()),
            'BC' => sprintf('0x%04X', $this->cpu->getBC()->get()),
            'DE' => sprintf('0x%04X', $this->cpu->getDE()->get()),
            'HL' => sprintf('0x%04X', $this->cpu->getHL()->get()),
            'SP' => sprintf('0x%04X', $this->cpu->getSP()->get()),
            'PC' => sprintf('0x%04X', $this->cpu->getPC()->get()),
            'IME' => $this->cpu->getIME() ? 'enabled' : 'disabled',
        ];
    }

    public function dumpMemory(int $start, int $length): array
    {
        $data = [];
        for ($i = 0; $i < $length; $i++) {
            $addr = ($start + $i) & 0xFFFF;
            $data[$addr] = $this->bus->readByte($addr);
        }
        return $data;
    }
}
```

---

## 8. Implementation Priority Roadmap

### Phase 1: Foundation (High Priority, Low Risk)
1. **Introduce Value Objects for Configuration** (1-2 days)
   - Create `EmulatorConfig`, `HardwareMode`, `SpeedMultiplier`
   - Refactor `Emulator` to use config objects
   - Update tests

2. **Add Logging Infrastructure** (1 day)
   - Implement `LoggerInterface` and `NullLogger`
   - Add logging to critical paths
   - Add debug logging option

3. **Introduce Test Builders** (2 days)
   - Create `CpuBuilder`, `PpuBuilder` for tests
   - Refactor existing tests to use builders
   - Add more unit tests

### Phase 2: Refactoring (Medium Priority, Medium Risk)
4. **Extract Configuration Management** (2-3 days)
   - Create `DisplayModeManager`
   - Extract `configurePpuDisplayMode` logic
   - Add Strategy pattern for display modes

5. **Introduce Service Container** (3-4 days)
   - Create lightweight `ServiceContainer`
   - Refactor `initializeSystem()` to use container
   - Add `EmulatorServiceProvider`

6. **Split Emulator Responsibilities** (5-7 days)
   - Extract `RomLoader`
   - Extract `StateManager`
   - Extract `EmulatorEngine` (core loop only)
   - Add `SystemBuilder`

### Phase 3: Architecture (Low Priority, High Impact)
7. **Introduce Event System** (3-5 days)
   - Create `EventDispatcher`
   - Migrate interrupt requests to events
   - Add frame events for debugging/metrics

8. **Refactor PPU Rendering** (7-10 days)
   - Split into `BackgroundRenderer`, `WindowRenderer`, `SpriteRenderer`
   - Extract `PpuStateMachine`
   - Create `ScanlineRenderer` coordinator

9. **Add Domain Layer Separation** (10-14 days)
   - Reorganize directory structure
   - Separate application use cases
   - Create infrastructure layer

---

## 9. Conclusion

PHPBoy demonstrates **excellent foundational architecture** with strong type safety, modern PHP features, and clear component boundaries. The main improvement opportunities lie in:

1. **Reducing complexity** in the `Emulator` and `Ppu` classes (God Object pattern)
2. **Improving testability** through dependency injection and better separation of concerns
3. **Adding flexibility** through design patterns (Strategy, Factory, Builder)
4. **Separating domain logic** from infrastructure concerns

**Recommended Next Steps:**
1. Start with Phase 1 improvements (low risk, high value)
2. Add comprehensive test coverage before major refactorings
3. Use feature branches for larger architectural changes
4. Maintain backward compatibility for existing frontend code

**Final Score:** 8.5/10

The codebase is in excellent shape for a personal/educational project. Implementing these recommendations would elevate it to **production-grade, enterprise-level quality** suitable for team collaboration and long-term maintenance.

---

**Questions or Clarifications?**
Feel free to discuss any of these recommendations. I can provide detailed implementation examples for any specific improvement.
