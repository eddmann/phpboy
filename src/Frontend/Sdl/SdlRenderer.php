<?php

declare(strict_types=1);

namespace Gb\Frontend\Sdl;

use Gb\Ppu\Color;
use Gb\Ppu\FramebufferInterface;

/**
 * SDL2 Native Renderer for PHPBoy.
 *
 * Provides hardware-accelerated native rendering using SDL2 PHP extension.
 * This renderer offers true native performance with direct GPU access.
 *
 * Features:
 * - Hardware-accelerated rendering (GPU-based)
 * - VSync support for smooth 60fps output
 * - Integer scaling for pixel-perfect display
 * - Efficient texture streaming for framebuffer updates
 *
 * Requirements:
 * - SDL2 PHP extension (pecl install sdl-beta)
 * - SDL2 library installed on system
 */
final class SdlRenderer implements FramebufferInterface
{
    private const WIDTH = 160;
    private const HEIGHT = 144;

    /** @var resource|object SDL Window */
    private $window;

    /** @var resource|object SDL Renderer */
    private $renderer;

    /** @var resource|object SDL Texture for framebuffer */
    private $texture;

    /** @var array<int, array<int, Color>> Framebuffer data [y][x] */
    private array $pixels = [];

    /** @var string Raw pixel buffer for SDL (RGBA format) */
    private string $pixelBuffer = '';

    private int $frameCount = 0;
    private int $scale;
    private bool $vsync;
    private bool $running = true;

    /**
     * @param int $scale Window scale factor (1-8, default 4)
     * @param bool $vsync Enable VSync for smooth 60fps (default true)
     * @param string $windowTitle Window title
     */
    public function __construct(int $scale = 4, bool $vsync = true, string $windowTitle = 'PHPBoy - Game Boy Color Emulator')
    {
        if (!extension_loaded('sdl')) {
            throw new \RuntimeException(
                "SDL extension not loaded. Install with: pecl install sdl-beta\n" .
                "See docs/sdl2-setup.md for installation instructions."
            );
        }

        $this->scale = max(1, min(8, $scale));
        $this->vsync = $vsync;

        // Initialize SDL2 video subsystem
        if (SDL_Init(SDL_INIT_VIDEO) < 0) {
            throw new \RuntimeException('SDL_Init failed: ' . SDL_GetError());
        }

        // Create window
        $this->window = SDL_CreateWindow(
            $windowTitle,
            SDL_WINDOWPOS_CENTERED,
            SDL_WINDOWPOS_CENTERED,
            self::WIDTH * $this->scale,
            self::HEIGHT * $this->scale,
            SDL_WINDOW_SHOWN
        );

        if (!$this->window) {
            throw new \RuntimeException('Failed to create SDL window: ' . SDL_GetError());
        }

        // Create hardware-accelerated renderer
        $rendererFlags = SDL_RENDERER_ACCELERATED;
        if ($this->vsync) {
            $rendererFlags |= SDL_RENDERER_PRESENTVSYNC;
        }

        $this->renderer = SDL_CreateRenderer($this->window, -1, $rendererFlags);

        if (!$this->renderer) {
            throw new \RuntimeException('Failed to create SDL renderer: ' . SDL_GetError());
        }

        // Set render scale quality to nearest-neighbor (pixel-perfect scaling)
        SDL_SetHint(SDL_HINT_RENDER_SCALE_QUALITY, '0');

        // Create texture for Game Boy screen (160x144)
        $this->texture = SDL_CreateTexture(
            $this->renderer,
            SDL_PIXELFORMAT_RGBA32,
            SDL_TEXTUREACCESS_STREAMING,
            self::WIDTH,
            self::HEIGHT
        );

        if (!$this->texture) {
            throw new \RuntimeException('Failed to create SDL texture: ' . SDL_GetError());
        }

        // Initialize framebuffer with white pixels
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $this->pixels[$y] = [];
            for ($x = 0; $x < self::WIDTH; $x++) {
                $this->pixels[$y][$x] = new Color(255, 255, 255);
            }
        }

        // Pre-allocate pixel buffer (RGBA format: 4 bytes per pixel)
        $this->pixelBuffer = str_repeat("\xFF\xFF\xFF\xFF", self::WIDTH * self::HEIGHT);
    }

    /**
     * Set a pixel at the given coordinates.
     */
    public function setPixel(int $x, int $y, Color $color): void
    {
        if ($x >= 0 && $x < self::WIDTH && $y >= 0 && $y < self::HEIGHT) {
            $this->pixels[$y][$x] = $color;
        }
    }

    /**
     * Get a pixel at the given coordinates.
     */
    public function getPixel(int $x, int $y): Color
    {
        if ($x >= 0 && $x < self::WIDTH && $y >= 0 && $y < self::HEIGHT) {
            return $this->pixels[$y][$x];
        }
        return new Color(0, 0, 0);
    }

    /**
     * Clear the framebuffer (fill with white).
     */
    public function clear(): void
    {
        for ($y = 0; $y < self::HEIGHT; $y++) {
            for ($x = 0; $x < self::WIDTH; $x++) {
                $this->pixels[$y][$x] = new Color(255, 255, 255);
            }
        }
    }

    /**
     * Present the framebuffer to screen.
     *
     * This is called after each frame is complete.
     * Updates the SDL texture and renders to window.
     */
    public function present(): void
    {
        $this->frameCount++;

        // Convert Color objects to raw RGBA bytes
        $offset = 0;
        for ($y = 0; $y < self::HEIGHT; $y++) {
            for ($x = 0; $x < self::WIDTH; $x++) {
                $color = $this->pixels[$y][$x];
                $this->pixelBuffer[$offset++] = chr($color->r);
                $this->pixelBuffer[$offset++] = chr($color->g);
                $this->pixelBuffer[$offset++] = chr($color->b);
                $this->pixelBuffer[$offset++] = chr(255); // Alpha
            }
        }

        // Update texture with pixel data (streaming texture for performance)
        SDL_UpdateTexture($this->texture, null, $this->pixelBuffer, self::WIDTH * 4);

        // Clear renderer
        SDL_RenderClear($this->renderer);

        // Copy texture to renderer (auto-scales to window size)
        SDL_RenderCopy($this->renderer, $this->texture, null, null);

        // Present to screen (swaps buffers)
        SDL_RenderPresent($this->renderer);
    }

    /**
     * Poll SDL events and handle window/input events.
     *
     * @return bool True if should continue running, false if quit requested
     */
    public function pollEvents(): bool
    {
        $event = new \SDL_Event();

        while (SDL_PollEvent($event)) {
            if ($event->type === SDL_QUIT) {
                $this->running = false;
                return false;
            }

            // Window events
            if ($event->type === SDL_WINDOWEVENT) {
                $this->handleWindowEvent($event);
            }
        }

        return $this->running;
    }

    /**
     * Handle window-specific events.
     */
    private function handleWindowEvent(\SDL_Event $event): void
    {
        // Handle window close, resize, etc.
        if (isset($event->window->event)) {
            switch ($event->window->event) {
                case SDL_WINDOWEVENT_CLOSE:
                    $this->running = false;
                    break;
            }
        }
    }

    /**
     * Get the framebuffer width.
     */
    public function getWidth(): int
    {
        return self::WIDTH;
    }

    /**
     * Get the framebuffer height.
     */
    public function getHeight(): int
    {
        return self::HEIGHT;
    }

    /**
     * Get the framebuffer as a 2D array.
     *
     * @return array<int, array<int, Color>> 2D array [y][x] of colors
     */
    public function getFramebuffer(): array
    {
        return $this->pixels;
    }

    /**
     * Get the raw pixel data as a flat array.
     *
     * @return Color[] Array of colors in row-major order
     */
    public function getPixelData(): array
    {
        $data = [];
        for ($y = 0; $y < self::HEIGHT; $y++) {
            for ($x = 0; $x < self::WIDTH; $x++) {
                $data[] = $this->pixels[$y][$x];
            }
        }
        return $data;
    }

    /**
     * Get current frame count.
     */
    public function getFrameCount(): int
    {
        return $this->frameCount;
    }

    /**
     * Check if renderer is still running.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Request renderer to stop.
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Save current framebuffer to a PNG file.
     *
     * Requires GD extension.
     *
     * @param string $filename Output filename
     */
    public function saveToPng(string $filename): void
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException("GD extension is required to save PNG files");
        }

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($image === false) {
            throw new \RuntimeException("Failed to create image");
        }

        for ($y = 0; $y < self::HEIGHT; $y++) {
            for ($x = 0; $x < self::WIDTH; $x++) {
                $color = $this->pixels[$y][$x];
                $r = max(0, min(255, $color->r));
                $g = max(0, min(255, $color->g));
                $b = max(0, min(255, $color->b));
                $colorIndex = imagecolorallocate($image, $r, $g, $b);
                if ($colorIndex !== false) {
                    imagesetpixel($image, $x, $y, $colorIndex);
                }
            }
        }

        imagepng($image, $filename);
        imagedestroy($image);
    }

    /**
     * Get renderer info for debugging.
     *
     * @return array{scale: int, vsync: bool, frames: int}
     */
    public function getInfo(): array
    {
        return [
            'scale' => $this->scale,
            'vsync' => $this->vsync,
            'frames' => $this->frameCount,
        ];
    }

    /**
     * Clean up SDL resources.
     */
    public function __destruct()
    {
        if (isset($this->texture)) {
            SDL_DestroyTexture($this->texture);
        }

        if (isset($this->renderer)) {
            SDL_DestroyRenderer($this->renderer);
        }

        if (isset($this->window)) {
            SDL_DestroyWindow($this->window);
        }

        SDL_Quit();
    }
}
