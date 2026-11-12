package com.christopheraldoo.petheal.util

import com.christopheraldoo.petheal.data.model.Doctor
import com.christopheraldoo.petheal.data.model.LocationSource
import com.christopheraldoo.petheal.data.model.UserLocation

/**
 * MULTI-CLINIC (2026-11-09): satu baris daftar dokter yang sudah membawa
 * konteks klinik & jarak.
 *
 * Dipakai Beranda dan Cari Dokter supaya keduanya membaca dokter dengan cara
 * yang sama — sebelumnya daftar dokter tidak menyebut kliniknya sama sekali,
 * jadi pasien tidak tahu ke mana booking-nya akan masuk.
 */
data class DoctorListing(
    val doctor: Doctor,
    /** Slug klinik (null bila server belum menyertakannya). */
    val clinicSlug: String?,
    val clinicName: String?,
    /** Alamat klinik; dipakai sebagai fallback bila nama tidak ada. */
    val clinicAddress: String?,
    /**
     * Jarak user → klinik dalam km. Null = lokasi belum diketahui ATAU klinik
     * belum punya koordinat. Jangan pernah diisi 0 sebagai pengganti.
     */
    val distanceKm: Double? = null
) {
    val id: Int? get() = doctor.id
    /** Id klinik dokter; dipakai membuka detail dokter lintas klinik. */
    val clinicId: Int? get() = doctor.clinic?.id
    val hasClinic: Boolean get() = !clinicName.isNullOrBlank() || !clinicSlug.isNullOrBlank()
    /** Label siap tampil; null berarti tidak ada yang jujur untuk ditulis. */
    val clinicLabel: String?
        get() = clinicName?.takeIf { it.isNotBlank() }
            ?: clinicAddress?.takeIf { it.isNotBlank() }
    val distanceLabel: String? get() = LocationHelper.formatDistance(distanceKm)
}

object DoctorListingMapper {

    /**
     * Petakan katalog dokter menjadi baris siap tampil.
     *
     * `userLocation` null → jarak null untuk semua baris (UI menyembunyikan
     * jarak, bukan menampilkan "0 km").
     */
    fun map(doctors: List<Doctor>, userLocation: UserLocation?): List<DoctorListing> =
        doctors.map { doctor ->
            val clinic = doctor.clinic
            val distance = if (userLocation != null &&
                clinic?.latitude != null && clinic.longitude != null
            ) {
                LocationHelper.haversineKm(
                    userLocation.latitude, userLocation.longitude,
                    clinic.latitude, clinic.longitude
                )
            } else {
                null
            }
            DoctorListing(
                doctor = doctor,
                clinicSlug = clinic?.slug,
                clinicName = clinic?.name,
                clinicAddress = clinic?.address,
                distanceKm = distance
            )
        }

    /**
     * Urutkan: terdekat dulu bila lokasi tersedia, lalu nama dokter.
     *
     * Klinik tanpa koordinat ditaruh di akhir — bukan disembunyikan, karena
     * pasien tetap boleh memilihnya.
     */
    fun sortByDistance(listings: List<DoctorListing>): List<DoctorListing> =
        listings.sortedWith(
            compareBy<DoctorListing> { it.distanceKm == null }
                .thenBy { it.distanceKm ?: 0.0 }
                .thenBy { it.doctor.name.orEmpty().lowercase() }
        )

    /** Urutan abjad — stabil dan dapat diprediksi tanpa lokasi. */
    fun sortByName(listings: List<DoctorListing>): List<DoctorListing> =
        listings.sortedBy { it.doctor.name.orEmpty().lowercase() }
}

/** Urutan daftar dokter; "Terdekat" hanya berarti bila lokasi tersedia. */
enum class DoctorSort { NEAREST, NAME }

/**
 * Status lokasi user untuk daftar dokter.
 *
 * Lokasi OPSIONAL: kegagalan hanya mengubah label chip, tidak pernah menjadi
 * error halaman dan tidak pernah menghalangi daftar dokter tampil.
 */
enum class DoctorLocationStatus { Unset, Ready, PermissionDenied, ServicesDisabled, Failed }

/**
 * Susun lokasi user dari preferensi/pengaturan, lalu (opsional) coba fix GPS
 * sekali. Mengembalikan null bila tidak ada lokasi yang bisa dipakai —
 * pemanggil wajib memperlakukan null sebagai "jarak tidak tersedia".
 */
suspend fun resolveUserLocation(
    saved: UserLocation?,
    hasPermission: Boolean,
    requestGps: Boolean,
    gps: suspend () -> LocationResult
): Pair<UserLocation?, DoctorLocationStatus> {
    if (saved != null) {
        // Lokasi manual selalu diutamakan: user sudah menyatakan pilihannya.
        if (saved.source == LocationSource.MANUAL) return saved to DoctorLocationStatus.Ready
        return saved to DoctorLocationStatus.Ready
    }
    if (!requestGps) return null to DoctorLocationStatus.Unset
    if (!hasPermission) return null to DoctorLocationStatus.PermissionDenied
    return when (val result = gps()) {
        is LocationResult.Success -> UserLocation(
            latitude = result.latitude,
            longitude = result.longitude,
            label = "Lokasi GPS"
        ) to DoctorLocationStatus.Ready
        LocationResult.PermissionDenied -> null to DoctorLocationStatus.PermissionDenied
        LocationResult.ServicesDisabled -> null to DoctorLocationStatus.ServicesDisabled
        LocationResult.Timeout, LocationResult.Unavailable -> null to DoctorLocationStatus.Failed
    }
}

/** Teks chip lokasi — menyebut tindakan, bukan istilah teknis. */
fun doctorLocationChipLabel(status: DoctorLocationStatus, hasLocation: Boolean, sort: DoctorSort): String =
    when {
        sort == DoctorSort.NEAREST && hasLocation -> "Terdekat"
        sort == DoctorSort.NAME -> "Nama A–Z"
        status == DoctorLocationStatus.PermissionDenied -> "Aktifkan lokasi"
        status == DoctorLocationStatus.ServicesDisabled -> "Lokasi mati"
        status == DoctorLocationStatus.Failed -> "Coba lokasi lagi"
        else -> "Urutkan terdekat"
    }
