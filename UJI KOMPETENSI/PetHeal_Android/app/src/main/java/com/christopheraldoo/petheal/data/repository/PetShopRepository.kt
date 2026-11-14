package com.christopheraldoo.petheal.data.repository

import android.util.Log
import com.christopheraldoo.petheal.data.model.PetShopCategory
import com.christopheraldoo.petheal.data.model.PetShopMeta
import com.christopheraldoo.petheal.data.model.PetShopOrder
import com.christopheraldoo.petheal.data.model.PetShopOrderLineRequest
import com.christopheraldoo.petheal.data.model.PetShopOrderRequest
import com.christopheraldoo.petheal.data.model.PetShopPayment
import com.christopheraldoo.petheal.data.model.PetShopPaymentStatusData
import com.christopheraldoo.petheal.data.model.PetShopProduct
import com.christopheraldoo.petheal.data.model.PetShopSeller
import com.christopheraldoo.petheal.data.model.PetShopVariant
import com.christopheraldoo.petheal.data.remote.ApiService
import com.christopheraldoo.petheal.data.remote.userFriendlyExceptionMessage
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import org.json.JSONObject
import retrofit2.Response
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Satu baris keranjang Pet Shop.
 *
 * Keranjang hanya hidup di memori selama sesi aplikasi: pesanan selalu
 * divalidasi ulang server (harga + stok), jadi menyimpan salinan lokal ke
 * disk hanya berisiko menampilkan harga kedaluwarsa.
 */
data class PetShopCartLine(
    val productId: Int,
    val variantId: Int,
    val productName: String,
    val variantName: String,
    val imageUrl: String?,
    val clinicId: Int,
    val clinicName: String,
    val unitPrice: Long,
    val quantity: Int,
    val availableStock: Int,
    /** Disalin dari klinik penjual saat masuk keranjang. */
    val onlinePaymentAvailable: Boolean = false
) {
    val lineTotal: Long get() = unitPrice * quantity
}

@Singleton
class PetShopRepository @Inject constructor(
    private val apiService: ApiService
) {
    companion object {
        private const val TAG = "PetShopRepository"
        private const val CART_PAGE_SIZE = 20
    }

    private val _cart = MutableStateFlow<List<PetShopCartLine>>(emptyList())
    val cart: StateFlow<List<PetShopCartLine>> = _cart.asStateFlow()

    // ── Katalog ─────────────────────────────────────────────────────────────

    suspend fun loadCategories(): Result<List<PetShopCategory>> = call {
        val response = apiService.getPetShopCategories()
        val body = response.body()
        if (response.isSuccessful && body?.success == true) {
            Result.Success(body.data ?: emptyList())
        } else {
            Result.Error(errorMessage(response, "Kategori produk gagal dimuat."))
        }
    }

    suspend fun loadSellers(): Result<List<PetShopSeller>> = call {
        val response = apiService.getPetShopSellers()
        val body = response.body()
        if (response.isSuccessful && body?.success == true) {
            Result.Success(body.data ?: emptyList())
        } else {
            Result.Error(errorMessage(response, "Daftar klinik penjual gagal dimuat."))
        }
    }

    /**
     * @return pasangan produk + meta paginasi. Produk tanpa stok tersedia
     *         memang tidak dikirim backend, jadi daftar kosong bukan bug UI.
     */
    suspend fun loadProducts(
        search: String? = null,
        categorySlug: String? = null,
        clinicId: Int? = null,
        sort: String? = null,
        page: Int = 1
    ): Result<Pair<List<PetShopProduct>, PetShopMeta?>> = call {
        val response = apiService.getPetShopProducts(
            search = search?.takeIf { it.isNotBlank() },
            category = categorySlug,
            clinicId = clinicId,
            sort = sort,
            page = page,
            perPage = CART_PAGE_SIZE
        )
        val body = response.body()
        if (response.isSuccessful && body?.success == true) {
            Result.Success((body.data ?: emptyList()) to body.meta)
        } else {
            Result.Error(errorMessage(response, "Produk gagal dimuat."))
        }
    }

    suspend fun loadProduct(id: Int): Result<PetShopProduct> = call {
        val response = apiService.getPetShopProduct(id)
        val body = response.body()
        if (response.isSuccessful && body?.success == true && body.data != null) {
            Result.Success(body.data)
        } else {
            Result.Error(errorMessage(response, "Detail produk tidak ditemukan."))
        }
    }

    // ── Keranjang ───────────────────────────────────────────────────────────

    /**
     * Tambah varian ke keranjang.
     *
     * Aturan yang ditegakkan di sini SAMA dengan server:
     * - satu pesanan hanya berisi produk dari satu klinik;
     * - jumlah per varian tidak boleh melebihi stok tersedia.
     *
     * @return pesan kesalahan, atau null bila berhasil.
     */
    fun addToCart(product: PetShopProduct, variant: PetShopVariant, quantity: Int = 1): String? {
        if (quantity <= 0) return "Jumlah tidak valid."
        if (!variant.isAvailable || variant.availableStock <= 0) return "Varian ini sedang habis."
        val clinicId = product.clinic?.id ?: return "Klinik penjual tidak diketahui."

        var error: String? = null

        _cart.update { current ->
            val otherClinic = current.firstOrNull { it.clinicId != clinicId }
            if (otherClinic != null) {
                error = "Satu pesanan hanya untuk satu klinik. Selesaikan keranjang ${otherClinic.clinicName} dulu, atau kosongkan keranjang."
                return@update current
            }

            val existing = current.firstOrNull { it.variantId == variant.id }
            val newQuantity = (existing?.quantity ?: 0) + quantity

            if (newQuantity > variant.availableStock) {
                error = "Stok ${product.name} tersisa ${variant.availableStock}."
                return@update current
            }

            if (existing != null) {
                current.map {
                    if (it.variantId == variant.id) it.copy(quantity = newQuantity) else it
                }
            } else {
                current + PetShopCartLine(
                    productId = product.id,
                    variantId = variant.id,
                    productName = product.name,
                    variantName = variant.name,
                    imageUrl = product.imageUrl,
                    clinicId = clinicId,
                    clinicName = product.clinic?.name.orEmpty(),
                    unitPrice = variant.effectivePrice,
                    quantity = quantity,
                    availableStock = variant.availableStock,
                    onlinePaymentAvailable = product.clinic?.onlinePaymentAvailable == true
                )
            }
        }

        return error
    }

    fun updateQuantity(variantId: Int, quantity: Int): String? {
        if (quantity <= 0) {
            removeFromCart(variantId)
            return null
        }

        var error: String? = null
        _cart.update { current ->
            val line = current.firstOrNull { it.variantId == variantId } ?: return@update current
            if (quantity > line.availableStock) {
                error = "Stok tersisa ${line.availableStock}."
                return@update current
            }
            current.map { if (it.variantId == variantId) it.copy(quantity = quantity) else it }
        }
        return error
    }

    fun removeFromCart(variantId: Int) {
        _cart.update { current -> current.filterNot { it.variantId == variantId } }
    }

    fun clearCart() {
        _cart.value = emptyList()
    }

    // ── Pesanan ─────────────────────────────────────────────────────────────

    suspend fun placeOrder(
        paymentMethod: String,
        notes: String? = null,
        customerPhone: String? = null
    ): Result<PetShopOrder> = call {
        val lines = _cart.value
        if (lines.isEmpty()) {
            return@call Result.Error("Keranjang masih kosong.")
        }

        val request = PetShopOrderRequest(
            items = lines.map { PetShopOrderLineRequest(it.variantId, it.quantity) },
            paymentMethod = paymentMethod,
            notes = notes?.takeIf { it.isNotBlank() },
            customerPhone = customerPhone?.takeIf { it.isNotBlank() }
        )

        val response = apiService.createPetShopOrder(request)
        val body = response.body()
        if (response.isSuccessful && body?.success == true && body.data != null) {
            clearCart()
            Result.Success(body.data)
        } else {
            val raw = runCatching { response.errorBody()?.string() }.getOrNull()
            val parsed = runCatching {
                if (raw.isNullOrBlank()) null
                else {
                    val json = org.json.JSONObject(raw)
                    val code = json.optString("code").takeIf { it.isNotBlank() }
                    val message = json.optString("message").takeIf { it.isNotBlank() }
                    if (code != null) {
                        return@call Result.Error(message ?: "Pesanan gagal dibuat.", code)
                    }
                    message
                }
            }.getOrNull()

            Result.Error(
                parsed ?: when {
                    response.code() == 401 -> "Sesi Anda berakhir. Silakan masuk kembali."
                    response.code() == 404 -> "Data tidak ditemukan."
                    response.code() == 422 -> "Pesanan gagal dibuat."
                    response.code() == 429 -> "Terlalu banyak permintaan. Coba lagi sebentar lagi."
                    response.code() >= 500 -> "Server sedang bermasalah. Coba lagi beberapa saat lagi."
                    else -> "Pesanan gagal dibuat."
                }
            )
        }
    }

    suspend fun loadOrders(page: Int = 1): Result<Pair<List<PetShopOrder>, PetShopMeta?>> = call {
        val response = apiService.getPetShopOrders(page)
        val body = response.body()
        if (response.isSuccessful && body?.success == true) {
            Result.Success((body.data ?: emptyList()) to body.meta)
        } else {
            Result.Error(errorMessage(response, "Pesanan gagal dimuat."))
        }
    }

    suspend fun loadOrder(id: Int): Result<PetShopOrder> = call {
        val response = apiService.getPetShopOrder(id)
        val body = response.body()
        if (response.isSuccessful && body?.success == true && body.data != null) {
            Result.Success(body.data)
        } else {
            Result.Error(errorMessage(response, "Pesanan tidak ditemukan."))
        }
    }

    suspend fun cancelOrder(id: Int, reason: String? = null): Result<PetShopOrder> = call {
        val response = apiService.cancelPetShopOrder(
            id,
            mapOf("reason" to reason.orEmpty())
        )
        val body = response.body()
        if (response.isSuccessful && body?.success == true && body.data != null) {
            Result.Success(body.data)
        } else {
            Result.Error(errorMessage(response, "Pesanan gagal dibatalkan."))
        }
    }

    /**
     * Buat transaksi online. `redirectUrl` dibuka di browser; status TIDAK
     * pernah dianggap lunas dari sini — layar pesanan memeriksa status ke
     * server setelah user kembali dari halaman pembayaran.
     */
    suspend fun createPayment(orderId: Int): Result<PetShopPayment> = call {
        val response = apiService.payPetShopOrder(orderId)
        val body = response.body()
        if (response.isSuccessful && body?.success == true && body.data != null) {
            Result.Success(body.data)
        } else {
            Result.Error(errorMessage(response, "Pembayaran tidak dapat dibuat."))
        }
    }

    suspend fun paymentStatus(orderId: Int): Result<PetShopPaymentStatusData> = call {
        val response = apiService.getPetShopPaymentStatus(orderId)
        val body = response.body()
        if (response.isSuccessful && body?.success == true && body.data != null) {
            Result.Success(body.data)
        } else {
            Result.Error(errorMessage(response, "Status pembayaran gagal diperiksa."))
        }
    }

    // ── Util ────────────────────────────────────────────────────────────────

    private suspend fun <T> call(block: suspend () -> Result<T>): Result<T> = try {
        block()
    } catch (e: Exception) {
        Log.e(TAG, "request failed", e)
        Result.Error(userFriendlyExceptionMessage(e))
    }

    /**
     * Pesan error backend dibaca SEKALI dari errorBody (stream Retrofit hanya
     * bisa dikonsumsi sekali). Envelope: `{success, message, code?}`.
     */
    private fun errorMessage(response: Response<*>, fallback: String): String {
        if (response.code() == 401) return "Sesi Anda berakhir. Silakan masuk kembali."
        val raw = runCatching { response.errorBody()?.string() }.getOrNull()
        val parsed = runCatching {
            if (raw.isNullOrBlank()) null else JSONObject(raw).optString("message").takeIf { it.isNotBlank() }
        }.getOrNull()
        if (!parsed.isNullOrBlank()) return parsed

        return when {
            response.code() == 404 -> "Data tidak ditemukan."
            response.code() == 422 -> fallback
            response.code() == 429 -> "Terlalu banyak permintaan. Coba lagi sebentar lagi."
            response.code() >= 500 -> "Server sedang bermasalah. Coba lagi beberapa saat lagi."
            else -> fallback
        }
    }
}
