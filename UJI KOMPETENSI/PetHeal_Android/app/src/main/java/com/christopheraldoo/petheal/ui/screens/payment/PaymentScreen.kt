package com.christopheraldoo.petheal.ui.screens.payment

import android.annotation.SuppressLint
import android.graphics.Bitmap
import android.util.Log
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.activity.compose.BackHandler
import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.model.Booking
import com.christopheraldoo.petheal.data.model.User

private const val TAG = "PaymentScreen"

// Brand tokens
private val PayPrimary = Color(0xFF18C964)
private val PayBgDark = Color(0xFFF6F8F6)
private val PayBgLight = Color(0xFFF6F8F6)
private val PaySurfaceDark = Color.White
private val PaySurfaceLight = Color(0xFFFFFFFF)

/**
 * Enhanced payment screen with complete post-payment UX.
 * Uses WebView for Midtrans Snap payment with proper callback detection.
 * Features:
 * - Animated success/pending/failed dialogs
 * - Automatic booking status refresh on exit
 * - Proper edge case handling
 */
@Composable
fun PaymentScreen(
    booking: Booking,
    user: User?,
    isDpPayment: Boolean,
    totalAmount: Double,
    onPaymentSuccess: (String) -> Unit,
    onPaymentPending: (String) -> Unit,
    onPaymentFailed: (String) -> Unit,
    onNavigateBack: () -> Unit,
    onBookingUpdated: () -> Unit = {},
    isRemainingPayment: Boolean = false, // NEW: Flag to indicate this is a remaining payment
    medicalRecordId: Int? = null,
    // Kode enabled_payments Midtrans dari pilihan di Buat Booking.
    // Null/empty = daftar lengkap (al Behavior lama untuk sisa/medical).
    enabledPayments: List<String>? = null,
    viewModel: PaymentViewModel = hiltViewModel()
) {
    val state by viewModel.uiState.collectAsState()
    val isDark = false
    val bgColor = if (isDark) PayBgDark else PayBgLight
    val textPrimary = if (isDark) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)
    val paymentContextTitle = when {
        medicalRecordId != null -> "Pembayaran Rekam Medis"
        isRemainingPayment -> "Pembayaran Sisa Booking"
        else -> "Pembayaran Booking"
    }
    val paymentContextSubtitle = when {
        medicalRecordId != null -> "Lunasi biaya tindakan dan obat tambahan untuk membuka rekam medis lengkap."
        isRemainingPayment -> "Lunasi sisa pembayaran booking ini."
        isDpPayment -> "Amankan jadwal dengan membayar uang muka (DP)."
        else -> "Selesaikan pembayaran booking untuk mengonfirmasi konsultasi."
    }

    var showResultDialog by remember { mutableStateOf(false) }
    var resultDialogType by remember { mutableStateOf<String?>(null) }
    var resultOrderId by remember { mutableStateOf("") }

    // Booking id is backend-assigned and nullable in the DTO: never drive the
    // payment flow with a fabricated `0` id.
    val bookingId = booking.id

    // System back must run the same status verification as the close button —
    // leaving mid-checkout without syncing leaves booking/payment out of sync.
    BackHandler(enabled = state.snapToken != null || state.snapRedirectUrl != null) {
        bookingId?.let { viewModel.checkPaymentStatusOnExit(it) } ?: onNavigateBack()
    }

    // Initiate payment on first load. Guard: ViewModel selamat dari rotasi,
    // tapi LaunchedEffect(Unit) jalan ulang — tanpa guard ini satu rotasi
    // = satu Snap token/order baru (duplikat order di backend).
    LaunchedEffect(Unit) {
        val s = viewModel.uiState.value
        if (s.snapToken != null || s.snapRedirectUrl != null || s.isLoading || s.isPaymentCompleted) {
            return@LaunchedEffect
        }
        if (bookingId == null && medicalRecordId == null) return@LaunchedEffect
        val safeBookingId = bookingId ?: 0

        if (medicalRecordId != null) {
            viewModel.initiateMedicalRecordExtraPayment(medicalRecordId)
        } else if (isRemainingPayment) {
            // For remaining payment, call the remaining payment endpoint
            viewModel.initiateRemainingPayment(safeBookingId, user)
        } else {
            // For initial payment (DP or full)
            val amount = if (isDpPayment) booking.dpAmount ?: totalAmount else booking.totalAmount ?: totalAmount
            viewModel.initiatePayment(
                booking = booking, user = user, isDpPayment = isDpPayment,
                totalAmount = amount, bookingId = safeBookingId,
                enabledPayments = enabledPayments
            )
        }
    }

    // Handle payment completion with delay for better UX.
    // Guard paymentNavigated: tanpa ini, rotasi selama jeda delay
    // memicu navigasi sukses/pending dua kali (backstack ganda).
    var paymentNavigated by remember { mutableStateOf(false) }
    LaunchedEffect(state.isPaymentCompleted) {
        val result = state.paymentResult
        if (state.isPaymentCompleted && result != null && !showResultDialog && !paymentNavigated) {
            paymentNavigated = true
            resultOrderId = result.orderId
            resultDialogType = result.status
            showResultDialog = true
            
            when (result.status) {
                "success" -> {
                    onBookingUpdated()
                    kotlinx.coroutines.delay(3000)
                    onPaymentSuccess(result.orderId)
                }
                "pending" -> {
                    onBookingUpdated()
                    kotlinx.coroutines.delay(4000)
                    onPaymentPending(result.orderId)
                }
                else -> {
                    kotlinx.coroutines.delay(3000)
                    onPaymentFailed(result.message ?: "Payment failed")
                }
            }
        }
    }

    Box(modifier = Modifier.fillMaxSize().background(bgColor)) {
        when {
            bookingId == null && medicalRecordId == null -> {
                // Backend never issued an id for this booking — do not mint
                // payments against a fabricated id.
                PaymentErrorScreen(
                    title = paymentContextTitle,
                    subtitle = paymentContextSubtitle,
                    error = "Data booking tidak valid. Kembali dan coba lagi.",
                    onRetry = onNavigateBack,
                    onNavigateBack = onNavigateBack,
                    textPrimary = textPrimary,
                    textSecondary = textSecondary
                )
            }
            state.isLoading && state.snapToken == null && state.snapRedirectUrl == null -> {
                Box(Modifier.fillMaxSize().padding(24.dp), contentAlignment = Alignment.Center) {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(28.dp),
                        colors = CardDefaults.cardColors(containerColor = Color.White),
                        elevation = CardDefaults.cardElevation(0.dp),
                        border = BorderStroke(1.dp, Color(0xFFE2E8F0))
                    ) {
                    Column(
                        modifier = Modifier.padding(28.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        val infiniteTransition = rememberInfiniteTransition()
                        val scale by infiniteTransition.animateFloat(
                            initialValue = 0.8f, targetValue = 1.2f,
                            animationSpec = infiniteRepeatable(animation = tween(1000), repeatMode = RepeatMode.Reverse)
                        )
                        Box(
                            modifier = Modifier
                                .size(86.dp)
                                .clip(RoundedCornerShape(28.dp))
                                .background(PayPrimary.copy(alpha = 0.10f)),
                            contentAlignment = Alignment.Center
                        ) {
                            CircularProgressIndicator(color = PayPrimary, modifier = Modifier.scale(scale), strokeWidth = 4.dp)
                        }
                        Text(paymentContextTitle, fontSize = 16.sp, fontWeight = FontWeight.SemiBold, color = textPrimary)
                        Text("Menyiapkan pembayaran yang aman", fontSize = 22.sp, fontWeight = FontWeight.Bold, color = textPrimary, textAlign = TextAlign.Center)
                        Text(paymentContextSubtitle, fontSize = 12.sp, color = textSecondary, textAlign = TextAlign.Center)
                        midtransCodesLabel(enabledPayments)?.let { methodLabel ->
                            Surface(
                                shape = RoundedCornerShape(50),
                                color = PayPrimary.copy(alpha = 0.1f),
                                border = BorderStroke(1.dp, PayPrimary.copy(alpha = 0.25f))
                            ) {
                                Row(
                                    modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Icon(Icons.Filled.Payment, null, tint = PayPrimary, modifier = Modifier.size(16.dp))
                                    Spacer(Modifier.width(6.dp))
                                    Text("Midtrans: $methodLabel", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = PayPrimary)
                                }
                            }
                        }
                        Surface(
                            shape = RoundedCornerShape(18.dp),
                            color = Color(0xFFF8FAFC),
                            border = BorderStroke(1.dp, Color(0xFFE2E8F0))
                        ) {
                            Row(
                                modifier = Modifier.fillMaxWidth().padding(14.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text("Total", color = textSecondary, fontSize = 13.sp, fontWeight = FontWeight.Medium)
                                Text(
                                    "Rp ${String.format("%,.0f", totalAmount).replace(",", ".")}",
                                    color = textPrimary,
                                    fontSize = 17.sp,
                                    fontWeight = FontWeight.Bold
                                )
                            }
                        }
                    }
                    }
                }
            }
            state.error != null && state.snapToken == null && state.snapRedirectUrl == null -> {
                PaymentErrorScreen(
                    title = paymentContextTitle,
                    subtitle = paymentContextSubtitle,
                    error = state.error ?: "Unknown error",
                    // PHASE 7: guard against retry spam — every retry mints a
                    // fresh Snap token server-side.
                    onRetry = {
                        if (!state.isLoading) {
                            when {
                                medicalRecordId != null -> viewModel.initiateMedicalRecordExtraPayment(medicalRecordId)
                                isRemainingPayment -> bookingId?.let { viewModel.initiateRemainingPayment(it, user) }
                                else -> bookingId?.let { viewModel.initiatePayment(booking, user, isDpPayment, totalAmount, it, enabledPayments) }
                            }
                        }
                    },
                    onNavigateBack = onNavigateBack,
                    textPrimary = textPrimary,
                    textSecondary = textSecondary
                )
            }
            state.snapRedirectUrl != null || state.snapToken != null -> {
                val webBookingId = bookingId
                if (webBookingId == null && medicalRecordId == null) {
                    PaymentErrorScreen(
                        title = paymentContextTitle,
                        subtitle = paymentContextSubtitle,
                        error = "Data booking tidak valid. Kembali dan coba lagi.",
                        onRetry = onNavigateBack,
                        onNavigateBack = onNavigateBack,
                        textPrimary = textPrimary,
                        textSecondary = textSecondary
                    )
                } else {
                    PaymentWebView(
                        redirectUrl = state.snapRedirectUrl,
                        bookingId = webBookingId ?: 0,
                        orderId = state.orderId, // Use the order ID stored when Snap token was created
                        paymentContextTitle = paymentContextTitle,
                        onPaymentResult = { orderId, status, paymentType, background ->
                            Log.d(TAG, "Payment callback: orderId=$orderId, status=$status")
                            viewModel.handlePaymentResult(orderId, status, paymentType, background)
                        },
                        onClose = {
                            Log.d(TAG, "User exited payment, checking status")
                            webBookingId?.let { viewModel.checkPaymentStatusOnExit(it) }
                        },
                        onCheckStatus = {
                            Log.d(TAG, "Manual status check requested")
                            webBookingId?.let { viewModel.refreshBookingStatus(it) }
                        },
                        quietTick = state.quietPollTick,
                        onError = { error -> Log.e(TAG, "WebView error: $error") },
                        textPrimary = textPrimary
                    )
                }
            }
            else -> {
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = PayPrimary)
                }
            }
        }

        // Animated Result Dialog Overlay
        AnimatedVisibility(
            visible = showResultDialog && resultDialogType != null,
            enter = fadeIn(animationSpec = tween(300)) + scaleIn(initialScale = 0.8f, animationSpec = tween(300)),
            exit = fadeOut(animationSpec = tween(300)) + scaleOut(targetScale = 0.8f, animationSpec = tween(300))
        ) {
            PaymentResultDialog(
                status = resultDialogType ?: "failed",
                orderId = resultOrderId,
                paymentType = state.paymentResult?.paymentType,
                titleOverride = paymentContextTitle,
                onDismiss = { showResultDialog = false },
                modifier = Modifier.align(Alignment.Center)
            )
        }
    }
}

/**
 * WebView component with improved callback detection.
 * Header is now properly clickable (fixed z-order issue).
 */
@SuppressLint("SetJavaScriptEnabled")
@Composable
private fun PaymentWebView(
    redirectUrl: String?,
    bookingId: Int,
    orderId: String?,
    paymentContextTitle: String,
    onPaymentResult: (String, String?, String?, Boolean) -> Unit,
    onClose: () -> Unit,
    onCheckStatus: () -> Unit = {},
    onError: (String) -> Unit = {},
    textPrimary: Color,
    quietTick: Int = 0
) {
    val urlToLoad = redirectUrl ?: return
    var hasLoadedUrl by remember { mutableStateOf(false) }
    var isProcessingCallback by remember { mutableStateOf(false) }
    // Poll diam-diam yang masih pending membuka lagi deteksi berikutnya.
    LaunchedEffect(quietTick) {
        if (quietTick > 0) isProcessingCallback = false
    }

    Box(modifier = Modifier.fillMaxSize()) {
        // WebView in background
        AndroidView(
            factory = { context ->
                WebView(context).apply {
                    settings.javaScriptEnabled = true
                    settings.domStorageEnabled = true
                    settings.allowFileAccess = false
                    settings.allowContentAccess = false
                    settings.loadWithOverviewMode = true
                    settings.useWideViewPort = true
                    setPadding(0, 120, 0, 0)

                    webViewClient = object : WebViewClient() {
                        override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                            if (isProcessingCallback) return true
                            val url = request?.url?.toString() ?: return false
                            Log.d(TAG, "WebView navigated to payment URL")

                            // Check for petheal:// custom scheme (finish callback)
                            val isCustomScheme = url.startsWith("petheal://")
                            
                            // Also check for Midtrans finish callbacks with http/https
                            val isFinishCallback = url.contains("/finish", ignoreCase = true) &&
                                (url.contains("midtrans.com") || url.contains("app.sandbox.midtrans.com"))

                            if (isCustomScheme || isFinishCallback) {
                                isProcessingCallback = true
                                
                                // Parse the URL to extract payment result parameters
                                val uri = android.net.Uri.parse(url)
                                
                                // Use the stored order ID from UI state (set when Snap token was created)
                                // This ensures we use the correct order ID even if URL parameters are missing
                                val detectedOrderId = uri.getQueryParameter("order_id")
                                    ?: uri.getQueryParameter("orderId")
                                    ?: orderId ?: "BOOKING-$bookingId"
                                val status = uri.getQueryParameter("transaction_status")
                                val paymentType = uri.getQueryParameter("payment_type")

                                Log.d(TAG, "Payment callback intercepted: orderId=$detectedOrderId, status=$status")
                                
                                // Handle the payment result
                                // This will trigger backend polling if status is unknown
                                onPaymentResult(detectedOrderId, status, paymentType, false)
                                
                                // Don't close WebView immediately - let the result dialog handle navigation
                                // The WebView will be replaced by the dialog overlay
                                
                                return true
                            }
                            return false
                        }

                        override fun onPageFinished(view: WebView?, url: String?) {
                            super.onPageFinished(view, url)
                            hasLoadedUrl = true
                            // Metode yang tidak redirect ke /finish (simulator
                            // GoPay/DANA/QRIS) terdeteksi dari pola URL sukses.
                            // Status null → poll backend untuk kebenarannya.
                            val loaded = url.orEmpty()
                            if (!isProcessingCallback && isCompletionUrl(loaded)) {
                                isProcessingCallback = true
                                val uri = android.net.Uri.parse(loaded)
                                val detectedOrderId = uri.getQueryParameter("order_id")
                                    ?: uri.getQueryParameter("orderId")
                                    ?: orderId ?: "BOOKING-$bookingId"
                                Log.d(TAG, "Completion page detected, polling status for $detectedOrderId")
                                onPaymentResult(
                                    detectedOrderId,
                                    uri.getQueryParameter("transaction_status"),
                                    uri.getQueryParameter("payment_type"),
                                    true
                                )
                            }
                        }

                        override fun onReceivedError(
                            view: WebView?,
                            request: WebResourceRequest?,
                            error: WebResourceError?
                        ) {
                            super.onReceivedError(view, request, error)
                            // Deeplink ke aplikasi eksternal (gojek://, dana://)
                            // gagal dibuka WebView = user diarahkan keluar untuk
                            // bayar. Cek status sekali saat ia kembali.
                            val failing = request?.url?.toString().orEmpty()
                            if (!isProcessingCallback &&
                                request?.isForMainFrame == true &&
                                !(failing.startsWith("http://") || failing.startsWith("https://"))
                            ) {
                                isProcessingCallback = true
                                Log.d(TAG, "External deeplink after payment, polling status: $failing")
                                onPaymentResult(orderId ?: "BOOKING-$bookingId", null, null, true)
                            }
                        }
                    }
                }
            },
            modifier = Modifier.fillMaxSize()
        ) { webView ->
            if (!hasLoadedUrl && !isProcessingCallback) {
                Log.d(TAG, "Loading Midtrans URL")
                webView.loadUrl(urlToLoad)
            }
        }

        // Header overlay (CLICKABLE!)
        Column(modifier = Modifier.fillMaxWidth().align(Alignment.TopCenter)) {
            Row(
                modifier = Modifier.fillMaxWidth().statusBarsPadding().padding(horizontal = 8.dp).padding(top = 8.dp, bottom = 12.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                IconButton(onClick = onClose) {
                    Icon(Icons.Filled.Close, contentDescription = "Tutup pembayaran", tint = textPrimary)
                }
                Text(paymentContextTitle, fontSize = 17.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                // GoPay sandbox tidak selalu redirect balik setelah bayar —
                // tombol ini mem-poll status ke backend lalu lanjut otomatis.
                IconButton(onClick = onCheckStatus) {
                    Icon(Icons.Filled.Sync, contentDescription = "Cek status pembayaran", tint = textPrimary)
                }
            }
            androidx.compose.material3.Divider(color = Color(0xFFE2E8F0))
        }
    }
}

/**
 * Pola URL yang menandakan alur bayar selesai di halaman tersebut.
 * Hanya pola kuat (sukses/finish/settlement) — halaman instruksi VA atau
 * QR yang masih menunggu TIDAK mengandung pola ini, jadi tidak ada
 * pengecekan prematur yang mengusir user dari checkout.
 */
private fun isCompletionUrl(url: String): Boolean {
    val u = url.lowercase()
    if (u.startsWith("petheal://")) return true
    if (!u.startsWith("http://") && !u.startsWith("https://")) return false
    // Halaman Snap transaksi (vtweb) tidak pernah mengandung penanda ini.
    val markers = listOf(
        "transaction_status=capture",
        "transaction_status=settlement",
        "/finish", "finish?",
        "payment_success", "payment-success",
        "success", "successful", "settlement", "berhasil"
    )
    return markers.any { u.contains(it) }
}

/**
 * Animated payment result dialog overlay.
 */
@Composable
fun PaymentResultDialog(
    status: String,
    orderId: String,
    paymentType: String?,
    titleOverride: String? = null,
    onDismiss: () -> Unit,
    modifier: Modifier = Modifier
) {
    val isDark = false
    val bgColor = if (isDark) Color(0xFF1E293B) else Color.White
    val textPrimary = if (isDark) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)

    val icon: ImageVector
    val iconColor: Color
    val title: String
    val message: String
    when (status) {
        "success" -> { icon = Icons.Filled.CheckCircle; iconColor = PayPrimary; title = "Pembayaran Selesai"; message = "Pembayaran Anda tercatat. Status booking terbaru akan dimuat ulang otomatis." }
        "pending" -> { icon = Icons.Filled.Schedule; iconColor = Color(0xFFF59E0B); title = "Pembayaran Tertunda"; message = "Sesi pembayaran masih aktif. Selesaikan transaksi untuk membuka status terbaru." }
        "cancelled" -> { icon = Icons.Outlined.Cancel; iconColor = Color(0xFF94A3B8); title = "Pembayaran Dibatalkan"; message = "Tidak ada dana yang terpotong. Anda bisa memulai pembayaran lagi dari detail booking." }
        else -> { icon = Icons.Filled.Cancel; iconColor = Color(0xFFEF4444); title = "Pembayaran Gagal"; message = "Checkout tidak selesai. Silakan coba lagi." }
    }

    val infiniteTransition = rememberInfiniteTransition()
    val scale by infiniteTransition.animateFloat(
        initialValue = 1f, targetValue = if (status == "success") 1.1f else 1f,
        animationSpec = infiniteRepeatable(animation = tween(800), repeatMode = RepeatMode.Reverse)
    )

    Box(
        modifier = Modifier.fillMaxSize().background(Color.Black.copy(alpha = 0.5f)).clickable(onClick = onDismiss)
    ) {
        Card(
            modifier = modifier.fillMaxWidth().padding(24.dp),
            shape = RoundedCornerShape(24.dp),
            colors = CardDefaults.cardColors(containerColor = bgColor),
            elevation = CardDefaults.cardElevation(defaultElevation = 8.dp)
        ) {
            Column(
                modifier = Modifier.fillMaxWidth().padding(32.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                Box(
                    modifier = Modifier.size(80.dp).clip(CircleShape).background(iconColor.copy(alpha = 0.1f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(imageVector = icon, contentDescription = null, tint = iconColor, modifier = Modifier.size(50.dp).scale(scale))
                }
                titleOverride?.let {
                    Text(text = it, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = textSecondary, textAlign = TextAlign.Center)
                }
                Text(text = title, fontSize = 22.sp, fontWeight = FontWeight.Bold, color = textPrimary, textAlign = TextAlign.Center)
                Text(text = message, fontSize = 14.sp, color = textSecondary, textAlign = TextAlign.Center, lineHeight = 20.sp)
                
                if (orderId.isNotBlank()) {
                    Surface(shape = RoundedCornerShape(8.dp), color = if (isDark) Color(0xFF334155) else Color(0xFFF1F5F9)) {
                        Text(text = "Order: $orderId", fontSize = 11.sp, color = textSecondary, modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp), fontFamily = androidx.compose.ui.text.font.FontFamily.Monospace)
                    }
                }
                
                if (paymentType != null) {
                    Surface(shape = RoundedCornerShape(50), color = if (isDark) Color(0xFF334155) else Color(0xFFE2E8F0)) {
                        Row(
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 7.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(Icons.Filled.Payment, contentDescription = null, modifier = Modifier.size(16.dp), tint = textSecondary)
                            Spacer(Modifier.width(6.dp))
                            Text(text = paymentType.replace("_", " ").uppercase(), fontSize = 11.sp, fontWeight = FontWeight.Medium, color = textSecondary)
                        }
                    }
                }
                
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center, modifier = Modifier.padding(top = 8.dp)) {
                    CircularProgressIndicator(strokeWidth = 2.dp, modifier = Modifier.size(16.dp), color = textSecondary.copy(alpha = 0.5f))
                    Spacer(Modifier.width(8.dp))
                    Text(text = "Returning automatically...", fontSize = 11.sp, color = textSecondary.copy(alpha = 0.7f))
                }
            }
        }
    }
}

/**
 * Error screen for when snap token creation fails.
 */
@Composable
fun PaymentErrorScreen(
    title: String,
    subtitle: String,
    error: String,
    onRetry: () -> Unit,
    onNavigateBack: () -> Unit,
    textPrimary: Color,
    textSecondary: Color
) {
    Column(modifier = Modifier.fillMaxSize().padding(24.dp).verticalScroll(rememberScrollState()), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
        Box(modifier = Modifier.size(92.dp).clip(RoundedCornerShape(28.dp)).background(Color(0xFFEF4444).copy(alpha = 0.1f)), contentAlignment = Alignment.Center) {
            Icon(Icons.Filled.Error, contentDescription = null, tint = Color(0xFFEF4444), modifier = Modifier.size(40.dp))
        }
        Spacer(Modifier.height(24.dp))
        Text(title, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = textSecondary)
        Spacer(Modifier.height(4.dp))
        Text("Pembayaran Gagal Dimulai", fontSize = 24.sp, fontWeight = FontWeight.Bold, color = textPrimary, textAlign = TextAlign.Center)
        Spacer(Modifier.height(8.dp))
        Text(subtitle, fontSize = 13.sp, color = textSecondary, textAlign = TextAlign.Center, lineHeight = 20.sp)
        Spacer(Modifier.height(12.dp))
        Card(
            modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp),
            shape = RoundedCornerShape(20.dp),
            colors = CardDefaults.cardColors(containerColor = Color(0xFFEF4444).copy(alpha = 0.05f)),
            border = BorderStroke(1.dp, Color(0xFFEF4444).copy(alpha = 0.12f))
        ) {
            Text(error, fontSize = 14.sp, color = Color(0xFFDC2626), textAlign = TextAlign.Center, modifier = Modifier.padding(16.dp))
        }
        Spacer(Modifier.height(24.dp))
        Button(onClick = onRetry, modifier = Modifier.fillMaxWidth().height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.buttonColors(containerColor = PayPrimary, contentColor = Color.White)) {
            Icon(Icons.Filled.Refresh, null, modifier = Modifier.size(20.dp))
            Spacer(Modifier.width(8.dp))
            Text("Coba Lagi", fontWeight = FontWeight.Bold)
        }
        Spacer(Modifier.height(12.dp))
        OutlinedButton(onClick = onNavigateBack, modifier = Modifier.fillMaxWidth().height(48.dp), shape = RoundedCornerShape(12.dp)) {
            Icon(Icons.Filled.ArrowBack, null, modifier = Modifier.size(20.dp))
            Spacer(Modifier.width(8.dp))
            Text("Kembali", fontWeight = FontWeight.SemiBold)
        }
    }
}

/**
 * Payment result screen shown after the payment flow completes.
 */
@Composable
fun PaymentResultScreen(
    orderId: String,
    status: String,
    message: String?,
    onNavigateToBookings: () -> Unit,
    onNavigateBack: () -> Unit
) {
    val isDark = false
    val bgColor = if (isDark) PayBgDark else PayBgLight
    val surfaceColor = if (isDark) PaySurfaceDark else PaySurfaceLight
    val textPrimary = if (isDark) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)

    val icon: ImageVector
    val titleColor: Color
    val subtitle: String
    val eyebrow: String
    when (status) {
        "success" -> {
            icon = Icons.Filled.CheckCircle
            titleColor = PayPrimary
            subtitle = "Pembayaran tercatat dan status booking akan dimuat ulang."
            eyebrow = "Pembayaran booking diperbarui"
        }
        "pending" -> {
            icon = Icons.Filled.Schedule
            titleColor = Color(0xFFF59E0B)
            subtitle = "Transaksi masih tertunda. Selesaikan sebelum batas waktu agar booking dapat difinalisasi."
            eyebrow = "Menunggu penyelesaian"
        }
        else -> {
            icon = Icons.Filled.Cancel
            titleColor = Color(0xFFEF4444)
            subtitle = "Pembayaran tidak selesai. Anda bisa mencoba lagi dari detail booking."
            eyebrow = "Checkout perlu perhatian"
        }
    }

    Box(modifier = Modifier.fillMaxSize().background(bgColor)) {
        Column(modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(24.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Spacer(Modifier.height(56.dp))
            Surface(
                shape = RoundedCornerShape(32.dp),
                color = titleColor.copy(alpha = 0.1f),
                border = BorderStroke(1.dp, titleColor.copy(alpha = 0.15f))
            ) {
                Box(
                    modifier = Modifier
                        .size(112.dp)
                        .clip(RoundedCornerShape(32.dp)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(imageVector = icon, contentDescription = null, tint = titleColor, modifier = Modifier.size(60.dp))
                }
            }
            Spacer(Modifier.height(28.dp))
            Surface(
                shape = RoundedCornerShape(999.dp),
                color = surfaceColor,
                border = BorderStroke(1.dp, Color(0xFFE2E8F0))
            ) {
                Text(
                    text = eyebrow,
                    modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp),
                    fontSize = 11.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = textSecondary
                )
            }
            Spacer(Modifier.height(16.dp))
            Text(text = when (status) { "success" -> "Pembayaran Berhasil"; "pending" -> "Pembayaran Tertunda"; else -> "Pembayaran Gagal" }, fontSize = 26.sp, fontWeight = FontWeight.Bold, color = textPrimary, textAlign = TextAlign.Center)
            Spacer(Modifier.height(10.dp))
            Text(text = subtitle, fontSize = 14.sp, color = textSecondary, textAlign = TextAlign.Center, lineHeight = 22.sp)
            Spacer(Modifier.height(24.dp))
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(20.dp),
                colors = CardDefaults.cardColors(containerColor = surfaceColor),
                elevation = CardDefaults.cardElevation(0.dp)
            ) {
                Column(
                    modifier = Modifier.padding(18.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text("ID Pesanan", fontSize = 12.sp, color = textSecondary, fontWeight = FontWeight.Medium)
                        Text(orderId, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = textPrimary)
                    }

                    message?.let {
                        Surface(
                            shape = RoundedCornerShape(16.dp),
                            color = when (status) {
                                "success" -> PayPrimary.copy(alpha = 0.08f)
                                "pending" -> Color(0xFFF59E0B).copy(alpha = 0.08f)
                                else -> Color(0xFFEF4444).copy(alpha = 0.08f)
                            }
                        ) {
                            Text(
                                it,
                                fontSize = 13.sp,
                                color = when (status) {
                                    "success" -> PayPrimary
                                    "pending" -> Color(0xFFF59E0B)
                                    else -> Color(0xFFEF4444)
                                },
                                modifier = Modifier.padding(14.dp),
                                lineHeight = 20.sp
                            )
                        }
                    }
                }
            }
            Spacer(Modifier.height(32.dp))
            Button(onClick = onNavigateToBookings, modifier = Modifier.fillMaxWidth().height(54.dp), shape = RoundedCornerShape(16.dp), colors = ButtonDefaults.buttonColors(containerColor = PayPrimary, contentColor = Color.White)) {
                Icon(Icons.Filled.List, null, modifier = Modifier.size(20.dp))
                Spacer(Modifier.width(8.dp))
                Text("Buka Booking Saya", fontWeight = FontWeight.Bold, fontSize = 16.sp)
            }
            Spacer(Modifier.height(12.dp))
            OutlinedButton(
                onClick = onNavigateBack,
                modifier = Modifier.fillMaxWidth().height(52.dp),
                shape = RoundedCornerShape(16.dp),
                border = BorderStroke(1.dp, Color(0xFFE2E8F0))
            ) {
                Text("Kembali ke Beranda", color = textPrimary, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}
