package com.christopheraldoo.earningquizapp.fragments

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import com.christopheraldoo.earningquizapp.R

/**
 * A Fragment that displays the leaderboard.
 *
 * This screen shows a ranked list of users based on their points.
 */
class LeaderboardFragment : Fragment() {

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View? {
        // Inflate the layout for this fragment
        return inflater.inflate(R.layout.fragment_leaderboard, container, false)
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        // TODO: Set up RecyclerView and adapter with mock data
        val rvLeaderboard = view.findViewById<androidx.recyclerview.widget.RecyclerView>(R.id.rv_leaderboard)

        // Create mock users for the leaderboard
        val users = listOf(
            com.christopheraldoo.earningquizapp.models.User("1", "Alice", "alice@example.com", "password123", points = 5200, rank = 1),
            com.christopheraldoo.earningquizapp.models.User("2", "Bob", "bob@example.com", "password123", points = 4800, rank = 2),
            com.christopheraldoo.earningquizapp.models.User("3", "Charlie", "charlie@example.com", "password123", points = 4500, rank = 3),
            com.christopheraldoo.earningquizapp.models.User("4", "David", "david@example.com", "password123", points = 4200, rank = 4),
            com.christopheraldoo.earningquizapp.models.User("5", "Eve", "eve@example.com", "password123", points = 3900, rank = 5)
        )

        // Set up RecyclerView
        val adapter = com.christopheraldoo.earningquizapp.adapters.LeaderboardAdapter(users)
        rvLeaderboard.adapter = adapter
        rvLeaderboard.layoutManager = androidx.recyclerview.widget.LinearLayoutManager(context)
    }
}
