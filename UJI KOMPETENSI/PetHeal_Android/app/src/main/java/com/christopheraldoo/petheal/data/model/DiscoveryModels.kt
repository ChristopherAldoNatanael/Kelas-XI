package com.christopheraldoo.petheal.data.model

import com.google.gson.annotations.SerializedName

/**
 * MULTI-CLINIC DISCOVERY (2026-11-09).
 *
 * DTO untuk endpoint publik baru di backend:
 *  - GET /api/public/clinics?lat=&lng=&sort=&search=&category=&min_rating=&open_now=
 *  - GET /api/public/clinics/{slug}?lat=&lng=
 *  - GET /api/public/service-categories
 *  - GET /api/public/services/compare?category_slug=|service_id=&lat=&lng=&sort=
 *  - GET /api/public/services/{id}?lat=&lng=
 *
 * Semua field harga/rating/jarak NULLABLE: backend tidak pernah mengarang
 * angka, jadi UI wajib punya state "belum tersedia".
 */
data class ClinicCard(
    val id: Int? = null,
    val name: String? = null,
    val slug: String? = null,
    val address: String? = null,
    val phone: String? = null,
    @SerializedName("logo_url") val logoUrl: String? = null,
    @SerializedName("featured_image_url") val featuredImageUrl: String? = null,
    @SerializedName("primary_color") val primaryColor: String? = null,
    val description: String? = null,
    val latitude: Double? = null,
    val longitude: Double? = null,
    @SerializedName("has_coordinates") val hasCoordinates: Boolean? = null,
    val rating: Double? = null,
    @SerializedName("reviews_count") val reviewsCount: Int? = null,
    @SerializedName("opening_time") val openingTime: String? = null,
    @SerializedName("closing_time") val closingTime: String? = null,
    @SerializedName("operating_days") val operatingDays: List<String>? = null,
    @SerializedName("is_open_now") val isOpenNow: Boolean? = null,
    @SerializedName("operating_status") val operatingStatus: String? = null,
    @SerializedName("distance_km") val distanceKm: Double? = null,
    @SerializedName("distance_text") val distanceText: String? = null,
    @SerializedName("doctors_count") val doctorsCount: Int? = null,
    @SerializedName("services_count") val servicesCount: Int? = null
) {
    /** ID unik untuk list Compose; slug dipakai backend, jadi aman. */
    val key: String get() = slug ?: id?.toString() ?: "unknown"
}

data class ClinicDiscoveryMeta(
    val total: Int? = null,
    val sort: String? = null,
    @SerializedName("has_location") val hasLocation: Boolean? = null
)

data class ClinicListResponse(
    val success: Boolean,
    val message: String? = null,
    val data: List<ClinicCard>? = null,
    val meta: ClinicDiscoveryMeta? = null
)

data class ServiceCategoryDto(
    val id: Int? = null,
    val name: String? = null,
    val slug: String? = null,
    val description: String? = null,
    /** Nama ikon Material Symbols yang dikirim backend (mis. "vaccines"). */
    val icon: String? = null
)

data class ServiceCategoriesResponse(
    val success: Boolean,
    val message: String? = null,
    val data: List<ServiceCategoryDto>? = null
)

data class CompareServiceCategory(
    val id: Int? = null,
    val name: String? = null,
    val slug: String? = null,
    val icon: String? = null
)

data class CompareClinic(
    val id: Int? = null,
    val name: String? = null,
    val slug: String? = null,
    val address: String? = null,
    @SerializedName("logo_url") val logoUrl: String? = null,
    @SerializedName("primary_color") val primaryColor: String? = null,
    val rating: Double? = null,
    @SerializedName("reviews_count") val reviewsCount: Int? = null,
    val phone: String? = null,
    @SerializedName("is_open_now") val isOpenNow: Boolean? = null,
    val latitude: Double? = null,
    val longitude: Double? = null
)

/** Satu baris perbandingan harga: layanan X di klinik Y. */
data class CompareItem(
    @SerializedName("service_id") val serviceId: Int? = null,
    @SerializedName("service_name") val serviceName: String? = null,
    @SerializedName("service_category") val serviceCategory: CompareServiceCategory? = null,
    val price: Double? = null,
    @SerializedName("price_available") val priceAvailable: Boolean? = null,
    @SerializedName("price_formatted") val priceFormatted: String? = null,
    @SerializedName("price_status") val priceStatus: String? = null,
    @SerializedName("duration_minutes") val durationMinutes: Int? = null,
    val description: String? = null,
    val clinic: CompareClinic? = null,
    @SerializedName("distance_km") val distanceKm: Double? = null,
    @SerializedName("distance_text") val distanceText: String? = null
) {
    val key: String get() = "${serviceId ?: 0}-${clinic?.slug ?: clinic?.id ?: 0}"
    val hasPrice: Boolean get() = priceAvailable != false && price != null
}

data class CompareMeta(
    val total: Int? = null,
    val sort: String? = null,
    @SerializedName("has_location") val hasLocation: Boolean? = null,
    val category: CompareServiceCategory? = null,
    @SerializedName("comparison_basis") val comparisonBasis: String? = null,
    @SerializedName("price_available_count") val priceAvailableCount: Int? = null,
    @SerializedName("lowest_price") val lowestPrice: Double? = null,
    @SerializedName("highest_price") val highestPrice: Double? = null,
    @SerializedName("lowest_price_formatted") val lowestPriceFormatted: String? = null
)

data class CompareResponse(
    val success: Boolean,
    val message: String? = null,
    val data: List<CompareItem>? = null,
    val meta: CompareMeta? = null
)

data class ClinicServiceDto(
    val id: Int? = null,
    val name: String? = null,
    val description: String? = null,
    val price: Double? = null,
    @SerializedName("price_formatted") val priceFormatted: String? = null,
    @SerializedName("duration_minutes") val durationMinutes: Int? = null,
    val category: String? = null,
    @SerializedName("service_category") val serviceCategory: CompareServiceCategory? = null,
    @SerializedName("is_active") val isActive: Boolean? = null
)

/** Detail klinik lengkap (layar Clinic Detail). */
data class ClinicDetail(
    val id: Int? = null,
    val name: String? = null,
    val slug: String? = null,
    val address: String? = null,
    val phone: String? = null,
    val email: String? = null,
    @SerializedName("logo_url") val logoUrl: String? = null,
    @SerializedName("featured_image_url") val featuredImageUrl: String? = null,
    @SerializedName("primary_color") val primaryColor: String? = null,
    val description: String? = null,
    val latitude: Double? = null,
    val longitude: Double? = null,
    @SerializedName("has_coordinates") val hasCoordinates: Boolean? = null,
    val rating: Double? = null,
    @SerializedName("reviews_count") val reviewsCount: Int? = null,
    @SerializedName("opening_time") val openingTime: String? = null,
    @SerializedName("closing_time") val closingTime: String? = null,
    @SerializedName("operating_days") val operatingDays: List<String>? = null,
    @SerializedName("is_open_now") val isOpenNow: Boolean? = null,
    @SerializedName("operating_status") val operatingStatus: String? = null,
    @SerializedName("distance_km") val distanceKm: Double? = null,
    @SerializedName("distance_text") val distanceText: String? = null,
    @SerializedName("doctors_count") val doctorsCount: Int? = null,
    @SerializedName("services_count") val servicesCount: Int? = null,
    val services: List<ClinicServiceDto>? = null
)

data class ClinicDetailFullResponse(
    val success: Boolean,
    val message: String? = null,
    val data: ClinicDetail? = null
)

data class ServiceEquivalent(
    @SerializedName("service_id") val serviceId: Int? = null,
    @SerializedName("service_name") val serviceName: String? = null,
    val price: Double? = null,
    @SerializedName("price_available") val priceAvailable: Boolean? = null,
    @SerializedName("price_formatted") val priceFormatted: String? = null,
    val clinic: CompareClinic? = null,
    @SerializedName("distance_km") val distanceKm: Double? = null
)

data class ServiceDetailPayload(
    val service: ClinicServiceDto? = null,
    val clinic: ClinicCard? = null,
    val equivalents: List<ServiceEquivalent>? = null,
    @SerializedName("equivalents_count") val equivalentsCount: Int? = null,
    @SerializedName("comparison_basis") val comparisonBasis: String? = null
)

data class ServiceDetailResponse(
    val success: Boolean,
    val message: String? = null,
    val data: ServiceDetailPayload? = null
)

/**
 * Lokasi pengguna. `source` membedakan GPS dari input manual supaya UI bisa
 * menampilkan status yang jujur ("Lokasi GPS" vs "Lokasi manual").
 */
data class UserLocation(
    val latitude: Double,
    val longitude: Double,
    val label: String? = null,
    val source: LocationSource = LocationSource.GPS
) {
    val isGps: Boolean get() = source == LocationSource.GPS
}

enum class LocationSource { GPS, MANUAL }
