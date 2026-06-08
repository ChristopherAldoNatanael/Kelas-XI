package com.christopheraldoo.petheal.ui.screens.payment

import android.annotation.SuppressLint
import android.graphics.Bitmap
import android.util.Log
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.compose.animation.*
import androidx.compose.animation.core.*
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
private val PayPrimary = Color(0xFF2BEE6C)
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
    viewModel: PaymentViewModel = hiltViewModel()
) {
    val state by viewModel.uiState.collectAsState()
    val isDark = false
    val bgColor = if (isDark) PayBgDark else PayBgLight
    val textPrimary = if (isDark) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)

    var showResultDialog by remember { mutableStateOf(false) }
    var resultDialogType by remember { mutableStateOf<String?>(null) }
    var resultOrderId by remember { mutableStateOf("") }

    // Initiate payment on first load
    LaunchedEffect(Unit) {
        val bookingId = booking.id ?: 0
        
        if (isRemainingPayment) {
            // For remaining payment, call the remaining payment endpoint
            viewModel.initiateRemainingPayment(bookingId, user)
        } else {
            // For initial payment (DP or full)
            val amount = if (isDpPayment) booking.dpAmount ?: totalAmount else booking.totalAmount ?: totalAmount
            viewModel.initiatePayment(
                booking = booking, user = user, isDpPayment = isDpPayment,
                totalAmount = amount, bookingId = bookingId
            )
        }
    }

    // Handle payment completion with delay for better UX
    LaunchedEffect(state.isPaymentCompleted) {
        if (state.isPaymentCompleted && state.paymentResult != null && !showResultDialog) {
            val result = state.paymentResult!!
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
            state.isLoading && state.snapToken == null && state.snapRedirectUrl == null -> {
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(16.dp)) {
                        val infiniteTransition = rememberInfiniteTransition()
                        val scale by infiniteTransition.animateFloat(
                            initialValue = 0.8f, targetValue = 1.2f,
                            animationSpec = infiniteRepeatable(animation = tween(1000), repeatMode = RepeatMode.Reverse)
                        )
                        CircularProgressIndicator(color = PayPrimary, modifier = Modifier.scale(scale), strokeWidth = 4.dp)
                        Text("Preparing secure payment...", fontSize = 16.sp, fontWeight = FontWeight.Medium, color = textPrimary)
                        Text("Please wait a moment", fontSize = 12.sp, color = textSecondary)
                    }
                }
            }
            state.error != null && state.snapToken == null && state.snapRedirectUrl == null -> {
                PaymentErrorScreen(error = state.error ?: "Unknown error", onRetry = {
                    viewModel.initiatePayment(booking, user, isDpPayment, totalAmount, booking.id ?: 0)
                }, onNavigateBack = onNavigateBack, textPrimary = textPrimary, textSecondary = textSecondary)
            }
            state.snapRedirectUrl != null || state.snapToken != null -> {
                PaymentWebView(
                    redirectUrl = state.snapRedirectUrl,
                    bookingId = booking.id ?: 0,
                    orderId = state.orderId, // Use the order ID stored when Snap token was created
                    onPaymentResult = { orderId, status, paymentType ->
                        Log.d(TAG, "Payment callback: orderId=$orderId, status=$status")
                        viewModel.handlePaymentResult(orderId, status, paymentType)
                    },
                    onClose = {
                        Log.d(TAG, "User exited payment, checking status")
                        viewModel.checkPaymentStatusOnExit(booking.id ?: 0)
                    },
                    onError = { error -> Log.e(TAG, "WebView error: $error") },
                    textPrimary = textPrimary
                )
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
    onPaymentResult: (String, String?, String?) -> Unit,
    onClose: () -> Unit,
    onError: (String) -> Unit = {},
    textPrimary: Color
) {
    val urlToLoad = redirectUrl ?: return
    var hasLoadedUrl by remember { mutableStateOf(false) }
    var isProcessingCallback by remember { mutableStateOf(false) }

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
                            Log.d(TAG, "WebView URL: $url")

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

                                Log.d(TAG, "Payment callback intercepted: orderId=$detectedOrderId, status=$status, url=$url")
                                
                                // Handle the payment result
                                // This will trigger backend polling if status is unknown
                                onPaymentResult(detectedOrderId, status, paymentType)
                                
                                // Don't close WebView immediately - let the result dialog handle navigation
                                // The WebView will be replaced by the dialog overlay
                                
                                return true
                            }
                            return false
                        }

                        override fun onPageFinished(view: WebView?, url: String?) {
                            super.onPageFinished(view, url)
                            hasLoadedUrl = true
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
                modifier = Modifier.fillMaxWidth().padding(horizontal = 8.dp).padding(top = 44.dp, bottom = 12.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                IconButton(onClick = onClose) {
                    Icon(Icons.Filled.Close, contentDescription = "Cancel Payment", tint = textPrimary)
                }
                Text("Complete Payment", fontSize = 17.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                IconButton(onClick = onClose) {
                    Icon(Icons.Filled.HelpOutline, contentDescription = "Help", tint = textPrimary)
                }
            }
            androidx.compose.material3.Divider(color = Color(0xFFE2E8F0))
        }
    }
}

/**
 * Animated payment result dialog overlay.
 */
@Composable
fun PaymentResultDialog(
    status: String,
    orderId: String,
    paymentType: String?,
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
        "success" -> { icon = Icons.Filled.CheckCircle; iconColor = PayPrimary; title = "Payment Successful!"; message = "Your booking is confirmed. Check your bookings for details." }
        "pending" -> { icon = Icons.Filled.Schedule; iconColor = Color(0xFFF59E0B); title = "Payment Pending"; message = "Please complete the payment to confirm your booking." }
        "cancelled" -> { icon = Icons.Outlined.Cancel; iconColor = Color(0xFF94A3B8); title = "Payment Canceled"; message = "You can try again anytime from your bookings." }
        else -> { icon = Icons.Filled.Cancel; iconColor = Color(0xFFEF4444); title = "Payment Failed"; message = "The payment was not completed. You can try again." }
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
                    Text(text = "Auto-redirecting...", fontSize = 11.sp, color = textSecondary.copy(alpha = 0.7f))
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
    error: String,
    onRetry: () -> Unit,
    onNavigateBack: () -> Unit,
    textPrimary: Color,
    textSecondary: Color
) {
    Column(modifier = Modifier.fillMaxSize().padding(24.dp).verticalScroll(rememberScrollState()), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
        Box(modifier = Modifier.size(80.dp).clip(RoundedCornerShape(16.dp)).background(Color(0xFFEF4444).copy(alpha = 0.1f)), contentAlignment = Alignment.Center) {
            Icon(Icons.Filled.Error, contentDescription = null, tint = Color(0xFFEF4444), modifier = Modifier.size(40.dp))
        }
        Spacer(Modifier.height(24.dp))
        Text("Payment Setup Failed", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = textPrimary)
        Spacer(Modifier.height(8.dp))
        Card(modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp), colors = CardDefaults.cardColors(containerColor = Color(0xFFEF4444).copy(alpha = 0.05f))) {
            Text(error, fontSize = 14.sp, color = Color(0xFFDC2626), textAlign = TextAlign.Center, modifier = Modifier.padding(16.dp))
        }
        Spacer(Modifier.height(24.dp))
        Button(onClick = onNavigateBack, modifier = Modifier.fillMaxWidth().height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.buttonColors(containerColor = PayPrimary, contentColor = Color(0xFF052E14))) {
            Icon(Icons.Filled.ArrowBack, null, modifier = Modifier.size(20.dp))
            Spacer(Modifier.width(8.dp))
            Text("Go Back", fontWeight = FontWeight.Bold)
        }
        Spacer(Modifier.height(12.dp))
        Button(onClick = onRetry, modifier = Modifier.fillMaxWidth().height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF3B82F6), contentColor = Color.White)) {
            Icon(Icons.Filled.Refresh, null, modifier = Modifier.size(20.dp))
            Spacer(Modifier.width(8.dp))
            Text("Try Again", fontWeight = FontWeight.Bold)
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

    val (icon, titleColor, subtitle) = when (status) {
        "success" -> Triple(Icons.Filled.CheckCircle, PayPrimary, "Your booking is now confirmed. You'll receive a notification shortly.")
        "pending" -> Triple(Icons.Filled.Schedule, Color(0xFFF59E0B), "Please complete your payment before the deadline to confirm your booking.")
        else -> Triple(Icons.Filled.Cancel, Color(0xFFEF4444), "The payment was not completed. You can try again or contact support.")
    }

    Box(modifier = Modifier.fillMaxSize().background(bgColor)) {
        Column(modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(24.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Spacer(Modifier.height(80.dp))
            Box(modifier = Modifier.size(100.dp).clip(RoundedCornerShape(24.dp)).background(titleColor.copy(alpha = 0.1f)), contentAlignment = Alignment.Center) {
                Icon(imageVector = icon, contentDescription = null, tint = titleColor, modifier = Modifier.size(60.dp))
            }
            Spacer(Modifier.height(32.dp))
            Text(text = when (status) { "success" -> "Payment Successful!"; "pending" -> "Payment Pending"; else -> "Payment Failed" }, fontSize = 24.sp, fontWeight = FontWeight.Bold, color = textPrimary)
            Spacer(Modifier.height(12.dp))
            Text(text = subtitle, fontSize = 14.sp, color = textSecondary, textAlign = TextAlign.Center)
            Spacer(Modifier.height(24.dp))
            Surface(shape = RoundedCornerShape(12.dp), color = surfaceColor, modifier = Modifier.fillMaxWidth()) {
                Column(modifier = Modifier.padding(16.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("Order ID", fontSize = 12.sp, color = textSecondary)
                    Spacer(Modifier.height(4.dp))
                    Text(orderId, fontSize = 14.sp, fontWeight = FontWeight.Medium, color = textPrimary)
                }
            }
            Spacer(Modifier.height(12.dp))
            message?.let {
                Surface(shape = RoundedCornerShape(12.dp), color = when (status) { "success" -> PayPrimary.copy(alpha = 0.1f); "pending" -> Color(0xFFF59E0B).copy(alpha = 0.1f); else -> Color(0xFFEF4444).copy(alpha = 0.1f) }, modifier = Modifier.fillMaxWidth()) {
                    Text(it, fontSize = 13.sp, color = when (status) { "success" -> PayPrimary; "pending" -> Color(0xFFF59E0B); else -> Color(0xFFEF4444) }, modifier = Modifier.padding(16.dp), textAlign = TextAlign.Center)
                }
            }
            Spacer(Modifier.height(40.dp))
            Button(onClick = onNavigateToBookings, modifier = Modifier.fillMaxWidth().height(52.dp), shape = RoundedCornerShape(14.dp), colors = ButtonDefaults.buttonColors(containerColor = PayPrimary, contentColor = Color(0xFF052E14))) {
                Icon(Icons.Filled.List, null, modifier = Modifier.size(20.dp))
                Spacer(Modifier.width(8.dp))
                Text("View My Bookings", fontWeight = FontWeight.Bold, fontSize = 16.sp)
            }
            Spacer(Modifier.height(12.dp))
            TextButton(onClick = onNavigateBack, modifier = Modifier.fillMaxWidth()) {
                Text("Back to Home", color = PayPrimary, fontWeight = FontWeight.Medium)
            }
        }
    }
}
