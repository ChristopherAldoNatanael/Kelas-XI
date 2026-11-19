package com.christopheraldoo.petheal.service

import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import com.christopheraldoo.petheal.MainActivity
import com.christopheraldoo.petheal.R
import com.christopheraldoo.petheal.data.model.AppNotification
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.repository.DeviceTokenRepository
import com.christopheraldoo.petheal.data.repository.NotificationRepository
import com.christopheraldoo.petheal.util.QueueAnnouncement
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.launch
import java.util.UUID
import javax.inject.Inject

@AndroidEntryPoint
class PetHealFirebaseService : FirebaseMessagingService() {

    @Inject lateinit var deviceTokenRepository: DeviceTokenRepository
    @Inject lateinit var notificationRepository: NotificationRepository
    @Inject lateinit var preferencesManager: PreferencesManager
    @Inject lateinit var queueAnnouncementPlayer: QueueAnnouncementPlayer
    @Inject lateinit var queueAnnouncementLedger: QueueAnnouncementLedger
    @Inject lateinit var appCoroutineScope: AppCoroutineScope

    private val serviceScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    override fun onDestroy() {
        super.onDestroy()
        serviceScope.cancel()
    }

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        serviceScope.launch {
            deviceTokenRepository.saveDeviceToken(token, "android")
        }
    }

    override fun onMessageReceived(message: RemoteMessage) {
        super.onMessageReceived(message)

        val type = message.data["type"] ?: "general"
        val petName = message.data["pet_name"]
        val status = message.data["status"]
        val date = message.data["date"] ?: message.data["next_visit"]
        val bookingId = message.data["booking_id"]?.toIntOrNull()
        val medicalRecordId = message.data["medical_record_id"]?.toIntOrNull()
        val petId = message.data["pet_id"]?.toIntOrNull()
        val doctorId = message.data["doctor_id"]?.toIntOrNull()
        val serviceRequestId = message.data["service_request_id"]?.toIntOrNull()
        val queueLabel = message.data["queue_label"]
        val queueEvent = message.data["queue_event"]
        val queueEventId = message.data["queue_event_id"]
        val doctorName = message.data["doctor_name"]
        val room = message.data["room"]

        // Hanya panggilan dokter (transisi status -> called) yang diumumkan.
        // Check-in, pemeriksaan dimulai, atau refresh data tidak berbunyi.
        val isQueueCall = type == "queue_called" &&
            (queueEvent == "called" || message.data["queue_status"] == "called")

        val title = message.data["title"] ?: message.notification?.title ?: "PetHeal"
        // Backend selalu mengirim `body` di dalam data; `notification.body`
        // hanya cadangan untuk pesan yang dibuat dari konsol FCM. Cabang
        // penyusun kalimat per tipe dulu tidak pernah tercapai karena
        // diperiksa setelah `data["body"]`, jadi dihapus agar alurnya jelas.
        val body = message.data["body"]?.takeIf { it.isNotBlank() }
            ?: message.notification?.body
            ?: ""

        // Panggilan dokter harus tampil SEKETIKA. Service ini berumur pendek:
        // begitu onMessageReceived selesai, onDestroy memanggil cancel() dan
        // coroutine di serviceScope ikut mati. Karena itu keputusan suara dan
        // pemanggilan showNotification dilakukan sinkron di sini, bukan di
        // dalam launch.
        val voiceCandidate = isQueueCall &&
            AppVisibilityTracker.isForeground &&
            preferencesManager.isQueueVoiceEnabledNow()

        // Idempoten: satu peristiwa antrian hanya diumumkan (suara) sekali.
        // Klaim juga dilakukan saat background agar pengiriman ulang FCM
        // tidak tiba-tiba berbunyi ketika aplikasi dibuka kemudian.
        //
        // Notifikasi TETAP diposting meski peristiwa ini duplikat: id
        // notifikasi stabil per booking, jadi posting ulang hanya mengganti
        // kartu yang sama. Dulu klaim duplikat langsung `return` sehingga
        // notifikasi bisa hilang sama sekali bila kiriman pertama gagal
        // ditampilkan (mis. proses aplikasi mati sebelum sempat notify).
        val alreadyAnnounced = isQueueCall && !queueEventId.isNullOrBlank() &&
            !queueAnnouncementLedger.claim(queueEventId)

        if (alreadyAnnounced) {
            Log.d(TAG, "Duplikat event antrian: $queueEventId — suara dilewati")
        }

        showNotification(
            title = title,
            body = body,
            type = type,
            bookingId = bookingId,
            medicalRecordId = medicalRecordId,
            petId = petId,
            doctorId = doctorId,
            serviceRequestId = serviceRequestId,
            silentQueueCall = voiceCandidate || alreadyAnnounced
        )

        // Riwayat dan TTS tidak boleh bergantung pada umur service.
        appCoroutineScope.scope.launch {
            notificationRepository.addNotification(
                AppNotification(
                    id = UUID.randomUUID().toString(),
                    title = title,
                    body = body,
                    type = type,
                    petName = petName,
                    status = status,
                    date = date,
                    bookingId = bookingId,
                    medicalRecordId = medicalRecordId,
                    petId = petId,
                    doctorId = doctorId,
                    timestamp = System.currentTimeMillis(),
                    isRead = false
                )
            )

            if (!voiceCandidate || alreadyAnnounced) return@launch

            // TTS hanya saat aplikasi terlihat. Bila engine/locale Indonesia
            // tidak tersedia, atau perangkat silent/DND, notifikasi yang tadi
            // senyap diposting ulang di channel bersuara sebagai fallback.
            val spoken = queueAnnouncementPlayer.speak(
                QueueAnnouncement.buildText(queueLabel, doctorName, room)
            )
            if (!spoken) {
                showNotification(
                    title = title,
                    body = body,
                    type = type,
                    bookingId = bookingId,
                    medicalRecordId = medicalRecordId,
                    petId = petId,
                    doctorId = doctorId,
                    serviceRequestId = serviceRequestId,
                    silentQueueCall = false
                )
            }
        }
    }

    private fun showNotification(
        title: String,
        body: String,
        type: String,
        bookingId: Int?,
        medicalRecordId: Int?,
        petId: Int?,
        doctorId: Int?,
        serviceRequestId: Int?,
        silentQueueCall: Boolean = false
    ) {
        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        // Tanpa izin/channel aktif, `notify()` gagal tanpa error apa pun —
        // gejalanya "notifikasi tidak muncul" padahal pengiriman sukses.
        // Catat supaya penyebabnya terlihat di logcat, bukan ditebak.
        if (!NotificationManagerCompat.from(this).areNotificationsEnabled()) {
            Log.w(TAG, "Notifikasi diblokir pengguna (izin/channel) — $type tidak akan tampil")
        }
        // Panggilan yang sudah diumumkan TTS memakai channel senyap; notifikasi
        // tetap tampil tetapi tidak memutar nada kedua.
        val channelId = if (silentQueueCall) {
            NotificationChannels.QUEUE_VOICE
        } else {
            channelIdFor(type)
        }

        // Idempoten: channel biasanya sudah dibuat di Application, tetapi
        // perangkat yang memperbarui aplikasi tanpa restart tetap aman.
        NotificationChannels.ensureCreated(this)

        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra("navigate_to", "notification_target")
            putExtra("notification_type", type)
            bookingId?.let { putExtra("booking_id", it) }
            medicalRecordId?.let { putExtra("medical_record_id", it) }
            petId?.let { putExtra("pet_id", it) }
            doctorId?.let { putExtra("doctor_id", it) }
            serviceRequestId?.let { putExtra("service_request_id", it) }
        }

        // Stable id per entity: a status update replaces the previous card
        // instead of stacking duplicates in the tray.
        val notificationId = when {
            serviceRequestId != null -> 40_000 + serviceRequestId
            bookingId != null -> 10_000 + bookingId
            medicalRecordId != null -> 20_000 + medicalRecordId
            petId != null -> 30_000 + petId
            else -> System.currentTimeMillis().toInt()
        }

        val pendingIntent = PendingIntent.getActivity(
            this,
            notificationId,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val builder = NotificationCompat.Builder(this, channelId)
            .setSmallIcon(R.drawable.ic_notification)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)

        when (channelId) {
            NotificationChannels.REMINDERS -> builder.setCategory(NotificationCompat.CATEGORY_REMINDER)
            NotificationChannels.QUEUE, NotificationChannels.QUEUE_VOICE ->
                builder.setCategory(NotificationCompat.CATEGORY_CALL)
            NotificationChannels.SERVICES -> builder.setCategory(NotificationCompat.CATEGORY_MESSAGE)
        }

        notificationManager.notify(notificationId, builder.build())
    }

    /**
     * Channel per jenis notifikasi. Definisi channel (nama, suara, importance)
     * hanya ada di [NotificationChannels]; di sini cukup memilih id-nya.
     */
    private fun channelIdFor(type: String): String = when (type) {
        "payment", "payment_status", "payment_reminder", "payment_due" ->
            CHANNEL_PAYMENTS

        "booking_reminder", "vaccination_reminder", "follow_up_reminder", "reminder" ->
            CHANNEL_REMINDERS

        "booking", "booking_status" -> CHANNEL_BOOKINGS

        // Antrian: panggilan dokter butuh kanal sendiri (bunyi berbeda).
        "queue", "queue_called", "queue_checked_in",
        "queue_consultation_started", "queue_consultation_completed" ->
            NotificationChannels.QUEUE

        "service_request" -> NotificationChannels.SERVICES

        else -> CHANNEL_GENERAL
    }

    private companion object {
        const val TAG = "PetHealFCM"
        const val CHANNEL_BOOKINGS = NotificationChannels.BOOKINGS
        const val CHANNEL_REMINDERS = NotificationChannels.REMINDERS
        const val CHANNEL_PAYMENTS = NotificationChannels.PAYMENTS
        const val CHANNEL_GENERAL = NotificationChannels.GENERAL
    }
}
