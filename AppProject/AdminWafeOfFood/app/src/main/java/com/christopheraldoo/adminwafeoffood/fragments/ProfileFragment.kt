package com.christopheraldoo.adminwafeoffood.fragments

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Button
import android.widget.TextView
import android.widget.Toast
import androidx.fragment.app.Fragment
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import com.christopheraldoo.adminwafeoffood.MainActivity
import com.christopheraldoo.adminwafeoffood.R

class ProfileFragment : Fragment() {
    
    private lateinit var tvUserEmail: TextView
    private lateinit var btnLogout: Button
    
    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View? {
        return inflater.inflate(R.layout.fragment_profile, container, false)
    }
      override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        initializeViews(view)
        setupUserInfo()
        setupClickListeners()
    }
    
    private fun initializeViews(view: View) {
        // Cari TextView dan Button untuk email dan logout
        // Sesuaikan dengan ID yang ada di layout fragment_profile.xml
        tvUserEmail = view.findViewById(R.id.tv_user_email) ?: run {
            // Jika tidak ada, buat TextView sementara (fallback)
            TextView(requireContext()).apply {
                text = "Email tidak ditemukan"
            }
        }
        
        btnLogout = view.findViewById(R.id.btn_logout) ?: run {
            // Jika tidak ada, buat Button sementara (fallback)
            Button(requireContext()).apply {
                text = "Logout"
            }
        }
    }
    
    private fun setupUserInfo() {
        val mainActivity = activity as? MainActivity
        val userEmail = mainActivity?.getCurrentUserEmail()
        
        if (userEmail != null) {
            tvUserEmail.text = userEmail
        } else {
            tvUserEmail.text = "User tidak ditemukan"
        }
    }
    
    private fun setupClickListeners() {
        btnLogout.setOnClickListener {
            showLogoutDialog()
        }
    }
    
    private fun showLogoutDialog() {
        MaterialAlertDialogBuilder(requireContext())
            .setTitle("Konfirmasi Logout")
            .setMessage("Apakah Anda yakin ingin keluar dari aplikasi?")
            .setPositiveButton("Ya, Keluar") { _, _ ->
                performLogout()
            }
            .setNegativeButton("Batal", null)
            .show()
    }
    
    private fun performLogout() {
        val mainActivity = activity as? MainActivity
        mainActivity?.performLogout()
        
        Toast.makeText(requireContext(), "Berhasil logout", Toast.LENGTH_SHORT).show()
    }
    
    companion object {
        fun newInstance() = ProfileFragment()
    }
}
