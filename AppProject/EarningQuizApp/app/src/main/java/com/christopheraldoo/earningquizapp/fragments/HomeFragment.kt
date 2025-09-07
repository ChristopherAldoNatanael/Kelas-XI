package com.christopheraldoo.earningquizapp.fragments

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import com.christopheraldoo.earningquizapp.R

/**
 * A Fragment that displays the main home screen.
 *
 * This screen includes a welcome message, user points, and a grid of quiz subjects.
 */
class HomeFragment : Fragment() {

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View? {
        // Inflate the layout for this fragment
        return inflater.inflate(R.layout.fragment_home, container, false)
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        // TODO: Populate views with user data and subject grid
        val rvSubjects = view.findViewById<androidx.recyclerview.widget.RecyclerView>(R.id.rv_subjects)
        val tvWelcome = view.findViewById<android.widget.TextView>(R.id.tv_welcome_message)

        // Populate user welcome message
        val user = com.christopheraldoo.earningquizapp.utils.SharedPrefsManager.getUser(requireContext())
        tvWelcome.text = "Hello, ${user?.fullName ?: "User"}!"

        // Create mock subjects
        val subjects = listOf(
            com.christopheraldoo.earningquizapp.models.Subject("Mathematics", R.drawable.ic_launcher_foreground),
            com.christopheraldoo.earningquizapp.models.Subject("Science", R.drawable.ic_launcher_foreground),
            com.christopheraldoo.earningquizapp.models.Subject("History", R.drawable.ic_launcher_foreground),
            com.christopheraldoo.earningquizapp.models.Subject("Geography", R.drawable.ic_launcher_foreground),
            com.christopheraldoo.earningquizapp.models.Subject("Art", R.drawable.ic_launcher_foreground),
            com.christopheraldoo.earningquizapp.models.Subject("Sports", R.drawable.ic_launcher_foreground)
        )

        // Set up RecyclerView
        val adapter = com.christopheraldoo.earningquizapp.adapters.SubjectAdapter(subjects)
        rvSubjects.adapter = adapter
        rvSubjects.layoutManager = androidx.recyclerview.widget.GridLayoutManager(context, 2)

        adapter.setOnItemClickListener {
            val intent = android.content.Intent(activity, com.christopheraldoo.earningquizapp.activities.QuizActivity::class.java)
            intent.putExtra("SUBJECT_NAME", it.name)
            startActivity(intent)
        }
    }
}
