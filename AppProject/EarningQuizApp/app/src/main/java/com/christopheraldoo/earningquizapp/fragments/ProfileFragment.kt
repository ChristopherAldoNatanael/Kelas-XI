package com.christopheraldoo.earningquizapp.fragments

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import com.christopheraldoo.earningquizapp.R

/**
 * A Fragment that displays the user's profile.
 *
 * This screen shows the user's details and provides options to edit the profile or log out.
 */
class ProfileFragment : Fragment() {

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View? {
        // Inflate the layout for this fragment
        return inflater.inflate(R.layout.fragment_profile, container, false)
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        // TODO: Populate views with user data and set up button listeners
        val tvName = view.findViewById<android.widget.TextView>(R.id.tv_profile_name)
        val tvEmail = view.findViewById<android.widget.TextView>(R.id.tv_profile_email)
        val btnEditProfile = view.findViewById<android.widget.Button>(R.id.btn_edit_profile)
        val btnLogout = view.findViewById<android.widget.Button>(R.id.btn_logout)

        // Populate user data
        val user = com.christopheraldoo.earningquizapp.utils.SharedPrefsManager.getUser(requireContext())
        if (user != null) {
            tvName.text = user.fullName
            tvEmail.text = user.email
        }

        // Edit Profile button listener
        btnEditProfile.setOnClickListener {
            val intent = android.content.Intent(activity, com.christopheraldoo.earningquizapp.activities.EditProfileActivity::class.java)
            startActivity(intent)
        }

        // Logout button listener
        btnLogout.setOnClickListener {
            com.christopheraldoo.earningquizapp.utils.SharedPrefsManager.logoutUser(requireContext())
            val intent = android.content.Intent(activity, com.christopheraldoo.earningquizapp.activities.LoginActivity::class.java)
            intent.flags = android.content.Intent.FLAG_ACTIVITY_NEW_TASK or android.content.Intent.FLAG_ACTIVITY_CLEAR_TASK
            startActivity(intent)
            activity?.finish()
        }
    }
}
