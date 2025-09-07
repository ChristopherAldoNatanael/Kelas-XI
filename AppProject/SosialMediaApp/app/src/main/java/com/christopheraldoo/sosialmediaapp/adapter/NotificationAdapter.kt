package com.christopheraldoo.sosialmediaapp.adapter

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.christopheraldoo.sosialmediaapp.databinding.ItemNotificationBinding
import com.christopheraldoo.sosialmediaapp.model.Notification
import com.christopheraldoo.sosialmediaapp.utils.Utils

/**
 * Adapter untuk RecyclerView notifikasi
 */
class NotificationAdapter(
    private val onNotificationClick: (Notification) -> Unit,
    private val onUserClick: (String) -> Unit
) : ListAdapter<Notification, NotificationAdapter.NotificationViewHolder>(NotificationDiffCallback()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): NotificationViewHolder {
        val binding = ItemNotificationBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return NotificationViewHolder(binding)
    }

    override fun onBindViewHolder(holder: NotificationViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class NotificationViewHolder(
        private val binding: ItemNotificationBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(notification: Notification) {
            with(binding) {
                // Set notification icon berdasarkan tipe
                when (notification.type) {
                    "like" -> ivNotificationIcon.setImageResource(android.R.drawable.btn_star_big_on)
                    "retweet" -> ivNotificationIcon.setImageResource(android.R.drawable.ic_menu_rotate)
                    "reply" -> ivNotificationIcon.setImageResource(android.R.drawable.ic_menu_send)
                    "follow" -> ivNotificationIcon.setImageResource(android.R.drawable.ic_menu_add)
                    "mention" -> ivNotificationIcon.setImageResource(android.R.drawable.ic_menu_call)
                    else -> ivNotificationIcon.setImageResource(android.R.drawable.ic_dialog_info)
                }

                // Set notification message
                tvNotificationMessage.text = notification.message
                
                // Set timestamp
                tvTimestamp.text = Utils.getRelativeTime(notification.timestamp)
                
                // Set read status (unread notifications lebih terang)
                val alpha = if (notification.isRead) 0.7f else 1.0f
                root.alpha = alpha
                
                // Set user info
                tvUserName.text = notification.fromUser.displayName
                tvUsername.text = "@${notification.fromUser.username}"
                
                // Click listeners
                root.setOnClickListener {
                    onNotificationClick(notification)
                }
                
                ivProfilePicture.setOnClickListener {
                    onUserClick(notification.fromUser.id)
                }
                
                tvUserName.setOnClickListener {
                    onUserClick(notification.fromUser.id)
                }
            }
        }
    }

    class NotificationDiffCallback : DiffUtil.ItemCallback<Notification>() {
        override fun areItemsTheSame(oldItem: Notification, newItem: Notification): Boolean {
            return oldItem.id == newItem.id
        }

        override fun areContentsTheSame(oldItem: Notification, newItem: Notification): Boolean {
            return oldItem == newItem
        }
    }
}
