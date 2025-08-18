package com.christopheraldoo.firebasetutorial.service

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import android.util.Log
import androidx.core.app.NotificationCompat
import com.christopheraldoo.firebasetutorial.MainActivity
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage

class MyFirebaseMesagingService : FirebaseMessagingService() {
    
    private val CHANNEL_ID = "firebase_notification_channel"
    
    override fun onMessageReceived(message: RemoteMessage) {
        super.onMessageReceived(message)
        
        Log.d("FCM_SERVICE", "Message received from: ${message.from}")
        
        // Ambil title dan body dari notification
        val title = message.notification?.title ?: "Firebase Notification"
        val body = message.notification?.body ?: "You have a new message"
        
        Log.d("FCM_SERVICE", "Title: $title, Body: $body")
        
        // Tampilkan notifikasi
        showNotification(title, body)
    }
    
    override fun onNewToken(token: String) {
        super.onNewToken(token)
        Log.d("FCM_SERVICE", "New token: $token")
    }
    
    private fun showNotification(title: String, body: String) {
        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        
        // Buat notification channel untuk Android 8+
        createNotificationChannel(notificationManager)
        
        // Intent untuk buka MainActivity saat notifikasi diklik
        val intent = Intent(this, MainActivity::class.java)
        val pendingIntent = PendingIntent.getActivity(
            this, 0, intent, 
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        
        // Build notifikasi dengan animasi keren
        val notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_info) // Icon notifikasi
            .setContentTitle(title)
            .setContentText(body)
            .setPriority(NotificationCompat.PRIORITY_HIGH) // Prioritas tinggi
            .setAutoCancel(true) // Hilang saat diklik
            .setContentIntent(pendingIntent)
            .setDefaults(NotificationCompat.DEFAULT_ALL) // Sound + Vibration
            .build()
        
        // Tampilkan notifikasi
        notificationManager.notify(1001, notification)
        
        Log.d("FCM_SERVICE", "Notification shown successfully")
    }
    
    private fun createNotificationChannel(notificationManager: NotificationManager) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                "Firebase Notifications", 
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Channel for Firebase push notifications"
                enableVibration(true)
                enableLights(true)
            }
            notificationManager.createNotificationChannel(channel)
            Log.d("FCM_SERVICE", "Notification channel created")
        }
    }
}