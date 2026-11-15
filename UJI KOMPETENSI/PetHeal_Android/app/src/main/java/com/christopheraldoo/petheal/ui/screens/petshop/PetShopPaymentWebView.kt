package com.christopheraldoo.petheal.ui.screens.petshop

import android.annotation.SuppressLint
import android.util.Log
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Sync
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Divider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.Surface

private const val TAG = "PetShopPaymentWebView"

/**
 * Halaman pembayaran Midtrans IN-APP (WebView) untuk pesanan Pet Shop.
 *
 * Tidak pernah membuka browser luar: user membayar di dalam aplikasi, lalu
 * callback `[onCompleted]` dipakai aplikasi untuk memverifikasi status ke
 * server dan langsung kembali ke detail pesanan.
 */
@SuppressLint("SetJavaScriptEnabled")
@Composable
fun PetShopPaymentWebView(
    url: String,
    isSyncing: Boolean,
    onCompleted: () -> Unit,
    onClose: () -> Unit
) {
    var hasLoadedUrl by remember { mutableStateOf(false) }
    var callbackHandled by remember { mutableStateOf(false) }

    fun handleCallback() {
        if (callbackHandled) return
        callbackHandled = true
        onCompleted()
    }

    Box(modifier = Modifier.fillMaxSize().background(Surface)) {
        AndroidView(
            factory = { context ->
                WebView(context).apply {
                    settings.javaScriptEnabled = true
                    settings.domStorageEnabled = true
                    settings.allowFileAccess = false
                    settings.allowContentAccess = false
                    settings.loadWithOverviewMode = true
                    settings.useWideViewPort = true
                    setPadding(0, 96, 0, 0)

                    webViewClient = object : WebViewClient() {
                        override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                            val target = request?.url?.toString() ?: return false

                            // Callback resmi aplikasi (petheal://) atau halaman
                            // finish Midtrans → selesaikan pembayaran di app.
                            if (target.startsWith("petheal://") || isMidtransFinishUrl(target)) {
                                Log.d(TAG, "Payment callback intercepted: $target")
                                handleCallback()
                                return true
                            }

                            return false
                        }

                        override fun onPageFinished(view: WebView?, url: String?) {
                            super.onPageFinished(view, url)
                            hasLoadedUrl = true

                            // Metode tanpa redirect /finish (VA, QRIS, e-wallet
                            // simulator) terdeteksi dari pola URL sukses.
                            if (!callbackHandled && isCompletionUrl(url.orEmpty())) {
                                Log.d(TAG, "Completion page detected: $url")
                                handleCallback()
                            }
                        }

                        override fun onReceivedError(
                            view: WebView?,
                            request: WebResourceRequest?,
                            error: WebResourceError?
                        ) {
                            super.onReceivedError(view, request, error)

                            // Deeplink ke aplikasi e-wallet (gojek://, dana://)
                            // gagal di WebView → user bayar di luar, cek status
                            // saat kembali ke aplikasi.
                            val failing = request?.url?.toString().orEmpty()
                            if (!callbackHandled &&
                                request?.isForMainFrame == true &&
                                failing.isNotBlank() &&
                                !failing.startsWith("http://") &&
                                !failing.startsWith("https://")
                            ) {
                                Log.d(TAG, "External deeplink after payment: $failing")
                                handleCallback()
                            }
                        }
                    }
                }
            },
            modifier = Modifier.fillMaxSize()
        ) { webView ->
            if (!hasLoadedUrl && !callbackHandled) {
                webView.loadUrl(url)
            }
        }

        Column(
            modifier = Modifier
                .fillMaxWidth()
                .align(Alignment.TopCenter)
                .background(Surface)
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .statusBarsPadding()
                    .padding(horizontal = 8.dp)
                    .padding(top = 6.dp, bottom = 10.dp),
                horizontalArrangement = androidx.compose.foundation.layout.Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                IconButton(onClick = onClose) {
                    Icon(Icons.Filled.Close, contentDescription = "Tutup pembayaran", tint = OnBackground)
                }
                Text(
                    text = "Pembayaran",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    color = OnBackground
                )
                IconButton(onClick = { handleCallback() }, enabled = !isSyncing) {
                    if (isSyncing) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(18.dp),
                            color = Primary,
                            strokeWidth = 2.dp
                        )
                    } else {
                        Icon(Icons.Filled.Sync, contentDescription = "Cek status pembayaran", tint = OnBackground)
                    }
                }
            }
            Divider(color = Outline)
        }
    }
}

/**
 * Halaman finish Midtrans (dipakai sebagai callback pembayaran).
 */
private fun isMidtransFinishUrl(url: String): Boolean {
    val lower = url.lowercase()
    if (!lower.contains("/finish")) return false
    return lower.contains("midtrans.com")
}

/**
 * Pola URL yang menandakan alur bayar selesai. Hanya pola kuat (sukses /
 * finish / settlement) — halaman instruksi VA atau QR yang masih menunggu
 * TIDAK mengandung pola ini, jadi tidak ada pengecekan prematur.
 */
private fun isCompletionUrl(url: String): Boolean {
    val lower = url.lowercase()
    if (lower.startsWith("petheal://")) return true
    if (!lower.startsWith("http://") && !lower.startsWith("https://")) return false

    val markers = listOf(
        "transaction_status=capture",
        "transaction_status=settlement",
        "/finish",
        "finish?",
        "payment_success",
        "payment-success",
        "success",
        "settlement",
        "berhasil"
    )
    return markers.any { lower.contains(it) }
}
