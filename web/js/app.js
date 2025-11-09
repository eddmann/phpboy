/**
 * PHPBoy - Application Controller
 * Handles UI interactions and emulator lifecycle
 */

class PHPBoyApp {
    constructor() {
        this.phpboy = null;
        this.romLoaded = false;

        // UI Elements
        this.elements = {
            romLoaderSection: document.getElementById('rom-loader-section'),
            emulatorSection: document.getElementById('emulator-section'),
            romFileInput: document.getElementById('rom-file'),
            romInfo: document.getElementById('rom-info'),
            romName: document.getElementById('rom-name'),
            romSize: document.getElementById('rom-size'),
            canvas: document.getElementById('screen'),
            screenOverlay: document.getElementById('screen-overlay'),
            btnPlay: document.getElementById('btn-play'),
            btnPause: document.getElementById('btn-pause'),
            btnReset: document.getElementById('btn-reset'),
            btnLoadNew: document.getElementById('btn-load-new'),
            speedControl: document.getElementById('speed-control'),
            speedValue: document.getElementById('speed-value'),
            volumeControl: document.getElementById('volume-control'),
            volumeValue: document.getElementById('volume-value'),
            fpsDisplay: document.getElementById('fps-display'),
            statusDisplay: document.getElementById('status-display'),
            loadingIndicator: document.getElementById('loading-indicator'),
            loadingMessage: document.getElementById('loading-message'),
            errorDisplay: document.getElementById('error-display'),
            errorMessage: document.getElementById('error-message'),
            aboutLink: document.getElementById('about-link'),
            aboutModal: document.getElementById('about-modal'),
            modalClose: document.getElementById('modal-close'),
        };

        this.init();
    }

    async init() {
        console.log('[PHPBoyApp] Initializing...');

        // Show loading indicator
        this.showLoading('Initializing PHP WASM runtime...');

        try {
            // Initialize PHPBoy emulator
            this.phpboy = new PHPBoy({
                canvas: this.elements.canvas,
                scale: 4,
                onFpsUpdate: (fps) => this.updateFps(fps),
                onError: (err) => this.showError(err),
            });

            await this.phpboy.init();
            this.phpboy.setupInputHandlers();

            // Hide loading
            this.hideLoading();

            // Setup UI event listeners
            this.setupEventListeners();

            // Update status
            this.updateStatus('Ready - Load a ROM to start');

            console.log('[PHPBoyApp] Initialized successfully');
        } catch (err) {
            console.error('[PHPBoyApp] Initialization failed:', err);
            this.showError('Failed to initialize PHPBoy: ' + err.message);
        }
    }

    setupEventListeners() {
        // ROM file selection
        this.elements.romFileInput.addEventListener('change', (e) => {
            this.handleRomSelection(e);
        });

        // Playback controls
        this.elements.btnPlay.addEventListener('click', () => {
            if (this.romLoaded) {
                this.phpboy.start();
                this.updatePlaybackButtons(true);
                this.updateStatus('Running');
                this.elements.screenOverlay.classList.remove('active');
            }
        });

        this.elements.btnPause.addEventListener('click', () => {
            this.phpboy.pause();
            this.updatePlaybackButtons(false);
            this.updateStatus('Paused');
            this.elements.screenOverlay.classList.add('active');
        });

        this.elements.btnReset.addEventListener('click', async () => {
            if (this.romLoaded) {
                await this.phpboy.reset();
                this.updateStatus('Reset - Press Play to start');
            }
        });

        this.elements.btnLoadNew.addEventListener('click', () => {
            this.loadNewRom();
        });

        // Speed control
        this.elements.speedControl.addEventListener('input', (e) => {
            const speed = parseFloat(e.target.value);
            this.elements.speedValue.textContent = speed.toFixed(2) + 'x';
            // TODO: Implement speed control in PHPBoy class
        });

        // Volume control
        this.elements.volumeControl.addEventListener('input', (e) => {
            const volume = parseInt(e.target.value);
            this.elements.volumeValue.textContent = volume + '%';
            // TODO: Implement volume control in PHPBoy class
        });

        // About modal
        this.elements.aboutLink.addEventListener('click', (e) => {
            e.preventDefault();
            this.elements.aboutModal.style.display = 'flex';
        });

        this.elements.modalClose.addEventListener('click', () => {
            this.elements.aboutModal.style.display = 'none';
        });

        this.elements.aboutModal.addEventListener('click', (e) => {
            if (e.target === this.elements.aboutModal) {
                this.elements.aboutModal.style.display = 'none';
            }
        });
    }

    async handleRomSelection(event) {
        const file = event.target.files[0];

        if (!file) {
            return;
        }

        console.log('[PHPBoyApp] ROM selected:', file.name, file.size, 'bytes');

        // Show loading
        this.showLoading('Loading ROM: ' + file.name);

        try {
            // Read file as ArrayBuffer
            const romData = await this.readFileAsArrayBuffer(file);

            // Load ROM into emulator
            await this.phpboy.loadRom(romData, file.name);

            // Update UI
            this.romLoaded = true;
            this.elements.romName.textContent = file.name;
            this.elements.romSize.textContent = this.formatFileSize(file.size);
            this.elements.romInfo.style.display = 'block';

            // Show emulator section
            this.elements.romLoaderSection.style.display = 'none';
            this.elements.emulatorSection.style.display = 'block';

            // Hide loading
            this.hideLoading();

            // Update status
            this.updateStatus('ROM loaded - Press Play to start');

            // Enable play button
            this.elements.btnPlay.disabled = false;

        } catch (err) {
            console.error('[PHPBoyApp] Failed to load ROM:', err);
            this.showError('Failed to load ROM: ' + err.message);
        }
    }

    readFileAsArrayBuffer(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();

            reader.onload = (e) => {
                resolve(e.target.result);
            };

            reader.onerror = (e) => {
                reject(new Error('Failed to read file: ' + e.target.error));
            };

            reader.readAsArrayBuffer(file);
        });
    }

    formatFileSize(bytes) {
        if (bytes < 1024) {
            return bytes + ' B';
        } else if (bytes < 1024 * 1024) {
            return (bytes / 1024).toFixed(2) + ' KB';
        } else {
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }
    }

    updatePlaybackButtons(playing) {
        this.elements.btnPlay.disabled = playing;
        this.elements.btnPause.disabled = !playing;
    }

    updateFps(fps) {
        this.elements.fpsDisplay.textContent = fps;

        // Color code FPS (green if 60, yellow if 45-59, red if < 45)
        if (fps >= 58) {
            this.elements.fpsDisplay.style.color = 'var(--accent-secondary)';
        } else if (fps >= 45) {
            this.elements.fpsDisplay.style.color = '#f59e0b'; // Yellow
        } else {
            this.elements.fpsDisplay.style.color = 'var(--accent-danger)';
        }
    }

    updateStatus(status) {
        this.elements.statusDisplay.textContent = status;
    }

    showLoading(message) {
        this.elements.loadingMessage.textContent = message;
        this.elements.loadingIndicator.style.display = 'block';
        this.elements.errorDisplay.style.display = 'none';
    }

    hideLoading() {
        this.elements.loadingIndicator.style.display = 'none';
    }

    showError(error) {
        console.error('[PHPBoyApp] Error:', error);
        this.elements.errorMessage.textContent = error.toString();
        this.elements.errorDisplay.style.display = 'block';
        this.elements.loadingIndicator.style.display = 'none';
    }

    loadNewRom() {
        // Stop emulation
        if (this.phpboy.running) {
            this.phpboy.stop();
        }

        // Reset UI
        this.romLoaded = false;
        this.elements.romFileInput.value = '';
        this.elements.romInfo.style.display = 'none';
        this.elements.emulatorSection.style.display = 'none';
        this.elements.romLoaderSection.style.display = 'block';
        this.updateStatus('Ready - Load a ROM to start');
        this.updatePlaybackButtons(false);
    }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    console.log('[PHPBoyApp] DOM ready, starting app...');
    window.app = new PHPBoyApp();
});
