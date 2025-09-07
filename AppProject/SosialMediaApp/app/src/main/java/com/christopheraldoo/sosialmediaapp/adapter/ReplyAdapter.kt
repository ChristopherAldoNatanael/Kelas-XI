package com.christopheraldoo.sosialmediaapp.adapter

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.TextView
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.christopheraldoo.sosialmediaapp.R
import com.christopheraldoo.sosialmediaapp.model.Reply
import com.christopheraldoo.sosialmediaapp.utils.Utils

/**
 * Adapter untuk RecyclerView yang menampilkan list reply di thread
 * Menggunakan ListAdapter dengan DiffUtil untuk performa yang optimal
 */
class ReplyAdapter(
    private val onUserClick: (String) -> Unit,
    private val onLikeClick: (String) -> Unit
) : ListAdapter<Reply, ReplyAdapter.ReplyViewHolder>(ReplyDiffCallback()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): ReplyViewHolder {
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_reply, parent, false)
        return ReplyViewHolder(view)
    }

    override fun onBindViewHolder(holder: ReplyViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class ReplyViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        private val ivProfileImage: ImageView = itemView.findViewById(R.id.iv_profile_image)
        private val tvDisplayName: TextView = itemView.findViewById(R.id.tv_display_name)
        private val tvUsername: TextView = itemView.findViewById(R.id.tv_username)
        private val tvTimestamp: TextView = itemView.findViewById(R.id.tv_timestamp)
        private val tvContent: TextView = itemView.findViewById(R.id.tv_content)
        
        private val llLike: LinearLayout = itemView.findViewById(R.id.ll_like)
        private val ivLike: ImageView = itemView.findViewById(R.id.iv_like)
        private val tvLikeCount: TextView = itemView.findViewById(R.id.tv_like_count)

        fun bind(reply: Reply) {
            // Set user info
            tvDisplayName.text = reply.user.displayName
            tvUsername.text = reply.user.username
            tvTimestamp.text = Utils.formatTimeAgo(reply.timestamp)
            tvContent.text = reply.content
            
            // Set profile image (placeholder for now)
            // In real implementation, you would load image using library like Glide or Picasso
            
            // Set like count
            tvLikeCount.text = if (reply.likeCount > 0) Utils.formatCount(reply.likeCount) else ""
            
            // Set like state
            if (reply.isLiked) {
                ivLike.setImageResource(R.drawable.ic_like_filled)
                tvLikeCount.setTextColor(itemView.context.getColor(R.color.like_red))
            } else {
                ivLike.setImageResource(R.drawable.ic_like)
                tvLikeCount.setTextColor(itemView.context.getColor(R.color.text_secondary))
            }
            
            // Set click listeners
            ivProfileImage.setOnClickListener { onUserClick(reply.user.id) }
            tvDisplayName.setOnClickListener { onUserClick(reply.user.id) }
            llLike.setOnClickListener { onLikeClick(reply.id) }
        }
    }

    class ReplyDiffCallback : DiffUtil.ItemCallback<Reply>() {
        override fun areItemsTheSame(oldItem: Reply, newItem: Reply): Boolean {
            return oldItem.id == newItem.id
        }

        override fun areContentsTheSame(oldItem: Reply, newItem: Reply): Boolean {
            return oldItem == newItem
        }
    }
}
