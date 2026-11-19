package com.christopheraldoo.petheal.service

import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Scope berumur aplikasi untuk pekerjaan yang TIDAK boleh ikut mati ketika
 * `FirebaseMessagingService` selesai.
 *
 * `FirebaseMessagingService` berumur pendek: Android memanggil `onDestroy`
 * segera setelah `onMessageReceived` selesai. Coroutine yang memakai scope
 * service bisa dibatalkan sebelum sempat menampilkan notifikasi atau
 * menyelesaikan TTS — ini penyebab notifikasi antrian tidak muncul.
 */
@Singleton
class AppCoroutineScope @Inject constructor() {
    val scope = CoroutineScope(SupervisorJob() + Dispatchers.Default)
}
