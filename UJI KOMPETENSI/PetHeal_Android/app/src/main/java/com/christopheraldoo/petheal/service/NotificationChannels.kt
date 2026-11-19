package com.christopheraldoo.petheal.service

import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.media.AudioAttributes
import android.net.Uri
import android.os.Build
import com.christopheraldoo.petheal.R

/**
 * Satu sumber definisi channel notifikasi.
 *
 * Channel dibuat saat aplikasi start — bukan saat push pertama tiba — supaya
 * suara dan importance sudah final sebelum notifikasi pertama. Android
 * mengunci properti channel setelah dibuat; mengganti nama/suara di versi
 * berikutnya butuh id baru.
 *
 * Antrian memakai bunyi dua nada pendek, permintaan layanan tiga nada, agar
 * pemilik bisa membedakan panggilan dokter dari penawaran layanan tanpa
 * membuka ponsel.
 */
object NotificationChannels {

    const val GENERAL = "petheal_notifications"
    const val BOOKINGS = "petheal_bookings"
    const val REMINDERS = "petheal_reminders"
    const val PAYMENTS = "petheal_payments"
    const val QUEUE = "petheal_queue"
    const val QUEUE_VOICE = "petheal_queue_voice"
    const val SERVICES = "petheal_services"

    fun ensureCreated(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return

        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        fun create(
            id: String,
            nameRes: Int,
            descriptionRes: Int,
            importance: Int,
            soundRes: Int? = null,
            vibration: LongArray? = null,
            silent: Boolean = false
        ) {
            if (manager.getNotificationChannel(id) != null) return
            val channel = NotificationChannel(id, context.getString(nameRes), importance).apply {
                description = context.getString(descriptionRes)
                if (silent) {
                    // Senyap eksplisit: suara digantikan TTS di aplikasi.
                    setSound(null, null)
                    enableVibration(false)
                } else if (soundRes != null) {
                    val uri = Uri.parse("android.resource://${context.packageName}/$soundRes")
                    setSound(
                        uri,
                        AudioAttributes.Builder()
                            .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                            .build()
                    )
                }
                vibration?.let {
                    enableVibration(true)
                    vibrationPattern = it
                }
            }
            manager.createNotificationChannel(channel)
        }

        create(
            GENERAL,
            R.string.notification_channel_general,
            R.string.notification_channel_general_desc,
            NotificationManager.IMPORTANCE_DEFAULT
        )
        create(
            BOOKINGS,
            R.string.notification_channel_bookings,
            R.string.notification_channel_bookings_desc,
            NotificationManager.IMPORTANCE_DEFAULT
        )
        create(
            REMINDERS,
            R.string.notification_channel_reminders,
            R.string.notification_channel_reminders_desc,
            NotificationManager.IMPORTANCE_HIGH
        )
        create(
            PAYMENTS,
            R.string.notification_channel_payments,
            R.string.notification_channel_payments_desc,
            NotificationManager.IMPORTANCE_HIGH
        )
        create(
            QUEUE,
            R.string.notification_channel_queue,
            R.string.notification_channel_queue_desc,
            NotificationManager.IMPORTANCE_HIGH,
            soundRes = R.raw.notif_queue,
            vibration = longArrayOf(0, 180, 90, 180)
        )
        // Kanal untuk panggilan yang sudah diumumkan lewat TTS saat aplikasi
        // terbuka: tetap tampil di layar, tetapi SENYAP agar tidak ada dua
        // suara (TTS + nada notifikasi) untuk satu pemanggilan.
        create(
            QUEUE_VOICE,
            R.string.notification_channel_queue_voice,
            R.string.notification_channel_queue_voice_desc,
            NotificationManager.IMPORTANCE_HIGH,
            silent = true
        )
        create(
            SERVICES,
            R.string.notification_channel_services,
            R.string.notification_channel_services_desc,
            NotificationManager.IMPORTANCE_DEFAULT,
            soundRes = R.raw.notif_service,
            vibration = longArrayOf(0, 120, 80, 120, 80, 200)
        )
    }
}
