package com.christopheraldoo.sosialmediaapp.adapter

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.christopheraldoo.sosialmediaapp.databinding.ItemChatMessageBinding
import com.christopheraldoo.sosialmediaapp.model.Message
import com.christopheraldoo.sosialmediaapp.utils.DummyData
import com.christopheraldoo.sosialmediaapp.utils.Utils

/**
 * Adapter untuk RecyclerView chat messages
 */
class ChatMessageAdapter : ListAdapter<Message, ChatMessageAdapter.MessageViewHolder>(MessageDiffCallback()) {

    private val currentUserId = DummyData.users[0].id // Current user ID

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): MessageViewHolder {
        val binding = ItemChatMessageBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return MessageViewHolder(binding)
    }

    override fun onBindViewHolder(holder: MessageViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class MessageViewHolder(
        private val binding: ItemChatMessageBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(message: Message) {
            with(binding) {
                val isCurrentUser = message.fromUser.id == currentUserId
                
                if (isCurrentUser) {
                    // Sent message (right side)
                    llSentMessage.visibility = android.view.View.VISIBLE
                    llReceivedMessage.visibility = android.view.View.GONE
                    
                    tvSentMessage.text = message.content
                    tvSentTime.text = Utils.getRelativeTime(message.timestamp)
                } else {
                    // Received message (left side)
                    llSentMessage.visibility = android.view.View.GONE
                    llReceivedMessage.visibility = android.view.View.VISIBLE
                    
                    tvReceivedMessage.text = message.content
                    tvReceivedTime.text = Utils.getRelativeTime(message.timestamp)
                    
                    // Set user profile info
                    tvSenderName.text = message.fromUser.displayName
                }
            }
        }
    }

    class MessageDiffCallback : DiffUtil.ItemCallback<Message>() {
        override fun areItemsTheSame(oldItem: Message, newItem: Message): Boolean {
            return oldItem.id == newItem.id
        }

        override fun areContentsTheSame(oldItem: Message, newItem: Message): Boolean {
            return oldItem == newItem
        }
    }
}
