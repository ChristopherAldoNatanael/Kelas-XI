package com.christopheraldoo.petheal.ui.screens.petshop

import android.content.Context
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.PetShopReceipt
import com.christopheraldoo.petheal.data.repository.PetShopRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import javax.inject.Inject

data class PetShopReceiptUiState(
    val isLoading: Boolean = true,
    val isBusy: Boolean = false,
    val error: String? = null,
    val receipt: PetShopReceipt? = null,
    val message: String? = null
)

@HiltViewModel
class PetShopReceiptViewModel @Inject constructor(
    private val repository: PetShopRepository,
    @ApplicationContext private val context: Context,
    savedStateHandle: SavedStateHandle
) : ViewModel() {

    private val orderId: Int = savedStateHandle.get<Int>("orderId") ?: 0

    private val _uiState = MutableStateFlow(PetShopReceiptUiState())
    val uiState: StateFlow<PetShopReceiptUiState> = _uiState.asStateFlow()

    init {
        load()
    }

    fun load() {
        if (orderId <= 0) {
            _uiState.update { it.copy(isLoading = false, error = "Pesanan tidak ditemukan.") }
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, error = null) }
            when (val result = repository.loadReceipt(orderId)) {
                is Result.Success -> _uiState.update {
                    it.copy(isLoading = false, receipt = result.data)
                }
                is Result.Error -> _uiState.update {
                    it.copy(isLoading = false, error = result.message)
                }
                else -> _uiState.update { it.copy(isLoading = false) }
            }
        }
    }

    /** Simpan PNG (bukan sekadar menampilkan toast: file benar-benar ditulis). */
    fun savePng() {
        runExport("Menyimpan gambar bukti pembayaran…") { receipt ->
            val bitmap = PetShopReceiptExporter.renderBitmap(receipt)
            val fileName = PetShopReceiptExporter.fileName(receipt, "png")
            val saved = PetShopReceiptExporter.savePngToGallery(context, bitmap, fileName)
            saved to "Bukti pembayaran disimpan sebagai PNG ($fileName)."
        }
    }

    fun sharePng() {
        runExport("Menyiapkan gambar…") { receipt ->
            val bitmap = PetShopReceiptExporter.renderBitmap(receipt)
            val fileName = PetShopReceiptExporter.fileName(receipt, "png")
            PetShopReceiptExporter.sharePng(context, bitmap, fileName)
            true to "Pilih aplikasi untuk membagikan bukti pembayaran."
        }
    }

    fun downloadPdf() {
        runExport("Mengunduh bukti pembayaran…") { receipt ->
            when (val result = repository.downloadReceiptPdf(orderId)) {
                is Result.Success -> {
                    val fileName = PetShopReceiptExporter.fileName(receipt, "pdf")
                    val saved = PetShopReceiptExporter.savePdfToDownloads(context, result.data, fileName)
                    saved to "Bukti pembayaran PDF disimpan ($fileName)."
                }
                is Result.Error -> false to result.message
                else -> false to "Unduhan gagal."
            }
        }
    }

    fun sharePdf() {
        runExport("Menyiapkan PDF…") { receipt ->
            when (val result = repository.downloadReceiptPdf(orderId)) {
                is Result.Success -> {
                    val fileName = PetShopReceiptExporter.fileName(receipt, "pdf")
                    PetShopReceiptExporter.sharePdf(context, result.data, fileName)
                    true to "Pilih aplikasi untuk membagikan PDF."
                }
                is Result.Error -> false to result.message
                else -> false to "PDF gagal disiapkan."
            }
        }
    }

    fun consumeMessage() {
        _uiState.update { it.copy(message = null) }
    }

    private fun runExport(busyMessage: String, block: suspend (PetShopReceipt) -> Pair<Boolean, String>) {
        val receipt = _uiState.value.receipt ?: return
        if (_uiState.value.isBusy) return

        viewModelScope.launch {
            _uiState.update { it.copy(isBusy = true, message = busyMessage, error = null) }
            val (success, message) = withContext(Dispatchers.IO) { block(receipt) }
            _uiState.update {
                it.copy(
                    isBusy = false,
                    message = message,
                    error = if (success) null else message
                )
            }
        }
    }
}
