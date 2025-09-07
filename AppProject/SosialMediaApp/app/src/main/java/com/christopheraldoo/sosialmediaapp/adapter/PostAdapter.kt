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
import com.christopheraldoo.sosialmediaapp.model.Post
import com.christopheraldoo.sosialmediaapp.utils.Utils

/**
 * Adapter untuk RecyclerView yang menampilkan list post di feed
 * Menggunakan ListAdapter dengan DiffUtil untuk performa yang optimal
 */
class PostAdapter(
    private val onPostClick: (Post) -> Unit,
    private val onUserClick: (String) -> Unit,
    private val onLikeClick: (String) -> Unit,
    private val onRetweetClick: (String) -> Unit,
    private val onReplyClick: (String) -> Unit,
    private val onShareClick: (String) -> Unit
) : ListAdapter<Post, PostAdapter.PostViewHolder>(PostDiffCallback()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): PostViewHolder {
        val view = LayoutInflater.from(parent.context)
            .inflate(R.layout.item_post, parent, false)
        return PostViewHolder(view)
    }

    override fun onBindViewHolder(holder: PostViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class PostViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        private val ivProfileImage: ImageView = itemView.findViewById(R.id.iv_profile_image)
        private val tvDisplayName: TextView = itemView.findViewById(R.id.tv_display_name)
        private val tvUsername: TextView = itemView.findViewById(R.id.tv_username)
        private val tvTimestamp: TextView = itemView.findViewById(R.id.tv_timestamp)
        private val tvContent: TextView = itemView.findViewById(R.id.tv_content)
        
        private val llReply: LinearLayout = itemView.findViewById(R.id.ll_reply)
        private val tvReplyCount: TextView = itemView.findViewById(R.id.tv_reply_count)
        
        private val llRetweet: LinearLayout = itemView.findViewById(R.id.ll_retweet)
        private val ivRetweet: ImageView = itemView.findViewById(R.id.iv_retweet)
        private val tvRetweetCount: TextView = itemView.findViewById(R.id.tv_retweet_count)
        
        private val llLike: LinearLayout = itemView.findViewById(R.id.ll_like)
        private val ivLike: ImageView = itemView.findViewById(R.id.iv_like)
        private val tvLikeCount: TextView = itemView.findViewById(R.id.tv_like_count)
        
        private val llShare: LinearLayout = itemView.findViewById(R.id.ll_share)

        fun bind(post: Post) {
            // Set user info
            tvDisplayName.text = post.user.displayName
            tvUsername.text = post.user.username
            tvTimestamp.text = Utils.formatTimeAgo(post.timestamp)
            tvContent.text = post.content
            
            // Set profile image (placeholder for now)
            // In real implementation, you would load image using library like Glide or Picasso
            
            // Set action counts
            tvReplyCount.text = if (post.replyCount > 0) Utils.formatCount(post.replyCount) else ""
            tvRetweetCount.text = if (post.retweetCount > 0) Utils.formatCount(post.retweetCount) else ""
            tvLikeCount.text = if (post.likeCount > 0) Utils.formatCount(post.likeCount) else ""
            
            // Set like state
            if (post.isLiked) {
                ivLike.setImageResource(R.drawable.ic_like_filled)
                tvLikeCount.setTextColor(itemView.context.getColor(R.color.like_red))
            } else {
                ivLike.setImageResource(R.drawable.ic_like)
                tvLikeCount.setTextColor(itemView.context.getColor(R.color.text_secondary))
            }
            
            // Set retweet state
            if (post.isRetweeted) {
                ivRetweet.setColorFilter(itemView.context.getColor(R.color.retweet_green))
                tvRetweetCount.setTextColor(itemView.context.getColor(R.color.retweet_green))
            } else {
                ivRetweet.setColorFilter(itemView.context.getColor(R.color.text_secondary))
                tvRetweetCount.setTextColor(itemView.context.getColor(R.color.text_secondary))
            }
            
            // Set click listeners
            itemView.setOnClickListener { onPostClick(post) }
            ivProfileImage.setOnClickListener { onUserClick(post.user.id) }
            tvDisplayName.setOnClickListener { onUserClick(post.user.id) }
            
            llReply.setOnClickListener { onReplyClick(post.id) }
            llRetweet.setOnClickListener { onRetweetClick(post.id) }
            llLike.setOnClickListener { onLikeClick(post.id) }
            llShare.setOnClickListener { onShareClick(post.id) }
        }
    }

    class PostDiffCallback : DiffUtil.ItemCallback<Post>() {
        override fun areItemsTheSame(oldItem: Post, newItem: Post): Boolean {
            return oldItem.id == newItem.id
        }

        override fun areContentsTheSame(oldItem: Post, newItem: Post): Boolean {
            return oldItem == newItem
        }
    }
}
