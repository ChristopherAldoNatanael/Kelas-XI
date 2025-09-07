package com.christopheraldoo.earningquizapp.adapters

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import com.christopheraldoo.earningquizapp.R
import com.christopheraldoo.earningquizapp.models.User

/**
 * Adapter for the leaderboard RecyclerView.
 *
 * This adapter binds a list of User objects to the `item_leaderboard_user` layout.
 */
class LeaderboardAdapter(private val users: List<User>) : RecyclerView.Adapter<LeaderboardAdapter.LeaderboardViewHolder>() {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): LeaderboardViewHolder {
        val view = LayoutInflater.from(parent.context).inflate(R.layout.item_leaderboard_user, parent, false)
        return LeaderboardViewHolder(view)
    }

    override fun onBindViewHolder(holder: LeaderboardViewHolder, position: Int) {
        val user = users[position]
        holder.bind(user)
    }

    override fun getItemCount(): Int = users.size

    class LeaderboardViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        private val tvRank: TextView = itemView.findViewById(R.id.tv_rank)
        private val tvName: TextView = itemView.findViewById(R.id.tv_user_name)
        private val tvPoints: TextView = itemView.findViewById(R.id.tv_user_points)

        fun bind(user: User) {
            tvRank.text = "#${user.rank}"
            tvName.text = user.fullName
            tvPoints.text = "${user.points} pts"
        }
    }
}
