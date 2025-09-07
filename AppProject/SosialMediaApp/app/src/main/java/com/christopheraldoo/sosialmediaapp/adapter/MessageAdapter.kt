package com.christopheraldoo.sosialmediaapp.adapter

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.christopheraldoo.sosialmediaapp.databinding.ItemConversationBinding
import com.christopheraldoo.sosialmediaapp.model.Conversation
import com.christopheraldoo.sosialmediaapp.utils.Utils

/**
 * Adapter untuk RecyclerView daftar percakapan/conversations
 */
class MessageAdapter(
    private val onConversationClick: (Conversation) -> Unit,
    private val onUserClick: (String) -> Unit
) : ListAdapter<Conversation, MessageAdapter.ConversationViewHolder>(ConversationDiffCallback()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ConversationViewHolder {
        val binding = ItemConversationBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return ConversationViewHolder(binding)
    }

    override fun onBindViewHolder(holder: ConversationViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class ConversationViewHolder(
        private val binding: ItemConversationBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(conversation: Conversation) {
            with(binding) {
                // User info
                tvDisplayName.text = conversation.otherUser.displayName
                tvUsername.text = "@${conversation.otherUser.username}"
                
                // Last message preview
                tvLastMessage.text = conversation.lastMessage.content
                
                // Timestamp
                tvTimestamp.text = Utils.getRelativeTime(conversation.timestamp)
                
                // Unread indicator
                if (conversation.unreadCount > 0) {
                    tvUnreadCount.visibility = View.VISIBLE
                    tvUnreadCount.text = conversation.unreadCount.toString()
                    
                    // Make text bold for unread
                    tvDisplayName.setTypeface(null, android.graphics.Typeface.BOLD)
                    tvLastMessage.setTypeface(null, android.graphics.Typeface.BOLD)
                } else {
                    tvUnreadCount.visibility = View.GONE
                    
                    // Normal text for read
                    tvDisplayName.setTypeface(null, android.graphics.Typeface.NORMAL)
                    tvLastMessage.setTypeface(null, android.graphics.Typeface.NORMAL)
                }
                
                // Online status (simulasi)
                viewOnlineStatus.visibility = if (Math.random() > 0.5) View.VISIBLE else View.GONE
                
                // Click listeners
                root.setOnClickListener {
                    onConversationClick(conversation)
                }
                
                ivProfilePicture.setOnClickListener {
                    onUserClick(conversation.otherUser.id)
                }
            }
        }
    }

    class ConversationDiffCallback : DiffUtil.ItemCallback<Conversation>() {
        override fun areItemsTheSame(oldItem: Conversation, newItem: Conversation): Boolean {
            return oldItem.id == newItem.id
        }

        override fun areContentsTheSame(oldItem: Conversation, newItem: Conversation): Boolean {
            return oldItem == newItem
        }
    }
}
