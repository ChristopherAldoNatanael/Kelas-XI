package com.christopheraldoo.petheal.ui.location

import android.Manifest
import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.provider.Settings
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import com.christopheraldoo.petheal.util.LocationHelper

/**
 * Satu-satunya pintu permintaan izin lokasi di aplikasi.
 *
 * Sebelumnya setiap layar (penemuan klinik, daftar dokter, detail produk Pet
 * Shop) membuat `rememberLauncherForActivityResult` sendiri, sehingga status
 * izin bisa berbeda antar halaman dan pengguna ditanya berulang kali.
 *
 * Aturan yang dijaga di sini:
 *  - status SELALU dibaca dari sistem (`checkSelfPermission`), bukan dari
 *    flag lokal yang bisa basi;
 *  - bila izin sudah diberikan, [request] tidak memunculkan dialog lagi —
 *    pemanggil langsung memakai lokasi;
 *  - status diperbarui setiap kali aplikasi kembali aktif, jadi izin yang
 *    dicabut lewat Settings langsung terdeteksi;
 *  - izin yang tidak bisa diminta lagi lewat dialog ditandai
 *    [LocationPermissionStatus.PERMANENTLY_DENIED] dan pemanggil menawarkan
 *    [LocationPermissionHandle.openSettings];
 *  - hanya izin "saat aplikasi digunakan" yang diminta (fine/coarse), tidak
 *    pernah background location.
 *
 * Lokasi tetap opsional: pemanggil wajib menyediakan jalur tanpa lokasi.
 */
enum class LocationPermissionStatus {
    GRANTED,
    DENIED,
    PERMANENTLY_DENIED
}

class LocationPermissionHandle internal constructor(
    val status: LocationPermissionStatus,
    private val requestBlock: (onResult: ((LocationPermissionStatus) -> Unit)?) -> Unit,
    private val openSettingsBlock: () -> Unit
) {
    val isGranted: Boolean get() = status == LocationPermissionStatus.GRANTED

    /** True bila dialog sistem masih bisa ditampilkan untuk izin ini. */
    val canRequest: Boolean get() = status != LocationPermissionStatus.PERMANENTLY_DENIED

    /**
     * Minta izin. Bila sudah diberikan, dialog TIDAK muncul dan [onResult]
     * langsung dipanggil dengan [LocationPermissionStatus.GRANTED].
     *
     * [onResult] menerima status akhir setelah dialog ditutup (atau setelah
     * status dibaca ulang ketika izin sudah ada / diblokir permanen). Pemanggil
     * memakainya untuk membedakan "user menolak" dari "sistem tidak bertanya
     * lagi", karena keduanya sama-sama menghasilkan status DENIED.
     */
    fun request(onResult: ((LocationPermissionStatus) -> Unit)? = null) =
        requestBlock(onResult)

    fun openSettings() = openSettingsBlock()
}

/** Izin lokasi minimum yang benar-benar dipakai fitur (foreground saja). */
val LOCATION_PERMISSIONS = arrayOf(
    Manifest.permission.ACCESS_FINE_LOCATION,
    Manifest.permission.ACCESS_COARSE_LOCATION
)

@Composable
fun rememberLocationPermission(): LocationPermissionHandle {
    val context = LocalContext.current
    val currentContext by rememberUpdatedState(context)

    var status by remember { mutableStateOf(readStatus(context)) }

    // Callback satu kali untuk pemanggil yang ingin tahu hasil dialog.
    var pendingResult by remember {
        mutableStateOf<((LocationPermissionStatus) -> Unit)?>(null)
    }

    val launcher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestMultiplePermissions()
    ) {
        // Hasil dialog bukan sumber kebenaran tunggal: baca ulang dari sistem
        // supaya "approx only" / "only this time" ikut terhitung benar.
        val resolved = readStatus(currentContext, consideredRequest = true)
        status = resolved
        pendingResult?.invoke(resolved)
        pendingResult = null
    }

    // Izin bisa berubah dari Settings saat aplikasi di background. Tanpa
    // pembacaan ulang ini, halaman lain akan terus mengira izin belum ada.
    val lifecycleOwner = LocalLifecycleOwner.current
    DisposableEffect(lifecycleOwner) {
        val observer = LifecycleEventObserver { _, event ->
            if (event == Lifecycle.Event.ON_RESUME) {
                status = readStatus(currentContext)
            }
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose { lifecycleOwner.lifecycle.removeObserver(observer) }
    }

    val openSettings = {
        runCatching {
            context.startActivity(
                Intent(
                    Settings.ACTION_APPLICATION_DETAILS_SETTINGS,
                    Uri.fromParts("package", context.packageName, null)
                )
            )
        }
        Unit
    }

    return LocationPermissionHandle(
        status = status,
        requestBlock = { onResult ->
            when (status) {
                // Sudah diizinkan: jangan tampilkan dialog, cukup segarkan
                // status agar pemanggil memakai lokasi yang ada.
                LocationPermissionStatus.GRANTED -> {
                    status = readStatus(currentContext)
                    onResult?.invoke(status)
                }
                // Dialog tidak akan muncul lagi: buka pengaturan aplikasi dan
                // beri tahu pemanggil alasannya.
                LocationPermissionStatus.PERMANENTLY_DENIED -> {
                    openSettings()
                    onResult?.invoke(LocationPermissionStatus.PERMANENTLY_DENIED)
                }
                LocationPermissionStatus.DENIED -> {
                    pendingResult = onResult
                    launcher.launch(LOCATION_PERMISSIONS)
                }
            }
        },
        openSettingsBlock = openSettings
    )
}

private fun readStatus(
    context: Context,
    consideredRequest: Boolean = false
): LocationPermissionStatus {
    if (LocationHelper.hasPermission(context)) return LocationPermissionStatus.GRANTED

    // Belum pernah ditanya / rationale masih boleh tampil → dialog masih sah.
    val activity = context.findActivity() ?: return LocationPermissionStatus.DENIED
    val canAskAgain = LOCATION_PERMISSIONS.any { permission ->
        ContextCompat.checkSelfPermission(context, permission) != PackageManager.PERMISSION_GRANTED &&
            ActivityCompat.shouldShowRequestPermissionRationale(activity, permission)
    }

    if (canAskAgain) return LocationPermissionStatus.DENIED

    // Tidak ada rationale & bukan hasil permintaan pertama → sistem tidak akan
    // menampilkan dialog lagi ("jangan tanya lagi").
    return if (consideredRequest) {
        LocationPermissionStatus.PERMANENTLY_DENIED
    } else {
        // Sebelum pernah meminta: tetap DENIED agar dialog pertama bisa muncul.
        LocationPermissionStatus.DENIED
    }
}

private tailrec fun Context.findActivity(): Activity? = when (this) {
    is Activity -> this
    is ContextWrapper -> baseContext.findActivity()
    else -> null
}
