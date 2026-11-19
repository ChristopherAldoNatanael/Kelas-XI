package com.christopheraldoo.petheal

import android.app.Application
import coil.ImageLoader
import coil.ImageLoaderFactory
import com.christopheraldoo.petheal.service.AppVisibilityTracker
import com.christopheraldoo.petheal.service.NotificationChannels
import dagger.hilt.android.HiltAndroidApp
import javax.inject.Inject

@HiltAndroidApp
class PetHealApplication : Application(), ImageLoaderFactory {

    @Inject
    lateinit var imageLoader: ImageLoader

    override fun onCreate() {
        super.onCreate()
        // Dibuat sebelum push pertama agar suara antrian/layanan sudah final.
        NotificationChannels.ensureCreated(this)
        // Menentukan TTS (foreground) vs notifikasi channel (background).
        AppVisibilityTracker.register(this)
    }

    override fun newImageLoader(): ImageLoader = imageLoader
}
