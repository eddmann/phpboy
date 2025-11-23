/**
 * PHPBoy - Game Boy Color Emulator in the Browser
 *
 * JavaScript bridge between em (PHP WebAssembly) and the browser.
 * Handles ROM loading, rendering, audio, and input.
 *
 * Uses krakjoe/em instead of php-wasm for better performance.
 */

class PHPBoy {
    constructor() {
        this.Module = null;
        this.emulator = null;
        this.canvas = null;
        this.ctx = null;
        this.audioContext = null;
        this.isRunning = false;
        this.isPaused = false;
        this.animationFrameId = null;
        this.fps = 0;
        this.frameCount = 0;
        this.lastFpsUpdate = 0;
        this.emulatorInitialized = false;

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
     * Initialize em and load the emulator
     */
    async init() {
        console.log('Initializing PHPBoy with em...');

        try {
            // Wait for Module to be ready (loaded by php-em.js)
            await this.waitForModule();

            console.log('Module loaded, starting up PHP runtime...');

            // Start up PHP (MINIT)
            const startupResult = this.Module.startup();
            if (!startupResult) {
                throw new Error('PHP startup failed');
            }

            console.log('PHP runtime started successfully');

            // Set up canvas
            this.canvas = document.getElementById('screen');
            this.ctx = this.canvas.getContext('2d', { alpha: false });

            // Set canvas size (Game Boy resolution: 160x144)
            this.canvas.width = 160;
            this.canvas.height = 144;

            // Set up input handlers
            this.setupInput();

            // Set up UI controls
            this.setupControls();

            console.log('PHPBoy initialized');
            this.updateStatus('Ready. Load a ROM to start.');

        } catch (error) {
            console.error('Error initializing PHPBoy:', error);
            this.updateStatus(\`Error: \${error.message}\`);
        }
    }

    /**
     * Wait for the Module to be ready
     */
    async waitForModule() {
        if (typeof Module !== 'undefined' && Module.ready) {
            this.Module = Module;
            return;
        }

        // Wait for Module to be defined and ready
        return new Promise((resolve, reject) => {
            const timeout = setTimeout(() => {
                reject(new Error('Timeout waiting for em Module to load'));
            }, 30000); // 30 second timeout

            const checkModule = () => {
                if (typeof Module !== 'undefined') {
                    this.Module = Module;

                    // Wait for Module to be fully ready
                    if (Module.ready) {
                        clearTimeout(timeout);
                        resolve();
                    } else {
                        setTimeout(checkModule, 100);
                    }
                } else {
                    setTimeout(checkModule, 100);
                }
            };

            checkModule();
        });
    }

    /**
     * Load and run a ROM file
     */
    async loadROM(file) {
        try {
            console.log(\`Loading ROM: \${file.name}\`);
            this.updateStatus(\`Loading \${file.name}...\`);

            // Read ROM file as array buffer
            const arrayBuffer = await file.arrayBuffer();
            const romData = new Uint8Array(arrayBuffer);

            // Write ROM to VFS using em's VFS API
            console.log(\`Writing ROM to VFS: \${romData.length} bytes\`);
            this.Module.vfs.put('/rom.gb', romData);

            console.log('ROM written to VFS successfully');

            // Load the emulator PHP code if not already loaded
            if (!this.emulatorInitialized) {
                console.log('Loading PHPBoy emulator code...');

                // Fetch the bundled emulator code
                const response = await fetch('/phpboy-wasm-full.php');
                const phpCode = await response.text();

                // Write to VFS
                const encoder = new TextEncoder();
                this.Module.vfs.put('/phpboy-wasm.php', encoder.encode(phpCode));

                console.log('Emulator code written to VFS');

                // Include the emulator (this initializes everything)
                console.log('Initializing emulator...');
                const initOutput = await this.Module.include('/phpboy-wasm.php');
                console.log('Emulator init output:', initOutput);

                this.emulatorInitialized = true;
            }

            this.updateStatus(\`Running \${file.name}\`);

            // Start emulation loop
            this.start();

        } catch (error) {
            console.error('Error loading ROM:', error);
            this.updateStatus(\`Error: \${error.message}\`);
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
            // Run multiple frames per render to improve performance
            // This reduces PHP-JS bridge overhead significantly
            const framesPerRender = 4;

            // Execute frames and get output
            const output = await this.Module.invoke(\`<?php
                global $emulator;

                // Step the emulator multiple times
                for ($i = 0; $i < \${framesPerRender}; $i++) {
                    $emulator->step();
                }

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
            ?>\`);

            const data = JSON.parse(output);

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
            this.updateStatus(\`Error: \${error.message}\`);
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
            await this.Module.invoke(\`<?php
                global $emulator;
                $input = $emulator->getInput();
                if ($input instanceof Gb\\\\Frontend\\\\Wasm\\\\WasmInput) {
                    $input->setButtonState(\${buttonCode}, true);
                }
            ?>\`);
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
            await this.Module.invoke(\`<?php
                global $emulator;
                $input = $emulator->getInput();
                if ($input instanceof Gb\\\\Frontend\\\\Wasm\\\\WasmInput) {
                    $input->setButtonState(\${buttonCode}, false);
                }
            ?>\`);
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

        // Save state button
        document.getElementById('saveStateBtn').addEventListener('click', () => {
            this.saveState();
        });

        // Load state button
        document.getElementById('loadStateBtn').addEventListener('click', () => {
            this.loadState();
        });

        // Screenshot button
        document.getElementById('screenshotBtn').addEventListener('click', () => {
            this.takeScreenshot();
        });

        // Fast forward button
        let fastForwardActive = false;
        document.getElementById('fastForwardBtn').addEventListener('click', () => {
            fastForwardActive = !fastForwardActive;
            this.setSpeed(fastForwardActive ? 4.0 : 1.0);
            document.getElementById('fastForwardBtn').textContent =
                fastForwardActive ? 'Normal Speed' : 'Fast Forward';
            document.getElementById('fastForwardBtn').classList.toggle('active', fastForwardActive);
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
            await this.Module.invoke(\`<?php
                global $emulator;
                $emulator->reset();
            ?>\`);
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
            await this.Module.invoke(\`<?php
                global $emulator;
                $emulator->setSpeed(\${multiplier});
            ?>\`);
            console.log(\`Speed set to \${multiplier}x\`);
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
            console.log(\`Volume set to \${volume}\`);
        }
    }

    /**
     * Save emulator state to browser storage
     */
    async saveState() {
        if (!this.isRunning) {
            this.updateSavestateInfo('No ROM loaded');
            return;
        }

        try {
            // Serialize the emulator state
            const stateJson = await this.Module.invoke(\`<?php
                global $emulator;
                $manager = new \\\\Gb\\\\Savestate\\\\SavestateManager($emulator);
                $state = $manager->serialize();
                echo json_encode($state);
            ?>\`);

            // Save to localStorage
            localStorage.setItem('phpboy_savestate', stateJson);

            this.updateSavestateInfo('State saved!');
            setTimeout(() => this.updateSavestateInfo(''), 3000);

            console.log('Savestate saved to localStorage');
        } catch (error) {
            console.error('Error saving state:', error);
            this.updateSavestateInfo('Error saving state');
        }
    }

    /**
     * Load emulator state from browser storage
     */
    async loadState() {
        if (!this.isRunning) {
            this.updateSavestateInfo('No ROM loaded');
            return;
        }

        try {
            const savedState = localStorage.getItem('phpboy_savestate');

            if (!savedState) {
                this.updateSavestateInfo('No saved state found');
                setTimeout(() => this.updateSavestateInfo(''), 3000);
                return;
            }

            // Deserialize the state into the emulator
            // Need to escape the JSON properly for PHP
            const escapedState = savedState.replace(/\\\\/g, '\\\\\\\\').replace(/'/g, "\\\\'");

            await this.Module.invoke(\`<?php
                global $emulator;
                $manager = new \\\\Gb\\\\Savestate\\\\SavestateManager($emulator);
                $stateData = json_decode('\${escapedState}', true);
                $manager->deserialize($stateData);
            ?>\`);

            this.updateSavestateInfo('State loaded!');
            setTimeout(() => this.updateSavestateInfo(''), 3000);

            console.log('Savestate loaded from localStorage');
        } catch (error) {
            console.error('Error loading state:', error);
            this.updateSavestateInfo('Error loading state');
        }
    }

    /**
     * Take a screenshot and download it
     */
    takeScreenshot() {
        if (!this.canvas) {
            return;
        }

        // Convert canvas to blob and download
        this.canvas.toBlob((blob) => {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = \`phpboy-screenshot-\${Date.now()}.png\`;
            a.click();
            URL.revokeObjectURL(url);

            this.updateSavestateInfo('Screenshot saved!');
            setTimeout(() => this.updateSavestateInfo(''), 3000);
        });
    }

    /**
     * Update savestate info text
     */
    updateSavestateInfo(message) {
        const infoElement = document.getElementById('savestateInfo');
        if (infoElement) {
            infoElement.textContent = message;
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
