package com.christopheraldoo.petheal.ui.screens.petshop

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.PetShopClinicAvailability
import com.christopheraldoo.petheal.data.model.PetShopProduct
import com.christopheraldoo.petheal.data.model.PetShopVariant
import com.christopheraldoo.petheal.data.repository.PetShopRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

data class PetShopProductUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val product: PetShopProduct? = null,
    val selectedVariantId: Int? = null,
    val quantity: Int = 1,
    val cartCount: Int = 0,
    val notice: String? = null,
    val addedToCart: Boolean = false,
    /** Klinik yang bisa dipilih sebagai tempat pengambilan, terurut jarak. */
    val pickupClinics: List<PetShopClinicAvailability> = emptyList(),
    val selectedClinicId: Int? = null,
    val isSwitchingClinic: Boolean = false,
    /** true = izin ada tetapi lokasi gagal didapat (GPS mati/timeout). */
    val locationUnavailable: Boolean = false,
    /** true = koordinat pengguna sudah dipakai untuk menghitung jarak. */
    val hasLocation: Boolean = false
) {
    val selectedVariant: PetShopVariant?
        get() = product?.variants?.firstOrNull { it.id == selectedVariantId }

    val selectedClinic: PetShopClinicAvailability?
        get() = pickupClinics.firstOrNull { it.id == selectedClinicId }

    /**
     * Add to cart hanya aktif bila varian yang dipilih pada klinik yang dipilih
     * benar-benar punya stok cukup. Klinik tanpa produk/stok tidak bisa dibeli.
     */
    val canAddToCart: Boolean
        get() {
            val variant = selectedVariant ?: return false
            val availability = selectedClinic?.product
            val stock = availability?.variants?.firstOrNull { it.id == variant.id }?.availableStock
                ?: variant.availableStock
            return variant.isAvailable && stock >= quantity && quantity > 0
        }
}

@HiltViewModel
class PetShopProductViewModel @Inject constructor(
    private val repository: PetShopRepository,
    savedStateHandle: SavedStateHandle
) : ViewModel() {

    // Argumen navigasi bertipe IntType, jadi harus dibaca sebagai Int.
    // Membacanya sebagai String memicu ClassCastException saat layar dibuka.
    private val productId: Int = savedStateHandle.get<Int>("productId") ?: 0

    private val _uiState = MutableStateFlow(PetShopProductUiState())
    val uiState: StateFlow<PetShopProductUiState> = _uiState.asStateFlow()

    private var latitude: Double? = null
    private var longitude: Double? = null

    init {
        viewModelScope.launch {
            repository.cart.collect { lines ->
                _uiState.update { it.copy(cartCount = lines.sumOf { line -> line.quantity }) }
            }
        }
        load()
    }

    fun load() {
        if (productId <= 0) {
            _uiState.update { it.copy(isLoading = false, error = "Produk tidak ditemukan.") }
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, error = null) }
            when (val result = repository.loadProduct(
                id = productId,
                latitude = latitude,
                longitude = longitude,
                quantity = _uiState.value.quantity
            )) {
                is Result.Success -> applyProduct(result.data, keepClinicId = null)
                is Result.Error -> _uiState.update {
                    it.copy(isLoading = false, error = result.message)
                }
                else -> _uiState.update { it.copy(isLoading = false) }
            }
        }
    }

    /** Dipanggil layar setelah izin lokasi diberikan dan fix didapat. */
    fun onLocationAvailable(lat: Double, lng: Double) {
        if (latitude == lat && longitude == lng && _uiState.value.hasLocation) return
        latitude = lat
        longitude = lng
        _uiState.update {
            it.copy(
                hasLocation = true,
                locationUnavailable = false
            )
        }
        reload()
    }

    /**
     * Izin sudah ada tetapi lokasi gagal didapat (GPS mati / timeout). Produk
     * tetap dimuat tanpa jarak, dan pengguna bisa mencoba lagi tanpa diminta
     * izin ulang.
     */
    fun onLocationUnavailable() {
        latitude = null
        longitude = null
        _uiState.update {
            it.copy(locationUnavailable = true, hasLocation = false)
        }
    }

    private fun reload() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, error = null) }
            when (val result = repository.loadProduct(
                id = productId,
                latitude = latitude,
                longitude = longitude,
                quantity = _uiState.value.quantity
            )) {
                is Result.Success -> applyProduct(result.data, keepClinicId = _uiState.value.selectedClinicId)
                is Result.Error -> _uiState.update { it.copy(isLoading = false, error = result.message) }
                else -> _uiState.update { it.copy(isLoading = false) }
            }
        }
    }

    private fun applyProduct(product: PetShopProduct, keepClinicId: Int?) {
        val clinics = product.pickupClinics.ifEmpty {
            // Backend mengirim daftar klinik pickup; bila kosong, tetap pakai
            // klinik penjual agar UI tidak kehilangan konteks pengambilan.
            val seller = product.clinic ?: return@ifEmpty emptyList()
            listOf(
                PetShopClinicAvailability(
                    id = seller.id ?: 0,
                    name = seller.name.orEmpty(),
                    slug = seller.slug,
                    address = seller.address,
                    phone = seller.phone,
                    logoUrl = seller.logoUrl,
                    product = null
                )
            )
        }

        val clinicId = keepClinicId?.takeIf { id -> clinics.any { it.id == id } }
            ?: product.clinic?.id
            ?: clinics.firstOrNull()?.id

        val availableVariantIds = clinics
            .firstOrNull { it.id == clinicId }
            ?.product
            ?.variants
            ?.filter { it.isAvailable && it.availableStock > 0 }
            ?.map { it.id }
            .orEmpty()

        val current = _uiState.value
        val variant = product.purchasableVariants.firstOrNull { it.id == current.selectedVariantId }
            ?: product.purchasableVariants.firstOrNull { availableVariantIds.isEmpty() || it.id in availableVariantIds }
            ?: product.purchasableVariants.firstOrNull()
            ?: product.variants.firstOrNull()

        _uiState.update {
            it.copy(
                isLoading = false,
                product = product,
                pickupClinics = clinics,
                selectedClinicId = clinicId,
                selectedVariantId = variant?.id,
                quantity = 1,
                isSwitchingClinic = false
            )
        }
    }

    /**
     * Pilih klinik pengambilan. Data harga/varian/stok WAJIB diambil ulang dari
     * produk klinik tersebut — bukan hanya mengganti label.
     */
    fun selectClinic(clinic: PetShopClinicAvailability) {
        val state = _uiState.value
        if (state.isSwitchingClinic || state.selectedClinicId == clinic.id) return

        val targetProductId = clinic.product?.clinicProductId ?: clinic.id

        if (targetProductId == state.product?.id || targetProductId <= 0) {
            // Klinik yang sama dengan produk yang sedang dibuka: cukup perbarui
            // pilihan; stok/harga sudah berasal dari satu produk ini.
            _uiState.update { it.copy(selectedClinicId = clinic.id) }
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(isSwitchingClinic = true, notice = null) }
            when (val result = repository.loadProduct(
                id = targetProductId,
                latitude = latitude,
                longitude = longitude,
                quantity = state.quantity
            )) {
                is Result.Success -> {
                    applyProduct(result.data, keepClinicId = clinic.id)
                    _uiState.update {
                        it.copy(
                            notice = "Klinik pengambilan diubah ke ${clinic.name}. Harga & stok diperbarui."
                        )
                    }
                }
                is Result.Error -> _uiState.update {
                    it.copy(isSwitchingClinic = false, notice = result.message)
                }
                else -> _uiState.update { it.copy(isSwitchingClinic = false) }
            }
        }
    }

    fun selectVariant(variantId: Int) {
        _uiState.update {
            val variant = it.product?.variants?.firstOrNull { v -> v.id == variantId }
            it.copy(
                selectedVariantId = variantId,
                // Jangan biarkan jumlah lama melebihi stok varian baru.
                quantity = it.quantity.coerceIn(1, maxOf(1, variant?.availableStock ?: 1))
            )
        }
    }

    fun incrementQuantity() {
        _uiState.update {
            val max = it.selectedVariant?.availableStock ?: 1
            it.copy(quantity = (it.quantity + 1).coerceAtMost(maxOf(1, max)))
        }
    }

    fun decrementQuantity() {
        _uiState.update { it.copy(quantity = (it.quantity - 1).coerceAtLeast(1)) }
    }

    fun addToCart() {
        val state = _uiState.value
        val product = state.product ?: return
        val variant = state.selectedVariant ?: return

        if (!state.canAddToCart) {
            _uiState.update {
                it.copy(notice = "Stok di klinik ini tidak mencukupi untuk jumlah yang dipilih.")
            }
            return
        }

        val error = repository.addToCart(product, variant, state.quantity)
        _uiState.update {
            it.copy(
                notice = error ?: "Ditambahkan ke keranjang.",
                addedToCart = error == null
            )
        }
    }

    fun consumeNotice() {
        _uiState.update { it.copy(notice = null) }
    }

    fun consumeAddedToCart() {
        _uiState.update { it.copy(addedToCart = false) }
    }
}
