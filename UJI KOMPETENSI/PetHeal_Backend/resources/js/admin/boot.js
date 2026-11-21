/**
 * Konfigurasi runtime konsol admin.
 *
 * Blade menulis satu blok JSON (`<script type="application/json" id="admin-boot">`)
 * sehingga modul JavaScript tidak perlu menyimpan teks terjemahan sendiri.
 * Semua label dibaca dari katalog bahasa server; modul hanya menyusun DOM.
 *
 * @returns {{ flashes: Array<{type: string, message: string}>, labels: object, routes: object }}
 */
function readBoot() {
    const node = document.getElementById('admin-boot');
    const fallback = { flashes: [], labels: {}, routes: {} };

    if (!node) {
        return fallback;
    }

    try {
        const parsed = JSON.parse(node.textContent || '{}');

        return {
            flashes: Array.isArray(parsed.flashes) ? parsed.flashes : [],
            labels: parsed.labels || {},
            routes: parsed.routes || {},
        };
    } catch (error) {
        // JSON rusak tidak boleh mematikan seluruh konsol admin.
        console.error('admin-boot tidak dapat dibaca', error);

        return fallback;
    }
}

export const boot = readBoot();

/**
 * Label dengan cadangan aman: kunci yang lupa ditambahkan ke katalog bahasa
 * akan tampil sebagai teks kosong, bukan "undefined" di layar admin.
 *
 * @param {string} key
 * @param {string} fallback
 */
export function label(key, fallback = '') {
    return typeof boot.labels[key] === 'string' ? boot.labels[key] : fallback;
}
