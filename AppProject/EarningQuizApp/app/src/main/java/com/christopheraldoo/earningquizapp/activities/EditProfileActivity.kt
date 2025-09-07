package com.christopheraldoo.earningquizapp.activities

import android.os.Bundle
import android.widget.Button
import android.widget.EditText
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.christopheraldoo.earningquizapp.R
import com.christopheraldoo.earningquizapp.models.User
import com.christopheraldoo.earningquizapp.utils.SharedPrefsManager

/**
 * Activity for editing user profile information.
 *
 * Currently, this only allows editing the user's full name.
 */
class EditProfileActivity : AppCompatActivity() {

    private lateinit var etFullName: EditText
    private lateinit var btnSave: Button

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_edit_profile)

        etFullName = findViewById(R.id.et_edit_full_name)
        btnSave = findViewById(R.id.btn_save_profile)

        // Load current user data
        val currentUser = SharedPrefsManager.getUser(this)
        etFullName.setText(currentUser?.fullName)

        btnSave.setOnClickListener {
            val newName = etFullName.text.toString().trim()
            if (newName.isNotEmpty()) {
                // In a real app, you'd send this to a backend.
                // Here, we'll update the user object in SharedPreferences.
                currentUser?.let { user ->
                    val updatedUser = user.copy(fullName = newName)
                    SharedPrefsManager.loginUser(this, updatedUser) // Re-save the user data
                    Toast.makeText(this, "Profile updated successfully", Toast.LENGTH_SHORT).show()
                    finish() // Go back to the previous screen
                }
            } else {
                Toast.makeText(this, "Name cannot be empty", Toast.LENGTH_SHORT).show()
            }
        }
    }
}
