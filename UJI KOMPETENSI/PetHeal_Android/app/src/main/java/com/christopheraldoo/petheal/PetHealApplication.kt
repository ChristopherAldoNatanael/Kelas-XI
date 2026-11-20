package com.christopheraldoo.petheal

import android.app.Application
import coil.ImageLoader
import coil.ImageLoaderFactory
import com.christopheraldoo.petheal.data.remote.SessionCacheBootstrap
import com.christopheraldoo.petheal.service.AppVisibilityTracker
import com.christopheraldoo.petheal.service.NotificationChannels
import dagger.hilt.android.HiltAndroidApp
import javax.inject.Inject

@HiltAndroidApp
class PetHealApplication : Application(), ImageLoaderFactory {

    @Inject
    lateinit var imageLoader: ImageLoader

    /**
     * Mengisi cache token/tenant di memori sebelum layar pertama memanggil API,
     * sehingga [com.christopheraldoo.petheal.data.remote.NetworkInterceptor]
     * tidak perlu membaca DataStore dengan `runBlocking` per permintaan.
     */
    @Inject
    lateinit var sessionCacheBootstrap: SessionCacheBootstrap

    override fun onCreate() {
        super.onCreate()
        // Dibuat sebelum push pertama agar suara antrian/layanan sudah final.
        NotificationChannels.ensureCreated(this)
        // Menentukan TTS (foreground) vs notifikasi channel (background).
        AppVisibilityTracker.register(this)
        // Cache sesi: token + slug klinik untuk header permintaan.
        sessionCacheBootstrap.start()
    }

    override fun newImageLoader(): ImageLoader = imageLoader
}
