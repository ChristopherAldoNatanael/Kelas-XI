package com.christopheraldoo.aplikasimonitoringkelas

import android.app.Application
import com.christopheraldoo.aplikasimonitoringkelas.data.AppDatabase

class MonitoringKelasApplication : Application() {
    val database by lazy { AppDatabase.getDatabase(this) }
}
