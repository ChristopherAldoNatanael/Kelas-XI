/**
 * Pemindai QR Pickup Pass untuk halaman VCMS (/admin/pet-shop/pickups).
 *
 * Prinsip:
 * 1. Memindai dengan kamera adalah jalur utama — kamera dinyalakan otomatis.
 * 2. Kode manual / token QR adalah cadangan; terbuka otomatis saat kamera
 *    tidak bisa dipakai atau QR tidak terbaca.
 *
 * Decode memakai dua mesin:
 * - `BarcodeDetector` (bawaan browser) bila tersedia — paling cepat, mis.
 *   di Android/ChromeOS.
 * - `jsQR` (pure JS) sebagai pengganti. Tanpa ini, Chrome di PC/laptop
 *   tidak punya `BarcodeDetector` sama sekali sehingga kamera tidak pernah
 *   bisa dipakai di ruang depan klinik.
 *
 * `jsQR` dimuat secara dinamis (import()) supaya tidak menambah bobot
 * semua halaman admin — hanya halaman pengambilan yang memakainya.
 */

let jsQR = null;

const loadJsQr = async () => {
    if (jsQR) return jsQR;
    const module = await import('jsqr');
    jsQR = module.default || module;
    return jsQR;
};

const ROOT_SELECTOR = '[data-scan-root]';

/* ── Util ─────────────────────────────────────────────────────────────── */

const readJson = (value, fallback = {}) => {
    try {
        return JSON.parse(value) || fallback;
    } catch (error) {
        return fallback;
    }
};

/**
 * Kode hasil pindai bisa berupa token panjang, kode 8 karakter, JSON, atau
 * URL dari scanner lain. Semua dinormalkan ke satu bentuk yang dipahami
 * server.
 */
export const extractCode = (raw) => {
    const value = String(raw || '').trim();
    if (!value) return '';

    try {
        const parsed = JSON.parse(value);
        const candidate = parsed.token || parsed.qr_payload || parsed.code || parsed.manual_code;
        if (candidate) return extractCode(candidate);
    } catch (error) {
        /* bukan JSON — lanjut penapisan biasa */
    }

    const fromQuery = value.match(/[?&](?:code|token|qr)=([^&#]+)/i);
    if (fromQuery) return extractCode(decodeURIComponent(fromQuery[1]));

    if (value.includes('/')) {
        const segment = value.split('/').filter(Boolean).pop();
        if (segment) return extractCode(segment);
    }

    const compact = value.replace(/[^A-Za-z0-9]/g, '');
    if (compact.length >= 32) return compact;               // token QR
    if (compact.length === 8) {
        return compact.slice(0, 4) + '-' + compact.slice(4); // kode manual
    }

    return value;
};

/* ── Pemindai ─────────────────────────────────────────────────────────── */

export function initPickupScanner() {
    const root = document.querySelector(ROOT_SELECTOR);
    if (!root || root.dataset.scanReady === '1') return;
    root.dataset.scanReady = '1';

    const strings = readJson(root.dataset.scanStrings, {});

    const video = root.querySelector('[data-scan-video]');
    const idle = root.querySelector('[data-scan-idle]');
    const idleIcon = root.querySelector('[data-scan-idle-icon]');
    const idleTitle = root.querySelector('[data-scan-idle-title]');
    const idleHint = root.querySelector('[data-scan-idle-hint]');
    const startBtn = root.querySelector('[data-scan-start]');
    const startText = root.querySelector('[data-scan-start-text]');
    const frame = root.querySelector('[data-scan-frame]');
    const chip = root.querySelector('[data-scan-chip]');
    const chipText = root.querySelector('[data-scan-chip-text]');
    const dot = root.querySelector('[data-scan-dot]');
    const torchBtn = root.querySelector('[data-scan-torch]');
    const stopBtn = root.querySelector('[data-scan-stop]');
    const status = root.querySelector('[data-scan-status]');
    const form = root.querySelector('[data-scan-form]');
    const viaInput = root.querySelector('[data-scan-via]');
    const fileInput = root.querySelector('[data-scan-file]');
    const codeInput = document.getElementById('pickup_code');
    const fallbackToggle = root.querySelector('[data-scan-fallback-toggle]');
    const fallbackChevron = root.querySelector('[data-scan-fallback-chevron]');

    if (!video || !form) return;

    let stream = null;
    let track = null;
    let detector = null;
    let running = false;
    let scanning = false;
    let timer = null;
    let firstDetectAt = null;
    let torchOn = false;
    let cameraUnavailable = false;
    let stoppedByUser = false;

    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });

    const setStatus = (text) => {
        if (status) status.textContent = text || '';
    };

    const setIdle = (title, hint, icon) => {
        if (idleTitle) idleTitle.textContent = title;
        if (idleHint) idleHint.textContent = hint;
        if (idleIcon) idleIcon.textContent = icon || 'qr_code_scanner';
        if (idle) idle.classList.remove('is-hidden');
        if (frame) frame.classList.add('is-hidden');
        if (chip) chip.classList.add('is-hidden');
    };

    /* Jalur cadangan dibuka otomatis saat kamera tidak bisa dipakai atau QR
       tidak terbaca, dan tetap bisa dibuka manual oleh petugas. */
    const openFallback = (focus = false) => {
        form.classList.remove('is-hidden');
        fallbackToggle?.setAttribute('aria-expanded', 'true');
        fallbackChevron?.classList.add('rotate-180');
        if (focus && codeInput) {
            codeInput.focus();
            codeInput.select();
        }
    };

    const closeFallback = () => {
        form.classList.add('is-hidden');
        fallbackToggle?.setAttribute('aria-expanded', 'false');
        fallbackChevron?.classList.remove('rotate-180');
    };

    fallbackToggle?.addEventListener('click', () => {
        if (form.classList.contains('is-hidden')) openFallback(true);
        else closeFallback();
    });

    const submitCode = (code, via) => {
        if (!code) return;
        codeInput.value = code;
        if (viaInput) viaInput.value = via;
        form.submit();
    };

    const stopCamera = () => {
        running = false;
        scanning = false;
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
        if (stream) {
            stream.getTracks().forEach((item) => item.stop());
            stream = null;
        }
        track = null;
        torchOn = false;
        torchBtn?.classList.add('is-hidden');
        torchBtn?.setAttribute('aria-pressed', 'false');
        stopBtn?.classList.add('is-hidden');
        frame?.classList.add('is-hidden');
        chip?.classList.add('is-hidden');
        idle?.classList.remove('is-hidden');
        video.srcObject = null;
    };

    const describeError = (error) => {
        const name = error && error.name ? error.name : '';

        if (name === 'NotAllowedError' || name === 'SecurityError') return strings.denied;
        if (name === 'NotFoundError' || name === 'OverconstrainedError') return strings.notfound;
        if (name === 'NotReadableError' || name === 'AbortError') return strings.busy;

        return strings.failed;
    };

    /* Decode satu frame dengan jsQR (dipakai saat BarcodeDetector tidak
       tersedia, mis. Chrome di PC). */
    const decodeFrame = async () => {
        if (!video.videoWidth || !video.videoHeight) return null;

        const decoder = await loadJsQr();
        if (!decoder) return null;

        const scale = Math.min(1, 640 / video.videoWidth);
        const width = Math.max(1, Math.round(video.videoWidth * scale));
        const height = Math.max(1, Math.round(video.videoHeight * scale));

        if (canvas.width !== width || canvas.height !== height) {
            canvas.width = width;
            canvas.height = height;
        }

        context.drawImage(video, 0, 0, width, height);

        const image = context.getImageData(0, 0, width, height);
        const result = decoder(image.data, width, height, { inversionAttempts: 'attemptBoth' });

        return result ? result.data : null;
    };

    const detectCode = async () => {
        if (detector) {
            const codes = await detector.detect(video);
            if (codes && codes.length > 0 && codes[0].rawValue) return codes[0].rawValue;
            return null;
        }

        return decodeFrame();
    };

    const startCamera = async () => {
        if (running || cameraUnavailable || stoppedByUser) return;

        if (idleTitle) idleTitle.textContent = strings.ready;
        if (idleHint) idleHint.textContent = '';
        if (startBtn) startBtn.disabled = true;

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
                audio: false,
            });
        } catch (error) {
            if (startBtn) startBtn.disabled = false;
            if (startText) startText.textContent = strings.retry;
            setIdle(strings.failed, describeError(error), 'videocam_off');
            setStatus(describeError(error));
            openFallback(true);
            return;
        }

        track = stream.getVideoTracks()[0] || null;

        // Lampu (torch) membantu layar gelap; hanya ditampilkan bila
        // perangkat benar-benar mendukungnya.
        try {
            const capabilities = track && track.getCapabilities ? track.getCapabilities() : {};
            if (capabilities && capabilities.torch) {
                torchBtn?.classList.remove('is-hidden');
            }
        } catch (error) {
            /* perangkat tidak melaporkan kemampuan */
        }

        video.srcObject = stream;
        try {
            await video.play();
        } catch (error) {
            /* autoplay ditolak; frame tetap terbaca */
        }

        running = true;
        firstDetectAt = null;
        idle?.classList.add('is-hidden');
        frame?.classList.remove('is-hidden');
        chip?.classList.remove('is-hidden');
        stopBtn?.classList.remove('is-hidden');
        if (chipText) chipText.textContent = strings.live;
        if (dot) dot.className = 'h-1.5 w-1.5 rounded-full bg-emerald-400';
        setStatus(strings.ready);
        if (startBtn) startBtn.disabled = false;

        const loop = async () => {
            if (!running) return;

            if (!scanning && video.readyState >= 2) {
                scanning = true;
                try {
                    const raw = await detectCode();
                    if (raw) {
                        const code = extractCode(raw);
                        stopCamera();
                        setStatus(strings.success);
                        submitCode(code, 'qr');
                        return;
                    }
                } catch (error) {
                    /* frame yang gagal dibaca (blur) cukup dilewati */
                }
                scanning = false;
            }

            // Belum menemukan apa pun: beri petunjuk, lalu buka jalur kode
            // manual tanpa mematikan kamera.
            if (firstDetectAt === null) firstDetectAt = Date.now();
            if (Date.now() - firstDetectAt > 9000) {
                if (status && status.textContent !== strings.nodetect_hint) {
                    setStatus(strings.nodetect_hint);
                    if (chipText) chipText.textContent = strings.nodetect;
                    if (dot) dot.className = 'h-1.5 w-1.5 rounded-full bg-amber-400';
                    openFallback(false);
                }
            }

            timer = setTimeout(loop, detector ? 120 : 180);
        };

        timer = setTimeout(loop, 120);
    };

    /* Fallback terakhir: petugas memotret layar pelanggan, lalu gambarnya
       didekode di perangkat ini — tanpa perlu kamera menyala. */
    const decodeImageFile = async (file) => {
        if (!file) return;

        const decoder = await loadJsQr();
        if (!decoder) {
            setStatus(strings.failed);
            return;
        }

        const image = new Image();
        const url = URL.createObjectURL(file);

        image.onload = () => {
            try {
                const scale = Math.min(1, 1200 / image.width);
                const width = Math.max(1, Math.round(image.width * scale));
                const height = Math.max(1, Math.round(image.height * scale));

                canvas.width = width;
                canvas.height = height;
                context.drawImage(image, 0, 0, width, height);

                const data = context.getImageData(0, 0, width, height);
                const result = decoder(data.data, width, height, { inversionAttempts: 'attemptBoth' });

                if (result && result.data) {
                    stopCamera();
                    setStatus(strings.success);
                    submitCode(extractCode(result.data), 'qr');
                    return;
                }

                setStatus(strings.nodetect_hint);
            } catch (error) {
                setStatus(strings.failed);
            } finally {
                URL.revokeObjectURL(url);
                if (fileInput) fileInput.value = '';
            }
        };

        image.onerror = () => {
            URL.revokeObjectURL(url);
            setStatus(strings.failed);
            if (fileInput) fileInput.value = '';
        };

        image.src = url;
    };

    fileInput?.addEventListener('change', () => {
        decodeImageFile(fileInput.files && fileInput.files[0]);
    });

    startBtn?.addEventListener('click', () => {
        if (running) return;
        stoppedByUser = false;
        startCamera();
    });

    stopBtn?.addEventListener('click', () => {
        stoppedByUser = true;
        stopCamera();
        if (startText) startText.textContent = strings.start;
        setIdle(strings.idle, strings.privacy, 'qr_code_scanner');
        setStatus('');
    });

    torchBtn?.addEventListener('click', async () => {
        if (!track) return;
        try {
            torchOn = !torchOn;
            await track.applyConstraints({ advanced: [{ torch: torchOn }] });
            torchBtn.setAttribute('aria-pressed', String(torchOn));
            torchBtn.setAttribute('aria-label', torchOn ? strings.torch_off : strings.torch);
            torchBtn.title = torchOn ? strings.torch_off : strings.torch;
        } catch (error) {
            /* perangkat tidak mendukung lampu */
            torchBtn.classList.add('is-hidden');
        }
    });

    /* Pengetikan manual: kode dinormalisasi (huruf besar + pemisah 4-4)
       supaya petugas tidak perlu mengingat formatnya. */
    codeInput?.addEventListener('input', () => {
        const compact = codeInput.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        if (compact.length <= 8) {
            codeInput.value = compact.length > 4 ? compact.slice(0, 4) + '-' + compact.slice(4) : compact;
        } else {
            codeInput.value = codeInput.value.trim();
        }
        if (viaInput) viaInput.value = 'manual';
    });

    window.addEventListener('beforeunload', stopCamera);

    /* Tab ditinggalkan → kamera dimatikan (hemat baterai & privasi). Saat tab
       dikembalikan, kamera hidup lagi tanpa perlu menekan tombol apa pun. */
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopCamera();
        } else if (!cameraUnavailable && !stoppedByUser && !running) {
            startCamera();
        }
    });

    /* Kamera butuh konteks aman (https / localhost). */
    if (!window.isSecureContext) {
        cameraUnavailable = true;
        setIdle(strings.failed, strings.insecure, 'lock');
        setStatus(strings.insecure);
        openFallback(true);
        return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        cameraUnavailable = true;
        setIdle(strings.failed, strings.unsupported, 'no_photography');
        setStatus(strings.unsupported);
        openFallback(true);
        return;
    }

    /* BarcodeDetector hanya ada di sebagian browser (Android/ChromeOS).
       Di PC/laptop dipakai jsQR supaya kamera tetap bisa dipakai. */
    if ('BarcodeDetector' in window) {
        try {
            detector = new BarcodeDetector({ formats: ['qr_code'] });
        } catch (error) {
            detector = null;
        }
    }

    /* Kamera hidup otomatis: petugas tidak perlu mencari tombol dulu. */
    startCamera();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        initPickupScanner();
    });
}
