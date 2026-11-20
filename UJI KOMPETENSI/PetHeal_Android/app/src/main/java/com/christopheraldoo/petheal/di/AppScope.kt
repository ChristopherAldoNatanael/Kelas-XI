package com.christopheraldoo.petheal.di

import javax.inject.Qualifier

/**
 * Penanda [kotlinx.coroutines.CoroutineScope] milik proses aplikasi.
 *
 * Dipakai untuk pekerjaan yang harus tetap hidup selama aplikasi berjalan —
 * bukan mengikuti satu layar — contohnya menyegarkan cache sesi dan menghapus
 * sesi setelah 401. Scope ini tidak pernah dibatalkan, jadi jangan pakai untuk
 * pekerjaan yang bergantung pada lifecycle sebuah layar.
 */
@Qualifier
@Retention(AnnotationRetention.BINARY)
annotation class ApplicationScope
