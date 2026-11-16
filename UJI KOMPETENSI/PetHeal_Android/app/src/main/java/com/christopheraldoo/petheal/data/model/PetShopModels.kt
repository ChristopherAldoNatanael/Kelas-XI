package com.christopheraldoo.petheal.data.model

import com.google.gson.annotations.SerializedName

/**
 * Model Pet Shop.
 *
 * Semua nominal dikirim backend sebagai integer rupiah, jadi tipe di sini
 * `Long` (bukan Double) — tidak ada pembulatan mata uang di aplikasi.
 */

// ============= KATALOG =============

data class PetShopCategory(
    val id: Int = 0,
    val name: String = "",
    val slug: String = "",
    val icon: String? = null,
    @SerializedName("products_count") val productsCount: Int = 0
)

data class PetShopSeller(
    val id: Int = 0,
    val name: String = "",
    val slug: String = "",
    val address: String? = null,
    val phone: String? = null,
    @SerializedName("logo_url") val logoUrl: String? = null,
    @SerializedName("products_count") val productsCount: Int = 0
)

data class PetShopProductCategory(
    val id: Int? = null,
    val name: String? = null,
    val slug: String? = null
)

data class PetShopProductClinic(
    val id: Int? = null,
    val name: String? = null,
    val slug: String? = null,
    val address: String? = null,
    val phone: String? = null,
    @SerializedName("logo_url") val logoUrl: String? = null,
    @SerializedName("primary_color") val primaryColor: String? = null,
    /**
     * Hanya true bila klinik penjual merchant-nya aktif + terverifikasi dan
     * Midtrans Partner siap. UI tidak boleh menawarkan pembayaran online bila
     * false (backend akan menolak pesanan online-nya).
     */
    @SerializedName("online_payment_available") val onlinePaymentAvailable: Boolean = false
)

data class PetShopVariant(
    val id: Int = 0,
    val sku: String? = null,
    val name: String = "",
    val price: Long = 0,
    @SerializedName("discount_price") val discountPrice: Long? = null,
    @SerializedName("effective_price") val effectivePrice: Long = 0,
    @SerializedName("available_stock") val availableStock: Int = 0,
    @SerializedName("is_available") val isAvailable: Boolean = false
)

data class PetShopProduct(
    val id: Int = 0,
    val name: String = "",
    val slug: String? = null,
    val brand: String? = null,
    val description: String? = null,
    @SerializedName("image_url") val imageUrl: String? = null,
    @SerializedName("is_active") val isActive: Boolean = true,
    val category: PetShopProductCategory? = null,
    val clinic: PetShopProductClinic? = null,
    @SerializedName("price_min") val priceMin: Long? = null,
    @SerializedName("price_max") val priceMax: Long? = null,
    @SerializedName("has_discount") val hasDiscount: Boolean = false,
    @SerializedName("total_available_stock") val totalAvailableStock: Int = 0,
    @SerializedName("is_available") val isAvailable: Boolean = false,
    @SerializedName("sold_count") val soldCount: Int = 0,
    val variants: List<PetShopVariant> = emptyList(),
    /** Klinik yang menjual produk sama, terurut jarak (Click & Collect). */
    @SerializedName("pickup_clinics") val pickupClinics: List<PetShopClinicAvailability> = emptyList()
) {
    val hasPriceRange: Boolean
        get() = priceMin != null && priceMax != null && priceMin != priceMax

    /** Varian yang benar-benar bisa dibeli sekarang. */
    val purchasableVariants: List<PetShopVariant>
        get() = variants.filter { it.isAvailable && it.availableStock > 0 }
}

data class PetShopMeta(
    @SerializedName("current_page") val currentPage: Int = 1,
    @SerializedName("last_page") val lastPage: Int = 1,
    @SerializedName("per_page") val perPage: Int = 12,
    val total: Int = 0
)

data class PetShopProductsResponse(
    val success: Boolean,
    val message: String? = null,
    val data: List<PetShopProduct>? = null,
    val meta: PetShopMeta? = null
)

// ============= PESANAN =============

data class PetShopOrderItem(
    val id: Int = 0,
    @SerializedName("product_id") val productId: Int? = null,
    @SerializedName("variant_id") val variantId: Int? = null,
    @SerializedName("product_name") val productName: String = "",
    @SerializedName("variant_name") val variantName: String = "",
    val sku: String? = null,
    @SerializedName("image_url") val imageUrl: String? = null,
    @SerializedName("unit_price") val unitPrice: Long = 0,
    @SerializedName("discount_price") val discountPrice: Long? = null,
    val quantity: Int = 0,
    val subtotal: Long = 0
)

data class PetShopPickup(
    @SerializedName("clinic_name") val clinicName: String? = null,
    val address: String? = null,
    val phone: String? = null
)

data class PetShopTimelineEntry(
    @SerializedName("from_status") val fromStatus: String? = null,
    @SerializedName("to_status") val toStatus: String? = null,
    val note: String? = null,
    @SerializedName("created_at") val createdAt: String? = null
)

data class PetShopOrder(
    val id: Int = 0,
    @SerializedName("order_number") val orderNumber: String = "",
    val status: String = "",
    @SerializedName("payment_status") val paymentStatus: String = "",
    @SerializedName("payment_method") val paymentMethod: String = "",
    val subtotal: Long = 0,
    @SerializedName("discount_total") val discountTotal: Long = 0,
    @SerializedName("total_amount") val totalAmount: Long = 0,
    val currency: String = "IDR",
    val clinic: ClinicRef? = null,
    val pickup: PetShopPickup? = null,
    @SerializedName("customer_name") val customerName: String? = null,
    @SerializedName("customer_phone") val customerPhone: String? = null,
    val notes: String? = null,
    @SerializedName("reservation_expires_at") val reservationExpiresAt: String? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    val items: List<PetShopOrderItem> = emptyList(),
    @SerializedName("can_cancel") val canCancel: Boolean = false,
    @SerializedName("receipt_available") val receiptAvailable: Boolean = false,
    @SerializedName("pickup_pass_available") val pickupPassAvailable: Boolean = false,
    @SerializedName("picked_up_at") val pickedUpAt: String? = null,
    val timeline: List<PetShopTimelineEntry> = emptyList(),
    /** Ringkasan baris pembayaran terakhir; null bila belum ada pembayaran. */
    val payment: PetShopOrderPayment? = null
) {
    val isOnline: Boolean get() = paymentMethod == METHOD_ONLINE
    val isPaid: Boolean get() = paymentStatus == PAYMENT_PAID
    val isAwaitingOnlinePayment: Boolean
        get() = isOnline && (paymentStatus == PAYMENT_UNPAID || paymentStatus == PAYMENT_PENDING)

    companion object {
        const val METHOD_ONLINE = "online"
        const val METHOD_PAY_AT_CLINIC = "pay_at_clinic"

        const val PAYMENT_UNPAID = "unpaid"
        const val PAYMENT_PENDING = "pending"
        const val PAYMENT_PAID = "paid"

        const val STATUS_PENDING_PAYMENT = "pending_payment"
        const val STATUS_AWAITING_CONFIRMATION = "awaiting_confirmation"
        const val STATUS_ACCEPTED = "accepted"
        const val STATUS_PREPARING = "preparing"
        const val STATUS_READY_FOR_PICKUP = "ready_for_pickup"
        const val STATUS_COMPLETED = "completed"
        const val STATUS_REJECTED = "rejected"
        const val STATUS_CANCELLED = "cancelled"
        const val STATUS_EXPIRED = "expired"
    }
}

/**
 * Satu baris `payments` milik pesanan. Nominal diterima/kembalian hanya ada
 * pada transaksi offline (bayar di klinik); transaksi online tidak punya
 * kembalian dan nilainya null.
 */
data class PetShopOrderPayment(
    @SerializedName("payment_code") val paymentCode: String? = null,
    @SerializedName("is_offline") val isOffline: Boolean = false,
    val method: String? = null,
    val status: String? = null,
    val provider: String? = null,
    @SerializedName("gross_amount") val grossAmount: Long = 0,
    @SerializedName("amount_received") val amountReceived: Long? = null,
    @SerializedName("change_amount") val changeAmount: Long? = null,
    val reference: String? = null,
    @SerializedName("recorded_by") val recordedBy: String? = null,
    @SerializedName("paid_at") val paidAt: String? = null
) {
    /** Label metode dari nilai yang disimpan backend, bukan tebakan UI. */
    val methodLabel: String?
        get() = when (method?.lowercase()) {
            "cash" -> "Tunai"
            "debit_card" -> "Kartu debit"
            "credit_card" -> "Kartu kredit"
            "bank_transfer", "bca_va", "bni_va", "bri_va", "mandiri_va", "permata_va", "cimb_va" -> "Transfer bank"
            "qris" -> "QRIS"
            "gopay" -> "GoPay"
            "shopeepay" -> "ShopeePay"
            "echannel" -> "Mandiri Bill"
            "akulaku" -> "Akulaku"
            null, "" -> null
            else -> method?.replace('_', ' ')?.replaceFirstChar { it.uppercase() }
        }
}

data class PetShopOrdersResponse(
    val success: Boolean,
    val message: String? = null,
    val data: List<PetShopOrder>? = null,
    val meta: PetShopMeta? = null
)

data class PetShopOrderResponse(
    val success: Boolean,
    val message: String? = null,
    val data: PetShopOrder? = null
)

// ============= PEMBAYARAN =============

data class PetShopPayment(
    @SerializedName("payment_code") val paymentCode: String? = null,
    @SerializedName("provider_order_id") val providerOrderId: String? = null,
    val status: String? = null,
    @SerializedName("gross_amount") val grossAmount: Long = 0,
    @SerializedName("snap_token") val snapToken: String? = null,
    @SerializedName("redirect_url") val redirectUrl: String? = null,
    @SerializedName("expires_at") val expiresAt: String? = null
)

data class PetShopPaymentResponse(
    val success: Boolean,
    val message: String? = null,
    val data: PetShopPayment? = null
)

data class PetShopPaymentSummary(
    @SerializedName("payment_code") val paymentCode: String? = null,
    val status: String? = null,
    @SerializedName("gross_amount") val grossAmount: Long = 0,
    @SerializedName("refunded_amount") val refundedAmount: Long = 0,
    @SerializedName("paid_at") val paidAt: String? = null
)

data class PetShopPaymentStatusData(
    @SerializedName("order_id") val orderId: Int = 0,
    @SerializedName("order_status") val orderStatus: String = "",
    @SerializedName("payment_status") val paymentStatus: String = "",
    @SerializedName("payment_method") val paymentMethod: String = "",
    val payment: PetShopPaymentSummary? = null
)

data class PetShopPaymentStatusResponse(
    val success: Boolean,
    val message: String? = null,
    val data: PetShopPaymentStatusData? = null
)

// ============= REQUEST =============

data class PetShopOrderLineRequest(
    @SerializedName("variant_id") val variantId: Int,
    val quantity: Int
)

data class PetShopOrderRequest(
    val items: List<PetShopOrderLineRequest>,
    @SerializedName("payment_method") val paymentMethod: String,
    val notes: String? = null,
    @SerializedName("customer_phone") val customerPhone: String? = null
)

// ============= KLINIK TERDEKAT (CLICK & COLLECT) =============

data class PetShopClinicProductAvailability(
    @SerializedName("clinic_product_id") val clinicProductId: Int = 0,
    @SerializedName("is_available") val isAvailable: Boolean = false,
    @SerializedName("can_fulfil_quantity") val canFulfilQuantity: Boolean = false,
    @SerializedName("available_stock") val availableStock: Int = 0,
    @SerializedName("total_available_stock") val totalAvailableStock: Int = 0,
    @SerializedName("price_min") val priceMin: Long = 0,
    @SerializedName("price_max") val priceMax: Long = 0,
    @SerializedName("selected_variant_id") val selectedVariantId: Int? = null,
    val variants: List<PetShopVariant> = emptyList()
)

/**
 * Satu klinik yang bisa dijadikan tempat pengambilan.
 * `distanceKm == null` berarti koordinat belum tersedia / lokasi ditolak — UI
 * tidak boleh menampilkan angka jarak palsu.
 */
data class PetShopClinicAvailability(
    val id: Int = 0,
    val name: String = "",
    val slug: String? = null,
    val address: String? = null,
    val city: String? = null,
    val district: String? = null,
    @SerializedName("postal_code") val postalCode: String? = null,
    val phone: String? = null,
    @SerializedName("logo_url") val logoUrl: String? = null,
    val latitude: Double? = null,
    val longitude: Double? = null,
    @SerializedName("pickup_enabled") val pickupEnabled: Boolean = true,
    @SerializedName("is_open_now") val isOpenNow: Boolean? = null,
    @SerializedName("distance_km") val distanceKm: Double? = null,
    @SerializedName("distance_label") val distanceLabel: String? = null,
    val product: PetShopClinicProductAvailability? = null
)

data class PetShopClinicFilters(
    val cities: List<String> = emptyList(),
    val districts: List<String> = emptyList(),
    @SerializedName("pickup_clinics_total") val pickupClinicsTotal: Int = 0
)

// ============= BUKTI PEMBAYARAN =============

data class PetShopReceiptCustomer(
    val name: String? = null,
    val phone: String? = null
)

data class PetShopReceiptClinic(
    val name: String? = null,
    val address: String? = null,
    val phone: String? = null,
    @SerializedName("logo_url") val logoUrl: String? = null,
    @SerializedName("primary_color") val primaryColor: String? = null
)

data class PetShopReceiptPickup(
    @SerializedName("clinic_name") val clinicName: String? = null,
    @SerializedName("clinic_address") val clinicAddress: String? = null,
    @SerializedName("clinic_phone") val clinicPhone: String? = null,
    val note: String? = null
)

data class PetShopReceiptItem(
    @SerializedName("product_name") val productName: String = "",
    @SerializedName("variant_name") val variantName: String? = null,
    val sku: String? = null,
    @SerializedName("unit_price") val unitPrice: Long = 0,
    @SerializedName("discount_price") val discountPrice: Long? = null,
    val quantity: Int = 0,
    val subtotal: Long = 0
)

data class PetShopReceiptTotals(
    val subtotal: Long = 0,
    @SerializedName("discount_total") val discountTotal: Long = 0,
    @SerializedName("total_amount") val totalAmount: Long = 0,
    @SerializedName("paid_amount") val paidAmount: Long = 0,
    @SerializedName("refunded_amount") val refundedAmount: Long = 0,
    @SerializedName("outstanding_amount") val outstandingAmount: Long = 0,
    val currency: String = "IDR"
)

data class PetShopReceiptPayment(
    val method: String = "",
    @SerializedName("method_label") val methodLabel: String? = null,
    val status: String = "",
    @SerializedName("receipt_status") val receiptStatus: String? = null,
    @SerializedName("receipt_status_label") val receiptStatusLabel: String? = null,
    @SerializedName("payment_code") val paymentCode: String? = null,
    @SerializedName("provider_transaction_id") val providerTransactionId: String? = null,
    @SerializedName("paid_at") val paidAt: String? = null,
    @SerializedName("merchant_label") val merchantLabel: String? = null,
    @SerializedName("merchant_id") val merchantId: String? = null
)

data class PetShopReceiptTimeline(
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("paid_at") val paidAt: String? = null,
    @SerializedName("ready_at") val readyAt: String? = null,
    @SerializedName("completed_at") val completedAt: String? = null
)

data class PetShopReceipt(
    @SerializedName("receipt_number") val receiptNumber: String = "",
    @SerializedName("order_number") val orderNumber: String = "",
    @SerializedName("is_settled") val isSettled: Boolean = false,
    @SerializedName("status_label") val statusLabel: String? = null,
    val customer: PetShopReceiptCustomer? = null,
    val clinic: PetShopReceiptClinic? = null,
    val pickup: PetShopReceiptPickup? = null,
    val items: List<PetShopReceiptItem> = emptyList(),
    val totals: PetShopReceiptTotals? = null,
    val payment: PetShopReceiptPayment? = null,
    val timeline: PetShopReceiptTimeline? = null
)

// ============= QR PICKUP PASS =============

data class PetShopPickupPassClinic(
    val id: Int = 0,
    val name: String? = null,
    val address: String? = null,
    val phone: String? = null,
    val latitude: Double? = null,
    val longitude: Double? = null
)

data class PetShopPickupPass(
    @SerializedName("order_number") val orderNumber: String = "",
    val status: String = "",
    @SerializedName("status_label") val statusLabel: String? = null,
    @SerializedName("qr_payload") val qrPayload: String? = null,
    @SerializedName("manual_code") val manualCode: String? = null,
    @SerializedName("expires_at") val expiresAt: String? = null,
    val clinic: PetShopPickupPassClinic? = null,
    @SerializedName("payment_status") val paymentStatus: String? = null,
    @SerializedName("payment_status_label") val paymentStatusLabel: String? = null,
    val instructions: String? = null,
    val items: List<PetShopPickupPassItem> = emptyList()
)

data class PetShopPickupPassItem(
    @SerializedName("product_name") val productName: String = "",
    @SerializedName("variant_name") val variantName: String? = null,
    val quantity: Int = 0
)

data class PetShopReceiptResponse(
    val success: Boolean,
    val message: String? = null,
    val data: PetShopReceipt? = null
)

data class PetShopPickupPassResponse(
    val success: Boolean,
    val message: String? = null,
    val data: PetShopPickupPass? = null
)

data class PetShopClinicsResponse(
    val success: Boolean,
    val message: String? = null,
    val data: List<PetShopClinicAvailability>? = null
)

data class PetShopClinicFiltersResponse(
    val success: Boolean,
    val message: String? = null,
    val data: PetShopClinicFilters? = null
)
