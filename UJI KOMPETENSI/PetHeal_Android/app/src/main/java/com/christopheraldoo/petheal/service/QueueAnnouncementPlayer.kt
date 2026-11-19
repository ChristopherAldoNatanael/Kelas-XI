package com.christopheraldoo.petheal.service

import android.app.NotificationManager
import android.content.Context
import android.media.AudioAttributes
import android.media.AudioManager
import android.os.Build
import android.speech.tts.TextToSpeech
import android.speech.tts.UtteranceProgressListener
import android.util.Log
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.coroutines.withTimeoutOrNull
import java.util.Locale
import java.util.UUID
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Pembaca pengumuman antrian dengan Text-to-Speech bahasa Indonesia.
 *
 * Prinsip:
 *  - Engine dibuat malas (baru saat ada panggilan) agar aplikasi tidak
 *    menahan resource TTS sepanjang waktu.
 *  - Jika suara `id-ID` tidak tersedia, method mengembalikan `false` dan
 *    pemanggil jatuh ke notifikasi channel (fallback), bukan memaksa suara
 *    berbahasa Inggris membaca teks Indonesia.
 *  - Menghormati silent mode, Do Not Disturb, dan volume notifikasi karena
 *    audio memakai `USAGE_NOTIFICATION_EVENT`.
 */
@Singleton
class QueueAnnouncementPlayer @Inject constructor(
    @ApplicationContext private val context: Context
) {
    private var engine: TextToSpeech? = null
    private var ready = false
    private var readyWaiter: CompletableDeferred<Boolean>? = null

    /**
     * Ucapkan teks. `true` berarti perangkat benar-benar mulai berbicara dan
     * selesai tanpa error — pemanggil boleh melewatkan notifikasi berbunyi
     * agar tidak ada suara ganda. `false` berarti pemanggil harus memakai
     * fallback notifikasi.
     */
    suspend fun speak(text: String): Boolean {
        if (text.isBlank()) return false
        if (!isDeviceAudible()) {
            Log.d(TAG, "Pengumuman dilewati: perangkat silent/DND")
            return false
        }

        return withContext(Dispatchers.Main) {
            val tts = ensureReady() ?: return@withContext false
            if (!applyIndonesianLanguage(tts)) {
                Log.w(TAG, "Suara bahasa Indonesia tidak tersedia di perangkat ini")
                return@withContext false
            }

            val utteranceId = UUID.randomUUID().toString()
            val finished = CompletableDeferred<Boolean>()

            tts.setOnUtteranceProgressListener(object : UtteranceProgressListener() {
                override fun onStart(id: String?) = Unit

                override fun onDone(id: String?) {
                    if (id == utteranceId) finished.complete(true)
                }

                @Deprecated("Deprecated di API 21, tetap wajib diimplementasikan")
                override fun onError(id: String?) {
                    if (id == utteranceId) finished.complete(false)
                }

                override fun onError(id: String?, errorCode: Int) {
                    if (id == utteranceId) finished.complete(false)
                }
            })

            val queued = tts.speak(text, TextToSpeech.QUEUE_FLUSH, null, utteranceId)
            if (queued != TextToSpeech.SUCCESS) {
                Log.w(TAG, "TTS menolak antrean ucapan (kode $queued)")
                return@withContext false
            }

            // Batas aman: kalimat pendek selesai < 10 detik. Timeout mencegah
            // service menggantung bila listener tidak pernah dipanggil.
            val done = withTimeoutOrNull(UTTERANCE_TIMEOUT_MS) { finished.await() } ?: false
            if (!done) Log.w(TAG, "Pengumuman tidak selesai tepat waktu")
            done
        }
    }

    private suspend fun ensureReady(): TextToSpeech? {
        if (ready) engine?.let { return it }

        val waiter = readyWaiter ?: CompletableDeferred<Boolean>().also { deferred ->
            readyWaiter = deferred
            val tts = TextToSpeech(context.applicationContext) { status ->
                ready = status == TextToSpeech.SUCCESS
                deferred.complete(ready)
            }
            engine = tts
        }

        val ok = withTimeoutOrNull(INIT_TIMEOUT_MS) { waiter.await() } ?: false
        if (!ok) {
            Log.w(TAG, "Inisialisasi TTS gagal/timeout")
            shutdown()
            return null
        }
        return engine
    }

    /**
     * Pilih locale Indonesia + voice kualitas terbaik yang tersedia offline.
     * Mengembalikan `false` bila perangkat belum punya voice Indonesia.
     */
    private fun applyIndonesianLanguage(tts: TextToSpeech): Boolean {
        val locale = Locale("id", "ID")
        val availability = runCatching { tts.isLanguageAvailable(locale) }.getOrDefault(TextToSpeech.LANG_NOT_SUPPORTED)
        val supported = availability == TextToSpeech.LANG_AVAILABLE ||
            availability == TextToSpeech.LANG_COUNTRY_AVAILABLE ||
            availability == TextToSpeech.LANG_COUNTRY_VAR_AVAILABLE
        if (!supported) return false

        val languageSet = runCatching { tts.setLanguage(locale) }.getOrDefault(TextToSpeech.LANG_NOT_SUPPORTED)
        if (languageSet < TextToSpeech.LANG_AVAILABLE) return false

        runCatching {
            tts.voices
                ?.filter { it.locale.language == locale.language && !it.isNetworkConnectionRequired }
                ?.maxByOrNull { it.quality }
                ?.let { best -> tts.voice = best }
        }

        tts.setSpeechRate(SPEECH_RATE)
        tts.setPitch(SPEECH_PITCH)
        tts.setAudioAttributes(
            AudioAttributes.Builder()
                // Volume notifikasi + otomatis menghormati silent/DND.
                .setUsage(AudioAttributes.USAGE_NOTIFICATION_EVENT)
                .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
                .build()
        )
        return true
    }

    /** Silent mode / DND berarti pengumuman suara tidak boleh berbunyi. */
    private fun isDeviceAudible(): Boolean {
        val audio = context.getSystemService(Context.AUDIO_SERVICE) as? AudioManager
        if (audio != null && audio.ringerMode != AudioManager.RINGER_MODE_NORMAL) return false

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
            val filter = runCatching { manager?.currentInterruptionFilter }.getOrNull()
            when (filter) {
                NotificationManager.INTERRUPTION_FILTER_NONE,
                NotificationManager.INTERRUPTION_FILTER_ALARMS,
                NotificationManager.INTERRUPTION_FILTER_PRIORITY -> return false
            }
        }
        return true
    }

    /** Lepas engine yang gagal siap agar percobaan berikutnya mulai bersih. */
    private fun shutdown() {
        runCatching { engine?.stop() }
        runCatching { engine?.shutdown() }
        engine = null
        ready = false
        readyWaiter = null
    }

    private companion object {
        const val TAG = "QueueAnnouncement"
        const val INIT_TIMEOUT_MS = 5_000L
        const val UTTERANCE_TIMEOUT_MS = 30_000L
        const val SPEECH_RATE = 0.95f
        const val SPEECH_PITCH = 1.02f
    }
}
