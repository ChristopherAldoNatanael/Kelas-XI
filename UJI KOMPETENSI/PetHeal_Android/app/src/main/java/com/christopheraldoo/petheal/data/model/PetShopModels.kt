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
    val variants: List<PetShopVariant> = emptyList()
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
    val timeline: List<PetShopTimelineEntry> = emptyList()
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
