package com.christopheraldoo.aplikasimonitoringkelas

// Admin functionality removed per project scope (only Siswa, Kurikulum, Kepala Sekolah roles).
// Deprecated stub kept to avoid accidental references.

import android.os.Bundle
import androidx.activity.ComponentActivity

@Deprecated("Admin role removed from mobile app; use web panel for administration.")
class AdminActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        // Immediately finish; no UI.
        finish()
    }
}

// All previous admin dashboard / management composables have been deleted.
// If any screen still imports them, remove those imports.

