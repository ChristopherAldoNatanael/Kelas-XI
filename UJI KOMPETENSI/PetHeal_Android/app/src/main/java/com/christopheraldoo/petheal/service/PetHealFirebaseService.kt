package com.christopheraldoo.petheal.service

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat
import com.christopheraldoo.petheal.R
import com.christopheraldoo.petheal.MainActivity
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.AppNotification
import com.christopheraldoo.petheal.data.repository.DeviceTokenRepository
import com.christopheraldoo.petheal.data.repository.NotificationRepository
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import java.util.UUID
import javax.inject.Inject

@AndroidEntryPoint
class PetHealFirebaseService : FirebaseMessagingService() {

    @Inject lateinit var deviceTokenRepository: DeviceTokenRepository
    @Inject lateinit var preferencesManager: PreferencesManager
    @Inject lateinit var notificationRepository: NotificationRepository

    private val serviceScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        serviceScope.launch {
            deviceTokenRepository.saveDeviceToken(token, "android")
        }
    }

    override fun onMessageReceived(message: RemoteMessage) {
        super.onMessageReceived(message)

        // ── Build display strings ──────────────────────────────────────────
        val type    = message.data["type"] ?: "general"
        val petName = message.data["pet_name"]
        val status  = message.data["status"]
        val date    = message.data["date"]

        val (title, body) = when {
            // Prefer data payload fields over notification object
            message.data.isNotEmpty() -> {
                val t = message.data["title"] ?: message.notification?.title ?: "PetHeal"
                val b = when (type) {
                    "booking_status"      -> "Booking for $petName on $date has been $status"
                    "booking_reminder"    -> "You have an appointment for $petName on $date"
                    "vaccination_reminder"-> "Time to vaccinate $petName! Next visit: $date"
                    else                  -> message.data["body"] ?: message.notification?.body ?: ""
                }
                Pair(t, b)
            }
            message.notification != null -> Pair(
                message.notification!!.title ?: "PetHeal",
                message.notification!!.body  ?: ""
            )
            else -> Pair("PetHeal", "")
        }

        // ── Persist to local DataStore ─────────────────────────────────────
        serviceScope.launch {
            notificationRepository.addNotification(
                AppNotification(
                    id        = UUID.randomUUID().toString(),
                    title     = title,
                    body      = body,
                    type      = type,
                    petName   = petName,
                    status    = status,
                    date      = date,
                    timestamp = System.currentTimeMillis(),
                    isRead    = false
                )
            )
        }

        // ── Show system notification ───────────────────────────────────────
        showNotification(title = title, body = body)
    }

    private fun showNotification(title: String, body: String) {
        val channelId = "petheal_notifications"
        val notificationManager =
            getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                channelId,
                "PetHeal Notifications",
                NotificationManager.IMPORTANCE_HIGH
            ).apply { description = "Notifications for booking updates and reminders" }
            notificationManager.createNotificationChannel(channel)
        }

        // Open app → notifications screen via intent extra
        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra("navigate_to", "notifications")
        }
        val pendingIntent = PendingIntent.getActivity(
            this, 0, intent,            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val notification = NotificationCompat.Builder(this, channelId)
            .setSmallIcon(R.drawable.ic_notification)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .build()

        notificationManager.notify(System.currentTimeMillis().toInt(), notification)
    }
}
