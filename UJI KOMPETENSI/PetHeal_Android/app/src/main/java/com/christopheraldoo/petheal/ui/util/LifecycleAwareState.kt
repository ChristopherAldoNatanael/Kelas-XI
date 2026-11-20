package com.christopheraldoo.petheal.ui.util

import androidx.compose.runtime.Composable
import androidx.compose.runtime.State
import androidx.compose.runtime.produceState
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.repeatOnLifecycle
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.StateFlow
import com.christopheraldoo.petheal.ui.util.collectAsStateLifecycleAware

/**
 * Pengganti `collectAsState()` yang berhenti mengoleksi saat layar tidak lagi
 * terlihat.
 *
 * **Masalah yang diselesaikan.** `collectAsState()` mengoleksi Flow selama
 * composable masih terpasang di komposisi — termasuk ketika aplikasi sudah
 * masuk background. Artinya setiap perubahan DataStore/API tetap memicu
 * recomposition dan penulisan state di layar yang tidak dilihat siapa pun.
 * Itu pemakaian CPU dan baterai yang sia-sia, dan pada jaringan lambat bisa
 * menumpuk pembaruan yang tidak pernah tampil.
 *
 * Dengan [repeatOnLifecycle] koleksi dihentikan saat lifecycle turun di bawah
 * `STARTED` dan dilanjutkan lagi saat layar kembali terlihat. Nilai terakhir
 * tetap dipertahankan, jadi layar tidak berkedip saat kembali dari background.
 *
 * Catatan: helper ini ditulis sendiri (bukan memakai
 * `lifecycle-runtime-compose`) karena versi yang tersedia (2.8.x) memerlukan
 * Compose 1.6+, sedangkan proyek ini memakai Compose BOM 2023.10.01 (1.5.x).
 */
@Composable
fun <T> StateFlow<T>.collectAsStateLifecycleAware(): State<T> =
    collectAsStateLifecycleAware(initial = value)

@Composable
fun <T> Flow<T>.collectAsStateLifecycleAware(initial: T): State<T> {
    val lifecycleOwner = LocalLifecycleOwner.current
    return produceState(initialValue = initial, key1 = this, key2 = lifecycleOwner) {
        lifecycleOwner.repeatOnLifecycle(Lifecycle.State.STARTED) {
            this@collectAsStateLifecycleAware.collect { newValue -> value = newValue }
        }
    }
}
