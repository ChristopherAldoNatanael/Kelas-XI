/**
 * Pemilih titik lokasi klinik untuk admin non-teknis.
 *
 * Alur yang dijamin: admin mengetik alamat → menekan "Buka peta" → mengklik
 * titik klinik di peta → koordinat tersimpan otomatis. Tidak ada nilai karangan:
 * satu-satunya sumber koordinat adalah titik yang benar-benar dipilih,
 * tautan Google Maps yang dibagikan, atau koordinat yang sudah tersimpan.
 *
 * Degradasi: bila peta gagal dimuat (offline/CSP), tautan Google Maps dan
 * input koordinat tetap berfungsi sehingga form tidak pernah buntu.
 */
const MAP_LOAD_TIMEOUT_MS = 12000;
const LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
const LEAFLET_JS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

/** Titik pusat awal saat klinik belum punya koordinat: Indonesia, bukan lokasi klinik. */
const DEFAULT_VIEW = { lat: -2.5489, lng: 118.0149, zoom: 5 };

document.addEventListener('DOMContentLoaded', () => {
    initPinLocation();
});

function initPinLocation() {
    const root = document.querySelector('[data-pin-location]');
    if (!root) return;

    const latInput = root.querySelector('[data-lat]');
    const lngInput = root.querySelector('[data-lng]');
    const addressInput = root.querySelector('[data-address]');
    const searchInput = root.querySelector('[data-search]');
    const mapEl = root.querySelector('[data-map]');
    const mapWrap = root.querySelector('[data-map-wrap]');
    const openBtn = root.querySelector('[data-open-map]');
    const clearBtn = root.querySelector('[data-clear]');
    const gmapsLink = root.querySelector('[data-gmaps-link]');
    const statusEl = root.querySelector('[data-status]');
    const coordsEl = root.querySelector('[data-coords]');
    const noticeEl = root.querySelector('[data-notice]');

    let map = null;
    let marker = null;

    const readCoords = () => {
        const lat = Number.parseFloat(latInput?.value ?? '');
        const lng = Number.parseFloat(lngInput?.value ?? '');
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
        if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
        return { lat, lng };
    };

    const updateSummary = () => {
        const coords = readCoords();
        if (coords) {
            if (statusEl) {
                statusEl.textContent = root.dataset.msgReady || 'Lokasi tersimpan';
                statusEl.className = 'clinic-pin__status clinic-pin__status--ok';
            }
            if (coordsEl) {
                coordsEl.textContent = `${coords.lat.toFixed(7)}, ${coords.lng.toFixed(7)}`;
                coordsEl.hidden = false;
            }
            if (gmapsLink) {
                gmapsLink.href = `https://www.google.com/maps/search/?api=1&query=${coords.lat},${coords.lng}`;
                gmapsLink.hidden = false;
            }
            if (clearBtn) clearBtn.hidden = false;
        } else {
            if (statusEl) {
                statusEl.textContent = root.dataset.msgEmpty || 'Lokasi klinik belum ditentukan';
                statusEl.className = 'clinic-pin__status clinic-pin__status--empty';
            }
            if (coordsEl) coordsEl.hidden = true;
            if (gmapsLink) gmapsLink.hidden = true;
            if (clearBtn) clearBtn.hidden = true;
        }
    };

    const notify = (message, tone = 'info') => {
        if (!noticeEl) return;
        noticeEl.textContent = message;
        noticeEl.className = `clinic-pin__notice clinic-pin__notice--${tone}`;
        noticeEl.hidden = !message;
        if (message) {
            window.clearTimeout(notify._t);
            notify._t = window.setTimeout(() => {
                noticeEl.hidden = true;
            }, 6000);
        }
    };

    const setPoint = (lat, lng, { moveMap = true, overwriteInputs = true } = {}) => {
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        // Jangan menimpa input yang sedang diketik admin (mode manual).
        const typing = document.activeElement === latInput || document.activeElement === lngInput;
        if (overwriteInputs && !typing) {
            if (latInput) latInput.value = lat.toFixed(7);
            if (lngInput) lngInput.value = lng.toFixed(7);
        }
        updateSummary();
        if (map && window.L) {
            const point = [lat, lng];
            if (marker) {
                marker.setLatLng(point);
            } else {
                marker = window.L.marker(point, { draggable: true }).addTo(map);
                marker.on('dragend', () => {
                    const p = marker.getLatLng();
                    setPoint(p.lat, p.lng, { moveMap: false });
                });
            }
            if (moveMap) map.setView(point, Math.max(map.getZoom(), 16));
        }
    };

    const loadLeaflet = () => new Promise((resolve, reject) => {
        if (window.L) return resolve(window.L);

        if (!document.querySelector(`link[href="${LEAFLET_CSS}"]`)) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = LEAFLET_CSS;
            document.head.appendChild(link);
        }

        const script = document.createElement('script');
        script.src = LEAFLET_JS;
        script.async = true;
        const timer = window.setTimeout(() => reject(new Error('timeout')), MAP_LOAD_TIMEOUT_MS);
        script.onload = () => {
            window.clearTimeout(timer);
            window.L ? resolve(window.L) : reject(new Error('missing'));
        };
        script.onerror = () => {
            window.clearTimeout(timer);
            reject(new Error('load-failed'));
        };
        document.head.appendChild(script);
    });

    const geocodeAddress = async (query) => {
        const endpoint = new URL('https://nominatim.openstreetmap.org/search');
        endpoint.searchParams.set('q', query);
        endpoint.searchParams.set('format', 'jsonv2');
        endpoint.searchParams.set('limit', '1');
        endpoint.searchParams.set('accept-language', 'id');
        // Nominatim meminta identifikasi aplikasi; tanpa API key, tanpa biaya.
        const response = await fetch(endpoint.toString(), {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error('geocode-failed');
        const rows = await response.json();
        if (!Array.isArray(rows) || rows.length === 0) return null;
        return {
            lat: Number.parseFloat(rows[0].lat),
            lng: Number.parseFloat(rows[0].lon),
            label: rows[0].display_name,
        };
    };

    const ensureMap = async () => {
        await loadLeaflet();
        if (map) return map;

        const start = readCoords() || DEFAULT_VIEW;
        map = window.L.map(mapEl, {
            center: [start.lat, start.lng],
            zoom: start.zoom || 16,
            attributionControl: true,
        });
        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        }).addTo(map);

        map.on('click', (event) => {
            setPoint(event.latlng.lat, event.latlng.lng, { moveMap: false });
        });

        const existing = readCoords();
        if (existing) {
            marker = window.L.marker([existing.lat, existing.lng], { draggable: true }).addTo(map);
            marker.on('dragend', () => {
                const p = marker.getLatLng();
                setPoint(p.lat, p.lng, { moveMap: false });
            });
        }
        return map;
    };

    const openMap = async () => {
        const query = (searchInput?.value || addressInput?.value || '').trim();
        if (mapWrap) mapWrap.hidden = false;
        if (openBtn) openBtn.disabled = true;
        try {
            await ensureMap();
            // Tanpa koordinat tersimpan, pusatkan peta ke alamat klinik yang
            // sudah diisi admin — bukan ke koordinat karangan.
            if (!readCoords() && query) {
                notify(root.dataset.msgSearching || 'Mencari alamat klinik…');
                const found = await geocodeAddress(query);
                if (found) {
                    map.setView([found.lat, found.lng], 16);
                    notify(
                        (root.dataset.msgChoose || 'Klik titik klinik di peta untuk menyimpan lokasi.'),
                        'info'
                    );
                } else {
                    notify(root.dataset.msgNotFound || 'Alamat tidak ditemukan. Geser peta lalu klik titik kliniknya.', 'warn');
                }
            } else if (!readCoords()) {
                notify(root.dataset.msgChoose || 'Klik titik klinik di peta untuk menyimpan lokasi.');
            }
            // Peta butuh ukuran ulang setelah kontainer ditampilkan.
            window.setTimeout(() => map && map.invalidateSize(), 120);
        } catch (error) {
            notify(
                root.dataset.msgMapFail ||
                    'Peta tidak bisa dimuat. Gunakan tautan Google Maps atau isi koordinat manual di bawah.',
                'warn'
            );
        } finally {
            if (openBtn) openBtn.disabled = false;
        }
    };

    openBtn?.addEventListener('click', openMap);

    clearBtn?.addEventListener('click', () => {
        if (latInput) latInput.value = '';
        if (lngInput) lngInput.value = '';
        if (map && marker) {
            map.removeLayer(marker);
            marker = null;
        }
        updateSummary();
        notify(root.dataset.msgCleared || 'Lokasi klinik dihapus dari form.', 'info');
    });

    latInput?.addEventListener('change', updateSummary);
    lngInput?.addEventListener('change', updateSummary);

    updateSummary();
}
