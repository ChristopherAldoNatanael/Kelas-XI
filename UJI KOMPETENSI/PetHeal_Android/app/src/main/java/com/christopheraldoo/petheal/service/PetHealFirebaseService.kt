package com.christopheraldoo.petheal.service

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat
import com.christopheraldoo.petheal.MainActivity
import com.christopheraldoo.petheal.R
import com.christopheraldoo.petheal.data.model.AppNotification
import com.christopheraldoo.petheal.data.repository.DeviceTokenRepository
import com.christopheraldoo.petheal.data.repository.NotificationRepository
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

        val title = message.data["title"] ?: message.notification?.title ?: "PetHeal"
        val body = when {
            message.data["body"] != null -> message.data["body"].orEmpty()
            type == "booking_status" -> "Status booking $petName untuk $date telah diperbarui ke $status."
            type == "booking_reminder" -> "Jadwal $petName pada $date akan segera dimulai."
            type == "vaccination_reminder" -> "Saatnya kunjungan berikutnya untuk $petName pada $date."
            else -> message.notification?.body ?: ""
        }

        serviceScope.launch {
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
        }

        showNotification(
            title = title,
            body = body,
            type = type,
            bookingId = bookingId,
            medicalRecordId = medicalRecordId,
            petId = petId,
            doctorId = doctorId
        )
    }

    private fun showNotification(
        title: String,
        body: String,
        type: String,
        bookingId: Int?,
        medicalRecordId: Int?,
        petId: Int?,
        doctorId: Int?
    ) {
        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        val channel = channelFor(type)

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O &&
            notificationManager.getNotificationChannel(channel.id) == null
        ) {
            val notificationChannel = NotificationChannel(
                channel.id,
                getString(channel.nameRes),
                channel.importance
            ).apply {
                description = getString(channel.descriptionRes)
            }
            notificationManager.createNotificationChannel(notificationChannel)
        }

        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra("navigate_to", "notification_target")
            putExtra("notification_type", type)
            bookingId?.let { putExtra("booking_id", it) }
            medicalRecordId?.let { putExtra("medical_record_id", it) }
            petId?.let { putExtra("pet_id", it) }
            doctorId?.let { putExtra("doctor_id", it) }
        }

        // Stable id per entity: a status update replaces the previous card
        // instead of stacking duplicates in the tray.
        val notificationId = when {
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

        val builder = NotificationCompat.Builder(this, channel.id)
            .setSmallIcon(R.drawable.ic_notification)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)

        if (channel.id == CHANNEL_REMINDERS) {
            builder.setCategory(NotificationCompat.CATEGORY_REMINDER)
        }

        notificationManager.notify(notificationId, builder.build())
    }

    private data class NotificationChannelSpec(
        val id: String,
        val nameRes: Int,
        val descriptionRes: Int,
        val importance: Int
    )

    private fun channelFor(type: String): NotificationChannelSpec {
        return when (type) {
            "payment", "payment_status", "payment_reminder", "payment_due" ->
                NotificationChannelSpec(
                    CHANNEL_PAYMENTS,
                    R.string.notification_channel_payments,
                    R.string.notification_channel_payments_desc,
                    NotificationManager.IMPORTANCE_HIGH
                )

            "booking_reminder", "vaccination_reminder", "follow_up_reminder", "reminder" ->
                NotificationChannelSpec(
                    CHANNEL_REMINDERS,
                    R.string.notification_channel_reminders,
                    R.string.notification_channel_reminders_desc,
                    NotificationManager.IMPORTANCE_HIGH
                )

            "booking", "booking_status" ->
                NotificationChannelSpec(
                    CHANNEL_BOOKINGS,
                    R.string.notification_channel_bookings,
                    R.string.notification_channel_bookings_desc,
                    NotificationManager.IMPORTANCE_DEFAULT
                )

            else ->
                NotificationChannelSpec(
                    CHANNEL_GENERAL,
                    R.string.notification_channel_general,
                    R.string.notification_channel_general_desc,
                    NotificationManager.IMPORTANCE_DEFAULT
                )
        }
    }

    private companion object {
        const val CHANNEL_BOOKINGS = "petheal_bookings"
        const val CHANNEL_REMINDERS = "petheal_reminders"
        const val CHANNEL_PAYMENTS = "petheal_payments"
        const val CHANNEL_GENERAL = "petheal_notifications"
    }
}
