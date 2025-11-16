package com.christopheraldoo.aplikasimonitoringkelas

import android.app.Application

/**
 * Application class for Monitoring Kelas
 * Configured to use MySQL database through Laravel API
 */
class MonitoringKelasApplication : Application() {
    
    override fun onCreate() {
        super.onCreate()
        // Initialize any application-wide components here
        // No local database needed - everything goes through MySQL via API
    }
}
