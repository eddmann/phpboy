/**
 * PHPBoy - Game Boy Color Emulator in WebAssembly
 * JavaScript Bridge and Runtime
 *
 * This file handles:
 * - PHP WASM module loading and initialization
 * - Emulator lifecycle (load ROM, run, pause, reset)
 * - Frame loop (60 FPS) with requestAnimationFrame
 * - Canvas rendering (160×144 → scaled display)
 * - WebAudio integration (stereo sound)
 * - Keyboard input mapping (arrows, Z/X, Enter, Shift)
 */

class PHPBoy {
    constructor(options = {}) {
        this.canvas = options.canvas;
        this.ctx = this.canvas?.getContext('2d');
        this.scale = options.scale || 4; // Default 4x scale (160×144 → 640×576)

        // Emulator state
        this.php = null; // PHP WASM instance
        this.emulator = null; // PHP Emulator object reference
        this.running = false;
        this.paused = false;
        this.romLoaded = false;

        // Performance tracking
        this.frameCount = 0;
        this.lastFpsUpdate = performance.now();
        this.currentFps = 0;

        // Audio
        this.audioContext = null;
        this.audioBuffer = [];
        this.audioSampleRate = 44100; // Game Boy audio sample rate
        this.audioBufferSize = 2048; // Buffer size for AudioWorklet

        // Input state
        this.buttonState = new Array(8).fill(false); // 8 Game Boy buttons
        this.keyMap = {
            'ArrowUp': 4,
            'ArrowDown': 5,
            'ArrowLeft': 6,
            'ArrowRight': 7,
            'KeyZ': 0, // A
            'KeyX': 1, // B
            'Enter': 2, // Start
            'ShiftLeft': 3, // Select
            'ShiftRight': 3, // Select
        };

        // Callbacks
        this.onFpsUpdate = options.onFpsUpdate || (() => {});
        this.onError = options.onError || ((err) => console.error('PHPBoy Error:', err));

        // Setup canvas
        if (this.canvas) {
            this.canvas.width = 160 * this.scale;
            this.canvas.height = 144 * this.scale;

            // Disable image smoothing for crisp pixel art
            this.ctx.imageSmoothingEnabled = false;
        }
    }

    /**
     * Initialize PHP WASM runtime
     */
    async init() {
        try {
            console.log('[PHPBoy] Initializing PHP WASM runtime...');

            // Wait for php-wasm to be ready
            // Assuming php-wasm loaded via <script> tag exposes global `php` object
            if (typeof php === 'undefined') {
                throw new Error('php-wasm not loaded. Include php-wasm.js before phpboy.js');
            }

            return new Promise((resolve, reject) => {
                php.addEventListener('ready', async () => {
                    this.php = php;
                    console.log('[PHPBoy] PHP WASM ready');

                    try {
                        // Load PHPBoy PHP classes
                        await this.loadPhpClasses();
                        resolve();
                    } catch (err) {
                        reject(err);
                    }
                });

                php.addEventListener('error', (e) => {
                    console.error('[PHPBoy] PHP error:', e);
                    reject(e);
                });
            });
        } catch (err) {
            this.onError(err);
            throw err;
        }
    }

    /**
     * Load PHPBoy PHP source files into WASM environment
     */
    async loadPhpClasses() {
        console.log('[PHPBoy] Loading PHP classes...');

        // In a real implementation, this would:
        // 1. Fetch all PHP source files from dist/
        // 2. Write them to WASM virtual filesystem
        // 3. Include autoloader

        // For now, assume they're bundled with the WASM build
        const result = await this.php.run(`
            <?php
            // Autoload PHPBoy classes (assuming Composer autoloader is available)
            require_once '/phpboy/vendor/autoload.php';
            echo "PHPBoy classes loaded successfully";
        `);

        console.log('[PHPBoy]', result);
    }

    /**
     * Load a ROM file
     * @param {ArrayBuffer} romData ROM file as ArrayBuffer
     * @param {string} filename ROM filename (optional, for display)
     */
    async loadRom(romData, filename = 'game.gb') {
        try {
            console.log(`[PHPBoy] Loading ROM: ${filename} (${romData.byteLength} bytes)`);

            // Convert ArrayBuffer to base64 for transfer to PHP
            const romBytes = new Uint8Array(romData);
            const base64Rom = btoa(String.fromCharCode.apply(null, romBytes));

            // Create emulator instance in PHP
            const phpCode = `
                <?php
                use Gb\\Emulator;
                use Gb\\Cartridge\\Cartridge;
                use Gb\\Frontend\\Wasm\\WasmFramebuffer;
                use Gb\\Frontend\\Wasm\\WasmInput;
                use Gb\\Apu\\Sink\\BufferSink;

                // Decode ROM data
                $romData = base64_decode('${base64Rom}');

                // Create I/O implementations
                $framebuffer = new WasmFramebuffer();
                $audioSink = new BufferSink();
                $input = new WasmInput();

                // Create cartridge from ROM data
                $cartridge = Cartridge::fromRomData($romData);

                // Create and initialize emulator
                $emulator = new Emulator(
                    cartridge: $cartridge,
                    framebuffer: $framebuffer,
                    audioSink: $audioSink,
                    input: $input
                );

                // Store references globally for subsequent calls
                $GLOBALS['phpboy_emulator'] = $emulator;
                $GLOBALS['phpboy_framebuffer'] = $framebuffer;
                $GLOBALS['phpboy_audio'] = $audioSink;
                $GLOBALS['phpboy_input'] = $input;

                echo "ROM loaded: " . $cartridge->getHeader()->getTitle();
            `;

            const result = await this.php.run(phpCode);
            console.log('[PHPBoy]', result);

            this.romLoaded = true;
            this.emulator = '$GLOBALS[\'phpboy_emulator\']'; // PHP variable reference

            return result;
        } catch (err) {
            this.onError(err);
            throw err;
        }
    }

    /**
     * Start emulation
     */
    start() {
        if (!this.romLoaded) {
            throw new Error('No ROM loaded. Call loadRom() first.');
        }

        if (this.running) {
            return; // Already running
        }

        console.log('[PHPBoy] Starting emulation...');
        this.running = true;
        this.paused = false;

        // Initialize audio
        this.initAudio();

        // Start frame loop
        this.frameLoop();
    }

    /**
     * Pause emulation
     */
    pause() {
        this.paused = true;
        console.log('[PHPBoy] Paused');
    }

    /**
     * Resume emulation
     */
    resume() {
        if (!this.paused) return;
        this.paused = false;
        console.log('[PHPBoy] Resumed');
        this.frameLoop();
    }

    /**
     * Stop emulation
     */
    stop() {
        this.running = false;
        this.paused = false;
        console.log('[PHPBoy] Stopped');

        // Stop audio
        if (this.audioContext) {
            this.audioContext.suspend();
        }
    }

    /**
     * Reset emulator
     */
    async reset() {
        console.log('[PHPBoy] Resetting...');

        const phpCode = `
            <?php
            if (isset($GLOBALS['phpboy_emulator'])) {
                $GLOBALS['phpboy_emulator']->reset();
                echo "Emulator reset";
            }
        `;

        await this.php.run(phpCode);

        if (this.running) {
            this.stop();
            this.start();
        }
    }

    /**
     * Main frame loop (runs at 60 FPS)
     */
    async frameLoop() {
        if (!this.running || this.paused) {
            return;
        }

        const frameStart = performance.now();

        try {
            // Step emulator for one frame (70224 CPU cycles = 1/60th second)
            await this.stepFrame();

            // Render to canvas
            await this.renderFrame();

            // Process audio
            await this.processAudio();

            // Update FPS counter
            this.updateFps();
        } catch (err) {
            console.error('[PHPBoy] Frame error:', err);
            this.onError(err);
            // Continue running despite errors (for debugging)
        }

        // Schedule next frame
        // Target: 60 FPS = 16.67ms per frame
        const frameTime = performance.now() - frameStart;
        const targetFrameTime = 1000 / 60;
        const delay = Math.max(0, targetFrameTime - frameTime);

        setTimeout(() => {
            requestAnimationFrame(() => this.frameLoop());
        }, delay);
    }

    /**
     * Step emulator for one frame
     */
    async stepFrame() {
        const phpCode = `
            <?php
            // Step emulator for one frame (70224 T-cycles)
            $emulator = $GLOBALS['phpboy_emulator'];
            $emulator->runFrame();
        `;

        await this.php.run(phpCode);
    }

    /**
     * Render framebuffer to canvas
     */
    async renderFrame() {
        // Get pixel data from PHP framebuffer
        const phpCode = `
            <?php
            $framebuffer = $GLOBALS['phpboy_framebuffer'];
            $pixels = $framebuffer->getPixelsRgba();
            echo json_encode($pixels);
        `;

        const result = await this.php.run(phpCode);
        const pixels = JSON.parse(result);

        // Create ImageData from pixel array
        const imageData = new ImageData(
            new Uint8ClampedArray(pixels),
            160,
            144
        );

        // Draw to canvas (scaled up)
        this.ctx.putImageData(imageData, 0, 0);
        this.ctx.imageSmoothingEnabled = false;

        // Scale up
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = 160;
        tempCanvas.height = 144;
        const tempCtx = tempCanvas.getContext('2d');
        tempCtx.putImageData(imageData, 0, 0);

        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.ctx.drawImage(
            tempCanvas,
            0, 0, 160, 144,
            0, 0, 160 * this.scale, 144 * this.scale
        );
    }

    /**
     * Process audio samples
     */
    async processAudio() {
        if (!this.audioContext) {
            return;
        }

        // Get audio samples from PHP
        const phpCode = `
            <?php
            $audioSink = $GLOBALS['phpboy_audio'];
            $left = $audioSink->getLeftBuffer();
            $right = $audioSink->getRightBuffer();
            $audioSink->clear(); // Clear for next frame
            echo json_encode(['left' => $left, 'right' => $right]);
        `;

        const result = await this.php.run(phpCode);
        const audioData = JSON.parse(result);

        if (audioData.left.length > 0) {
            this.queueAudio(audioData.left, audioData.right);
        }
    }

    /**
     * Initialize WebAudio context
     */
    initAudio() {
        if (this.audioContext) {
            this.audioContext.resume();
            return;
        }

        try {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)({
                sampleRate: this.audioSampleRate
            });

            console.log('[PHPBoy] Audio initialized:', this.audioSampleRate, 'Hz');
        } catch (err) {
            console.warn('[PHPBoy] Audio not available:', err);
        }
    }

    /**
     * Queue audio samples to WebAudio
     */
    queueAudio(leftSamples, rightSamples) {
        if (!this.audioContext || leftSamples.length === 0) {
            return;
        }

        const bufferLength = leftSamples.length;
        const audioBuffer = this.audioContext.createBuffer(
            2, // stereo
            bufferLength,
            this.audioSampleRate
        );

        const leftChannel = audioBuffer.getChannelData(0);
        const rightChannel = audioBuffer.getChannelData(1);

        for (let i = 0; i < bufferLength; i++) {
            leftChannel[i] = leftSamples[i];
            rightChannel[i] = rightSamples[i];
        }

        const source = this.audioContext.createBufferSource();
        source.buffer = audioBuffer;
        source.connect(this.audioContext.destination);
        source.start();
    }

    /**
     * Set button state (called from keyboard events)
     */
    async setButton(buttonCode, pressed) {
        this.buttonState[buttonCode] = pressed;

        if (!this.romLoaded) {
            return;
        }

        const phpCode = `
            <?php
            $input = $GLOBALS['phpboy_input'];
            $input->setButtonState(${buttonCode}, ${pressed ? 'true' : 'false'});
        `;

        await this.php.run(phpCode);
    }

    /**
     * Handle keyboard events
     */
    setupInputHandlers() {
        document.addEventListener('keydown', (e) => {
            if (this.keyMap.hasOwnProperty(e.code)) {
                e.preventDefault();
                const buttonCode = this.keyMap[e.code];
                this.setButton(buttonCode, true);
            }
        });

        document.addEventListener('keyup', (e) => {
            if (this.keyMap.hasOwnProperty(e.code)) {
                e.preventDefault();
                const buttonCode = this.keyMap[e.code];
                this.setButton(buttonCode, false);
            }
        });
    }

    /**
     * Update FPS counter
     */
    updateFps() {
        this.frameCount++;
        const now = performance.now();
        const elapsed = now - this.lastFpsUpdate;

        if (elapsed >= 1000) { // Update every second
            this.currentFps = Math.round((this.frameCount * 1000) / elapsed);
            this.onFpsUpdate(this.currentFps);
            this.frameCount = 0;
            this.lastFpsUpdate = now;
        }
    }

    /**
     * Get emulator state (for debugging)
     */
    async getState() {
        const phpCode = `
            <?php
            $emulator = $GLOBALS['phpboy_emulator'];
            echo json_encode([
                'running' => true,
                'frameCount' => $emulator->getFrameCount() ?? 0,
            ]);
        `;

        const result = await this.php.run(phpCode);
        return JSON.parse(result);
    }
}

// Export for browser usage
if (typeof window !== 'undefined') {
    window.PHPBoy = PHPBoy;
}
