package com.christopheraldoo.sosialmediaapp.viewmodel

import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import com.christopheraldoo.sosialmediaapp.model.Notification
import com.christopheraldoo.sosialmediaapp.utils.DummyData

/**
 * ViewModel untuk Notifications
 */
class NotificationViewModel : ViewModel() {
    
    private val _notifications = MutableLiveData<List<Notification>>()
    val notifications: LiveData<List<Notification>> = _notifications
    
    private val _isLoading = MutableLiveData<Boolean>()
    val isLoading: LiveData<Boolean> = _isLoading
    
    private val _unreadCount = MutableLiveData<Int>()
    val unreadCount: LiveData<Int> = _unreadCount
    
    private var allNotifications = listOf<Notification>()
    private var currentFilter = "all"
    
    /**
     * Load notifications
     */
    fun loadNotifications() {
        _isLoading.value = true
        
        // Simulasi network delay
        android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
            allNotifications = DummyData.notifications
            filterNotifications(currentFilter)
            updateUnreadCount()
            _isLoading.value = false
        }, 500)
    }
    
    /**
     * Refresh notifications
     */
    fun refreshNotifications() {
        loadNotifications()
    }
    
    /**
     * Filter notifications berdasarkan tipe
     */
    fun filterNotifications(filterType: String) {
        currentFilter = filterType
        
        val filtered = when (filterType) {
            "mentions" -> allNotifications.filter { it.type == "mention" }
            "all" -> allNotifications
            else -> allNotifications
        }
        
        _notifications.value = filtered.sortedByDescending { it.timestamp }
    }
    
    /**
     * Mark notification as read
     */
    fun markAsRead(notificationId: String) {
        allNotifications = allNotifications.map { notification ->
            if (notification.id == notificationId) {
                notification.copy(isRead = true)
            } else {
                notification
            }
        }
        
        filterNotifications(currentFilter)
        updateUnreadCount()
    }
    
    /**
     * Mark all notifications as read
     */
    fun markAllAsRead() {
        allNotifications = allNotifications.map { it.copy(isRead = true) }
        filterNotifications(currentFilter)
        updateUnreadCount()
    }
    
    private fun updateUnreadCount() {
        val unread = allNotifications.count { !it.isRead }
        _unreadCount.value = unread
    }
}
