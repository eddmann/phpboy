/**
 * PHPBoy - Game Boy Color Emulator in the Browser
 *
 * JavaScript bridge between PHP-WASM and the browser.
 * Handles ROM loading, rendering, audio, and input.
 */

class PHPBoy {
    constructor() {
        this.php = null;
        this.emulator = null;
        this.canvas = null;
        this.ctx = null;
        this.audioContext = null;
        this.audioWorklet = null;
        this.isRunning = false;
        this.isPaused = false;
        this.animationFrameId = null;
        this.fps = 0;
        this.frameCount = 0;
        this.lastFpsUpdate = 0;

        // Button state tracking
        this.buttons = {
            up: false,
            down: false,
            left: false,
            right: false,
            a: false,
            b: false,
            start: false,
            select: false
        };

        // Key mappings (keyboard key => Game Boy button code)
        this.keyMap = {
            'ArrowUp': 4,
            'ArrowDown': 5,
            'ArrowLeft': 6,
            'ArrowRight': 7,
            'z': 0,          // A button
            'x': 1,          // B button
            'a': 0,          // A button (alternative)
            's': 1,          // B button (alternative)
            'Enter': 2,      // Start
            'Shift': 3       // Select
        };
    }

    /**
     * Initialize PHP-WASM and load the emulator
     */
    async init() {
        console.log('Initializing PHPBoy...');

        // Import php-wasm
        const { PhpWeb } = await import('https://cdn.jsdelivr.net/npm/php-wasm/PhpWeb.mjs');

        console.log('Loading PHP runtime...');
        this.php = new PhpWeb();

        // Wait for PHP to be ready
        await this.php.binary;
        console.log('PHP runtime loaded');

        // Set up canvas
        this.canvas = document.getElementById('screen');
        this.ctx = this.canvas.getContext('2d', { alpha: false });

        // Set canvas size (Game Boy resolution: 160x144, scaled 4x)
        this.canvas.width = 160;
        this.canvas.height = 144;

        // Set up input handlers
        this.setupInput();

        // Set up UI controls
        this.setupControls();

        console.log('PHPBoy initialized');
        this.updateStatus('Ready. Load a ROM to start.');
    }

    /**
     * Load and run a ROM file
     */
    async loadROM(file) {
        try {
            console.log(`Loading ROM: ${file.name}`);
            this.updateStatus(`Loading ${file.name}...`);

            // Read ROM file as array buffer
            const arrayBuffer = await file.arrayBuffer();
            const romData = new Uint8Array(arrayBuffer);

            // Write ROM to PHP filesystem
            await this.php.writeFile('/rom.gb', romData);
            console.log(`ROM written to filesystem: ${romData.length} bytes`);

            // Load and execute the PHP emulator script
            // This script will be loaded from phpboy-wasm.php
            const result = await this.php.run(`<?php
                require_once '/php/vendor/autoload.php';
                require_once '/php/web/phpboy-wasm.php';
            `);

            console.log('Emulator initialized:', result);

            this.updateStatus(`Running ${file.name}`);

            // Start emulation loop
            this.start();

        } catch (error) {
            console.error('Error loading ROM:', error);
            this.updateStatus(`Error: ${error.message}`);
        }
    }

    /**
     * Start the emulation loop
     */
    start() {
        if (this.isRunning) return;

        this.isRunning = true;
        this.isPaused = false;
        this.lastFpsUpdate = performance.now();
        this.frameCount = 0;

        // Initialize audio if not already done
        if (!this.audioContext) {
            this.initAudio();
        }

        // Start the render loop
        this.loop();
    }

    /**
     * Main emulation loop
     */
    async loop() {
        if (!this.isRunning || this.isPaused) return;

        try {
            // Execute one frame in PHP
            const result = await this.php.run(`<?php
                // Step the emulator for one frame
                $emulator->step();

                // Get framebuffer data
                $framebuffer = $emulator->getFramebuffer();
                $pixels = $framebuffer->getPixelsRGBA();

                // Get audio samples
                $audioSink = $emulator->getAudioSink();
                $audioSamples = $audioSink->getSamplesFlat();

                // Return as JSON
                echo json_encode([
                    'pixels' => $pixels,
                    'audio' => $audioSamples
                ]);
            `);

            const data = JSON.parse(result.body);

            // Render frame
            if (data.pixels && data.pixels.length > 0) {
                this.renderFrame(data.pixels);
            }

            // Queue audio samples
            if (data.audio && data.audio.length > 0) {
                this.queueAudio(data.audio);
            }

            // Update FPS counter
            this.updateFPS();

        } catch (error) {
            console.error('Error in emulation loop:', error);
            this.updateStatus(`Error: ${error.message}`);
            this.stop();
            return;
        }

        // Continue loop
        this.animationFrameId = requestAnimationFrame(() => this.loop());
    }

    /**
     * Render a frame to the canvas
     */
    renderFrame(pixels) {
        // Create ImageData from pixel array
        const imageData = new ImageData(
            new Uint8ClampedArray(pixels),
            160,
            144
        );

        // Draw to canvas
        this.ctx.putImageData(imageData, 0, 0);
    }

    /**
     * Initialize Web Audio API
     */
    async initAudio() {
        try {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)({
                sampleRate: 32768
            });

            console.log('Audio context initialized');
        } catch (error) {
            console.error('Error initializing audio:', error);
        }
    }

    /**
     * Queue audio samples to Web Audio API
     */
    queueAudio(samples) {
        if (!this.audioContext || samples.length === 0) return;

        // TODO: Implement proper audio buffering with ScriptProcessorNode or AudioWorklet
        // For now, we skip audio implementation as it requires more complex buffer management
    }

    /**
     * Set up keyboard input handlers
     */
    setupInput() {
        document.addEventListener('keydown', (e) => this.handleKeyDown(e));
        document.addEventListener('keyup', (e) => this.handleKeyUp(e));
    }

    /**
     * Handle key down event
     */
    async handleKeyDown(e) {
        const buttonCode = this.keyMap[e.key];
        if (buttonCode === undefined) return;

        e.preventDefault();

        if (!this.isRunning) return;

        try {
            await this.php.run(`<?php
                $input = $emulator->getInput();
                if ($input instanceof Gb\\Frontend\\Wasm\\WasmInput) {
                    $input->setButtonState(${buttonCode}, true);
                }
            `);
        } catch (error) {
            console.error('Error handling key down:', error);
        }
    }

    /**
     * Handle key up event
     */
    async handleKeyUp(e) {
        const buttonCode = this.keyMap[e.key];
        if (buttonCode === undefined) return;

        e.preventDefault();

        if (!this.isRunning) return;

        try {
            await this.php.run(`<?php
                $input = $emulator->getInput();
                if ($input instanceof Gb\\Frontend\\Wasm\\WasmInput) {
                    $input->setButtonState(${buttonCode}, false);
                }
            `);
        } catch (error) {
            console.error('Error handling key up:', error);
        }
    }

    /**
     * Set up UI controls
     */
    setupControls() {
        // ROM file input
        document.getElementById('romFile').addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                this.loadROM(e.target.files[0]);
            }
        });

        // Pause button
        document.getElementById('pauseBtn').addEventListener('click', () => {
            this.togglePause();
        });

        // Reset button
        document.getElementById('resetBtn').addEventListener('click', () => {
            this.reset();
        });

        // Speed control
        document.getElementById('speedControl').addEventListener('change', (e) => {
            this.setSpeed(parseFloat(e.target.value));
        });

        // Volume control
        document.getElementById('volumeControl').addEventListener('change', (e) => {
            this.setVolume(parseFloat(e.target.value));
        });
    }

    /**
     * Toggle pause state
     */
    togglePause() {
        if (!this.isRunning) return;

        this.isPaused = !this.isPaused;

        const pauseBtn = document.getElementById('pauseBtn');
        pauseBtn.textContent = this.isPaused ? 'Resume' : 'Pause';

        if (!this.isPaused) {
            this.loop();
        } else {
            if (this.animationFrameId) {
                cancelAnimationFrame(this.animationFrameId);
            }
        }
    }

    /**
     * Reset the emulator
     */
    async reset() {
        if (!this.isRunning) return;

        try {
            await this.php.run(`<?php
                $emulator->reset();
            `);
            console.log('Emulator reset');
        } catch (error) {
            console.error('Error resetting emulator:', error);
        }
    }

    /**
     * Set emulation speed
     */
    async setSpeed(multiplier) {
        try {
            await this.php.run(`<?php
                $emulator->setSpeed(${multiplier});
            `);
            console.log(`Speed set to ${multiplier}x`);
        } catch (error) {
            console.error('Error setting speed:', error);
        }
    }

    /**
     * Set audio volume
     */
    setVolume(volume) {
        if (this.audioContext) {
            // TODO: Implement volume control when audio is working
            console.log(`Volume set to ${volume}`);
        }
    }

    /**
     * Stop emulation
     */
    stop() {
        this.isRunning = false;
        this.isPaused = false;

        if (this.animationFrameId) {
            cancelAnimationFrame(this.animationFrameId);
            this.animationFrameId = null;
        }
    }

    /**
     * Update FPS counter
     */
    updateFPS() {
        this.frameCount++;
        const now = performance.now();
        const elapsed = now - this.lastFpsUpdate;

        if (elapsed >= 1000) {
            this.fps = Math.round(this.frameCount / (elapsed / 1000));
            document.getElementById('fps').textContent = this.fps;
            this.frameCount = 0;
            this.lastFpsUpdate = now;
        }
    }

    /**
     * Update status message
     */
    updateStatus(message) {
        document.getElementById('status').textContent = message;
    }
}

// Initialize when DOM is ready
let phpboy;
document.addEventListener('DOMContentLoaded', async () => {
    phpboy = new PHPBoy();
    await phpboy.init();
});
