package com.christopheraldoo.petheal.data.remote

import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.di.ApplicationScope
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Menyalin sesi (token + tenant) dari DataStore ke memori **satu kali** di awal
 * proses, lalu menjaga salinannya tetap segar.
 *
 * Ini yang membuat [NetworkInterceptor] cukup membaca variabel tanpa
 * `runBlocking`: setiap kali token atau slug klinik berubah (login, pilih
 * klinik, logout, sesi kedaluwarsa), `combine` langsung memperbarui cache.
 *
 * Dipanggil dari `PetHealApplication.onCreate`, bukan dari sebuah layar, supaya
 * cache sudah terisi sebelum layar pertama memanggil API.
 */
@Singleton
class SessionCacheBootstrap @Inject constructor(
    private val preferencesManager: PreferencesManager,
    private val networkInterceptor: NetworkInterceptor,
    @ApplicationScope private val appScope: CoroutineScope
) {

    fun start() {
        appScope.launch {
            combine(
                preferencesManager.authToken,
                preferencesManager.clinicSlug
            ) { token, slug -> token to slug }
                .collect { (token, slug) ->
                    networkInterceptor.updateToken(token)
                    networkInterceptor.updateClinicSlug(slug)
                }
        }
    }
}
