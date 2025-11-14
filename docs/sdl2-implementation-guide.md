# SDL2 Missing Features - Implementation Guide

## Summary of Critical Gaps

The SDL2 frontend is approximately **60% complete** and missing key features for production use. The main gaps are:

1. **Audio Output** (Blocks basic gameplay)
2. **Frontend Selection** (CLI argument handling)
3. **On-Screen Display** (User feedback)
4. **Hotkey System** (User control)
5. **Display Configuration** (User preferences)

---

## 1. AUDIO OUTPUT (CRITICAL - HIGHEST PRIORITY)

### Current State
- CLI has: `SoxAudioSink` + `WavSink` + full APU implementation
- SDL2 has: No audio support at all

### What Needs to be Done

#### File to Create: `src/Frontend/Sdl/SdlAudioSink.php`

```php
<?php
declare(strict_types=1);

namespace Gb\Frontend\Sdl;

use Gb\Apu\AudioSinkInterface;

/**
 * SDL2 Real-time Audio Sink
 * 
 * Integrates with SDL2 audio subsystem for real-time playback
 */
final class SdlAudioSink implements AudioSinkInterface
{
    /** @var int SDL audio device ID */
    private int $audioDevice;
    
    /** @var float[] Left channel samples buffer */
    private array $leftBuffer = [];
    
    /** @var float[] Right channel samples buffer */
    private array $rightBuffer = [];
    
    private int $sampleRate;
    private bool $available = false;
    
    public function __construct(int $sampleRate = 44100)
    {
        $this->sampleRate = $sampleRate;
        $this->initializeAudio();
    }
    
    private function initializeAudio(): void
    {
        // Initialize SDL audio subsystem
        if (SDL_Init(SDL_INIT_AUDIO) < 0) {
            error_log("SDL Audio Init failed: " . SDL_GetError());
            return;
        }
        
        // Open audio device
        // Would need SDL audio device setup
        // This requires SDL2 PHP extension audio API support
        
        $this->available = true;
    }
    
    public function pushSample(float $left, float $right): void
    {
        if (!$this->available) {
            return;
        }
        
        $this->leftBuffer[] = $left;
        $this->rightBuffer[] = $right;
    }
    
    public function flush(): void
    {
        if (empty($this->leftBuffer)) {
            return;
        }
        
        // Convert to SDL audio format and queue
        // Implementation depends on SDL2 PHP extension capabilities
        
        $this->leftBuffer = [];
        $this->rightBuffer = [];
    }
    
    public function isAvailable(): bool
    {
        return $this->available;
    }
    
    public function __destruct()
    {
        // Clean up audio resources
        SDL_CloseAudioDevice($this->audioDevice);
    }
}
```

### Integration Points

1. **In `bin/phpboy.php`** - Add audio sink selection:
```php
// Around line 295-310
if ($options['frontend'] === 'sdl') {
    if ($options['audio']) {
        $audioSink = new SdlAudioSink(44100);
        $emulator->setAudioSink($audioSink);
    }
}
```

2. **In `src/Frontend/Sdl/SdlRenderer.php`** - Add audio initialization:
```php
public function __construct(...) {
    // Existing code...
    
    // Initialize SDL audio if not already done
    if (!SDL_GetCurrentAudioDriver()) {
        SDL_InitSubSystem(SDL_INIT_AUDIO);
    }
}
```

### Known Issues
- SDL2 PHP extension may have limited audio support
- May need to queue audio differently than expected
- Real-time audio synchronization with emulation timing

---

## 2. FRONTEND SELECTION IN CLI

### Current State
- `bin/phpboy.php` hardcodes `CliRenderer` and `CliInput`
- No `--frontend` option available

### What Needs to be Done

#### Modify: `bin/phpboy.php`

**Step 1**: Add `frontend` option to `parseArguments()`:
```php
// Around line 96-121
$options = [
    // ... existing options ...
    'frontend' => 'cli',  // NEW: default to CLI
    // ... rest ...
];

// In the parsing loop:
} elseif (str_starts_with($arg, '--frontend=')) {
    $mode = substr($arg, 11);
    if (!in_array($mode, ['cli', 'sdl'], true)) {
        fwrite(STDERR, "Invalid frontend: $mode (must be: cli or sdl)\n");
        exit(1);
    }
    $options['frontend'] = $mode;
} elseif ($arg === '--sdl' || $arg === '--sdl2') {
    $options['frontend'] = 'sdl';
}
```

**Step 2**: Update SDL2 setup check:
```php
// Around line 313-315
if (!$options['headless']) {
    if ($options['frontend'] === 'cli') {
        $input = new CliInput();
        $emulator->setInput($input);
    } elseif ($options['frontend'] === 'sdl') {
        $input = new SdlInput();
        $emulator->setInput($input);
    }
}
```

**Step 3**: Update renderer setup:
```php
// Around line 318-326
if ($options['frontend'] === 'cli') {
    $renderer = new CliRenderer();
    if ($options['headless']) {
        $renderer->setDisplayMode('none');
    } else {
        $renderer->setDisplayMode($options['display_mode']);
    }
} elseif ($options['frontend'] === 'sdl') {
    if (!extension_loaded('sdl')) {
        fwrite(STDERR, "Error: SDL extension not loaded\n");
        exit(1);
    }
    $renderer = new SdlRenderer(
        scale: $options['sdl_scale'] ?? 4,
        vsync: $options['sdl_vsync'] ?? true
    );
} else {
    throw new \RuntimeException("Unknown frontend: {$options['frontend']}");
}

$emulator->setFramebuffer($renderer);
```

**Step 4**: Add SDL-specific main loop (for event polling):
```php
// Replace the simple $emulator->run() with frontend-aware loop
if ($options['frontend'] === 'sdl') {
    // SDL2 requires event polling
    while ($renderer->isRunning()) {
        if (!$renderer->pollEvents()) {
            break;
        }
        
        $emulator->step();
        
        if ($rewindBuffer !== null) {
            $rewindBuffer->recordFrame();
        }
    }
} else {
    // CLI can use the simple run loop
    $emulator->run();
}
```

---

## 3. ON-SCREEN DISPLAY (FPS Counter, Debug Info)

### Current State
- CLI shows: Frame number, elapsed time, FPS info
- SDL2 shows: Nothing

### What Needs to be Done

#### Modify: `src/Frontend/Sdl/SdlRenderer.php`

**Step 1**: Add text rendering capability (using SDL2 TTF):
```php
private $font = null;
private $showDebugInfo = true;

public function __construct(..., string $fontPath = null) {
    // ... existing code ...
    
    if ($fontPath && SDL_Init(SDL_INIT_VIDEO | SDL_INIT_EVENTS) >= 0) {
        // Initialize TTF if available
        // This requires SDL2_ttf extension
        if (function_exists('TTF_Init')) {
            TTF_Init();
            if ($fontPath && file_exists($fontPath)) {
                $this->font = TTF_OpenFont($fontPath, 12);
            }
        }
    }
}
```

**Step 2**: Draw FPS overlay in `present()`:
```php
public function present(): void
{
    $this->frameCount++;
    
    // ... existing rendering code ...
    
    // Draw debug overlay if enabled
    if ($this->showDebugInfo && $this->font !== null) {
        $this->drawDebugOverlay();
    }
}

private function drawDebugOverlay(): void
{
    $fps = $this->calculateFps();
    $text = sprintf("FPS: %.1f | Frame: %d", $fps, $this->frameCount);
    
    // Render text surface using SDL2_ttf
    // This is complex - alternative: use simple pixel-based font rendering
    // or skip and just log to console
}
```

**Alternative (Simpler)**: Just show in window title:
```php
public function present(): void
{
    $this->frameCount++;
    
    // ... existing code ...
    
    // Update window title with FPS
    if ($this->frameCount % 60 === 0) {  // Update every second
        $fps = $this->calculateFps();
        SDL_SetWindowTitle(
            $this->window,
            sprintf("PHPBoy - %.1f FPS | Frame %d", $fps, $this->frameCount)
        );
    }
}

private function calculateFps(): float
{
    static $lastTime = 0;
    static $frameCount = 0;
    
    $currentTime = microtime(true);
    $frameCount++;
    
    if ($currentTime - $lastTime >= 1.0) {
        $fps = $frameCount / ($currentTime - $lastTime);
        $frameCount = 0;
        $lastTime = $currentTime;
        return $fps;
    }
    
    return 0.0;
}
```

---

## 4. HOTKEY SUPPORT

### Current State
- No hotkeys defined for SDL2
- Possible hotkeys: F11 (fullscreen), F12 (screenshot), P (pause), ESC (exit)

### What Needs to be Done

#### Modify: `src/Frontend/Sdl/SdlInput.php`

**Step 1**: Add hotkey handler:
```php
class SdlInput implements InputInterface
{
    // ... existing code ...
    
    private $hotkeyCallbacks = [];
    
    public function registerHotkey(int $scancode, callable $callback): void
    {
        $this->hotkeyCallbacks[$scancode] = $callback;
    }
    
    public function handleKeyEvent(\SDL_Event $event): void
    {
        // ... existing key mapping code ...
        
        // Check for hotkeys
        if ($event->type === SDL_KEYDOWN) {
            $scancode = $event->key->keysym->scancode;
            
            if (isset($this->hotkeyCallbacks[$scancode])) {
                call_user_func($this->hotkeyCallbacks[$scancode]);
            }
        }
    }
}
```

#### Modify: `src/Frontend/Sdl/SdlRenderer.php`

**Step 2**: Add hotkey callbacks in renderer:
```php
public function registerHotkeys(SdlInput $input): void
{
    // F11 - Toggle fullscreen
    $input->registerHotkey(SDL_SCANCODE_F11, fn() => $this->toggleFullscreen());
    
    // F12 - Take screenshot
    $input->registerHotkey(SDL_SCANCODE_F12, fn() => $this->takescreenshot());
    
    // ESC - Exit
    $input->registerHotkey(SDL_SCANCODE_ESCAPE, fn() => $this->stop());
    
    // P - Pause/Resume
    $input->registerHotkey(SDL_SCANCODE_P, fn() => $this->togglePause());
}

private function toggleFullscreen(): void
{
    $flags = SDL_GetWindowFlags($this->window);
    $fullscreen = ($flags & SDL_WINDOW_FULLSCREEN_DESKTOP) !== 0;
    SDL_SetWindowFullscreen($this->window, $fullscreen ? 0 : SDL_WINDOW_FULLSCREEN_DESKTOP);
}

private function takeScreenshot(): void
{
    $filename = sprintf("screenshot_%d.png", time());
    $this->saveToPng($filename);
    echo "Screenshot saved: $filename\n";
}

private function togglePause(): void
{
    // Would need to signal emulator to pause
    // Requires emulator API extension
    echo "Pause toggle not yet implemented\n";
}
```

---

## 5. DISPLAY CONFIGURATION

### Current State
- SDL2 hardcoded to 4x scale, VSync enabled
- No window resizing or scale selection

### What Needs to be Done

#### Add command-line options to `bin/phpboy.php`:
```php
// In parseArguments():
$options = [
    // ... existing ...
    'sdl_scale' => 4,      // NEW
    'sdl_vsync' => true,   // NEW
    'sdl_fullscreen' => false,  // NEW
];

// In parsing loop:
} elseif (str_starts_with($arg, '--sdl-scale=')) {
    $scale = (int)substr($arg, 12);
    $options['sdl_scale'] = max(1, min(8, $scale));
} elseif ($arg === '--sdl-no-vsync') {
    $options['sdl_vsync'] = false;
} elseif ($arg === '--fullscreen') {
    $options['sdl_fullscreen'] = true;
}
```

#### Modify `src/Frontend/Sdl/SdlRenderer.php`:
```php
public function __construct(
    int $scale = 4,
    bool $vsync = true,
    string $windowTitle = 'PHPBoy',
    bool $fullscreen = false
) {
    // ... existing init code ...
    
    // Apply fullscreen flag
    if ($fullscreen) {
        SDL_SetWindowFullscreen($this->window, SDL_WINDOW_FULLSCREEN_DESKTOP);
    }
}

public function setScale(int $scale): void
{
    if ($scale === $this->scale) {
        return;
    }
    
    $this->scale = max(1, min(8, $scale));
    $newWidth = self::WIDTH * $this->scale;
    $newHeight = self::HEIGHT * $this->scale;
    SDL_SetWindowSize($this->window, $newWidth, $newHeight);
}
```

---

## 6. JOYSTICK/GAMEPAD SUPPORT (Nice to Have)

### Current State
- `SdlInput` has infrastructure for custom mappings
- No gamepad button detection or mapping

### What Needs to be Done

#### Modify: `src/Frontend/Sdl/SdlInput.php`

```php
public function __construct()
{
    // ... existing code ...
    
    // Initialize joystick support
    SDL_Init(SDL_INIT_JOYSTICK | SDL_INIT_GAMECONTROLLER);
    SDL_JoystickEventState(SDL_ENABLE);
    SDL_GameControllerEventState(SDL_ENABLE);
    
    $this->loadGamepadMappings();
}

private function loadGamepadMappings(): void
{
    // Map game controller buttons to Game Boy buttons
    // This would require handling SDL_CONTROLLERBUTTONDOWN events
    // Similar to keyboard event handling
}

public function handleControllerEvent(\SDL_Event $event): void
{
    if ($event->type === SDL_CONTROLLERBUTTONDOWN) {
        // Map controller buttons to Game Boy buttons
        $button = $this->getButtonFromController($event->cbutton->button);
        if ($button !== null) {
            $this->pressedButtons[] = $button;
        }
    } elseif ($event->type === SDL_CONTROLLERBUTTONUP) {
        // Remove button from pressed list
    } elseif ($event->type === SDL_CONTROLLERAXISMOTION) {
        // Handle analog stick for D-pad
    }
}
```

---

## 7. IMPLEMENTATION PRIORITY & EFFORT ESTIMATE

| Feature | Priority | Effort | Blocking | Files to Modify |
|---------|----------|--------|----------|-----------------|
| Audio Sink | 🔴 Critical | 4-6 hrs | YES | Create: `SdlAudioSink.php`<br>Modify: `bin/phpboy.php` |
| Frontend Selection | 🔴 Critical | 2-3 hrs | YES | Modify: `bin/phpboy.php` |
| Window Title FPS | 🟡 Important | 1 hr | NO | Modify: `SdlRenderer.php` |
| Hotkey System | 🟡 Important | 2-3 hrs | NO | Modify: `SdlInput.php`, `SdlRenderer.php` |
| Display Config | 🟡 Important | 2 hrs | NO | Modify: `bin/phpboy.php`, `SdlRenderer.php` |
| Joystick Support | 🟢 Nice to Have | 3-4 hrs | NO | Modify: `SdlInput.php` |
| Advanced Rendering | 🟢 Nice to Have | 4-6 hrs | NO | Modify: `SdlRenderer.php` |

**Total for MVP (Critical + Important): ~10-15 hours**

---

## 8. TESTING CHECKLIST

After implementing each feature:

- [ ] Audio: Games play sound in SDL2 mode
- [ ] Frontend: `--frontend=sdl` and `--frontend=cli` both work
- [ ] FPS Display: Window title shows current FPS
- [ ] Hotkeys: F11, F12, ESC, P all work as expected
- [ ] Display Config: Scale and fullscreen options apply correctly
- [ ] Save States: `--savestate-load` and `--savestate-save` work with SDL2
- [ ] Speed Control: `--speed` option works with SDL2
- [ ] Input: Keyboard input works responsively

---

## 9. ARCHITECTURAL IMPROVEMENTS

Consider these enhancements alongside implementation:

1. **Abstract Frontend Selection**
   ```php
   // Create a FrontendFactory
   $frontend = FrontendFactory::create($options['frontend'], $options);
   ```

2. **Unified Configuration**
   ```php
   // Config class for frontend options
   $sdlConfig = new SdlConfig();
   $sdlConfig->setScale(4);
   $sdlConfig->setVSync(true);
   ```

3. **Event System**
   ```php
   // Allow emulator to dispatch pause/resume events
   $emulator->addEventListener('pause', $callback);
   ```

---

## CONCLUSION

The SDL2 frontend is **architecturally sound** but **functionally incomplete**. The primary blocker is audio support. With focused implementation of the 7 features listed above, SDL2 can achieve full feature parity with the CLI frontend within 2-3 days of development work.

The suggested implementation order:
1. Audio (blocks gameplay)
2. Frontend selection (basic usability)
3. Window title FPS (user feedback)
4. Hotkeys (user control)
5. Display configuration (user preferences)
6. Joystick (nice enhancement)
7. Advanced rendering (cosmetic)
