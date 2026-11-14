package com.christopheraldoo.petheal.ui.screens.petshop

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.asPaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBars
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountBalanceWallet
import androidx.compose.material.icons.filled.DeleteOutline
import androidx.compose.material.icons.filled.LocalHospital
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.ShoppingBag
import androidx.compose.material.icons.filled.Storefront
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.RadioButton
import androidx.compose.material3.RadioButtonDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.model.PetShopOrder
import com.christopheraldoo.petheal.data.repository.PetShopCartLine
import com.christopheraldoo.petheal.ui.components.EmptyState
import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.ErrorSoft
import com.christopheraldoo.petheal.ui.theme.InfoSoft
import com.christopheraldoo.petheal.ui.theme.Info
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.PetHealRadius
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.PrimaryLight
import com.christopheraldoo.petheal.ui.theme.Surface
import com.christopheraldoo.petheal.ui.theme.SurfaceMuted
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.util.formatRupiah

@Composable
fun PetShopCheckoutScreen(
    onNavigateBack: () -> Unit,
    onContinueShopping: () -> Unit,
    onOrderCreated: (Int) -> Unit,
    viewModel: PetShopCheckoutViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }

    LaunchedEffect(uiState.notice) {
        uiState.notice?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.consumeNotice()
        }
    }

    LaunchedEffect(uiState.placedOrder) {
        uiState.placedOrder?.let { order ->
            viewModel.consumePlacedOrder()
            onOrderCreated(order.id)
        }
    }

    Scaffold(
        containerColor = Background,
        snackbarHost = { SnackbarHost(snackbarHostState) },
        topBar = {
            PetShopHeader(
                title = "Keranjang",
                subtitle = if (uiState.isEmpty) null else "${uiState.itemCount} item · ${uiState.clinicName}",
                onBack = onNavigateBack
            )
        },
        bottomBar = {
            if (!uiState.isEmpty) {
                CheckoutBar(
                    total = uiState.subtotal,
                    isSubmitting = uiState.isSubmitting,
                    onSubmit = viewModel::placeOrder
                )
            }
        }
    ) { innerPadding ->
        if (uiState.isEmpty) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(innerPadding)
                    .background(Background)
            ) {
                EmptyState(
                    icon = Icons.Filled.ShoppingBag,
                    title = "Keranjang masih kosong",
                    description = "Tambahkan produk dari katalog Pet Shop untuk mulai membuat pesanan.",
                    actionText = "Lihat katalog",
                    onActionClick = onContinueShopping
                )
            }
            return@Scaffold
        }

        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .background(Background),
            contentPadding = PaddingValues(horizontal = 20.dp, vertical = 12.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            item {
                Text(
                    "Produk pesanan",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground
                )
            }

            items(uiState.lines, key = { it.variantId }) { line ->
                CartLineCard(
                    line = line,
                    onDecrease = { viewModel.updateQuantity(line.variantId, line.quantity - 1) },
                    onIncrease = { viewModel.updateQuantity(line.variantId, line.quantity + 1) },
                    onRemove = { viewModel.removeLine(line.variantId) }
                )
            }

            item {
                ClinicPickupCard(uiState.clinicName)
            }

            item {
                Text(
                    "Metode pembayaran",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground,
                    modifier = Modifier.padding(top = 4.dp)
                )
            }

            item {
                PaymentMethodOption(
                    title = "Bayar online",
                    description = if (uiState.isOnlineAvailable) {
                        "Bayar aman lewat Midtrans (transfer bank, QRIS, e-wallet, kartu kredit)."
                    } else {
                        "Tidak tersedia saat ini. Gunakan bayar di klinik."
                    },
                    icon = Icons.Filled.AccountBalanceWallet,
                    selected = uiState.paymentMethod == PetShopOrder.METHOD_ONLINE,
                    enabled = uiState.isOnlineAvailable,
                    onClick = { viewModel.onPaymentMethodSelected(PetShopOrder.METHOD_ONLINE) }
                )
            }

            item {
                PaymentMethodOption(
                    title = "Bayar di klinik",
                    description = "Bayar saat mengambil produk. Pesanan menunggu konfirmasi klinik.",
                    icon = Icons.Filled.Storefront,
                    selected = uiState.paymentMethod == PetShopOrder.METHOD_PAY_AT_CLINIC,
                    enabled = true,
                    onClick = { viewModel.onPaymentMethodSelected(PetShopOrder.METHOD_PAY_AT_CLINIC) }
                )
            }

            item {
                OutlinedTextField(
                    value = uiState.phone,
                    onValueChange = viewModel::onPhoneChange,
                    modifier = Modifier.fillMaxWidth(),
                    label = { Text("Nomor telepon (opsional)", fontSize = 12.sp) },
                    placeholder = { Text("08xxxxxxxxxx", fontSize = 13.sp) },
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                    shape = RoundedCornerShape(PetHealRadius.md),
                    colors = TextFieldDefaults.colors(
                        focusedContainerColor = Surface,
                        unfocusedContainerColor = Surface,
                        focusedIndicatorColor = Primary,
                        unfocusedIndicatorColor = Outline
                    )
                )
            }

            item {
                OutlinedTextField(
                    value = uiState.notes,
                    onValueChange = viewModel::onNotesChange,
                    modifier = Modifier.fillMaxWidth(),
                    label = { Text("Catatan untuk klinik (opsional)", fontSize = 12.sp) },
                    placeholder = { Text("Contoh: tolong siapkan sebelum jam 15.00", fontSize = 13.sp) },
                    minLines = 2,
                    maxLines = 4,
                    shape = RoundedCornerShape(PetHealRadius.md),
                    colors = TextFieldDefaults.colors(
                        focusedContainerColor = Surface,
                        unfocusedContainerColor = Surface,
                        focusedIndicatorColor = Primary,
                        unfocusedIndicatorColor = Outline
                    )
                )
            }

            if (uiState.error != null) {
                item { PetShopInlineError(uiState.error.orEmpty()) }
            }

            item {
                SummaryCard(subtotal = uiState.subtotal)
            }

            item {
                PetShopInfoBanner(
                    "Pesanan hanya bisa diambil di klinik penjual. Total akhir dihitung ulang oleh server saat pesanan dibuat."
                )
            }
        }
    }
}

@Composable
private fun CartLineCard(
    line: PetShopCartLine,
    onDecrease: () -> Unit,
    onIncrease: () -> Unit,
    onRemove: () -> Unit
) {
    Card(
        colors = CardDefaults.cardColors(containerColor = Surface),
        shape = RoundedCornerShape(PetHealRadius.lg),
        border = BorderStroke(1.dp, Outline),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Row(modifier = Modifier.padding(12.dp)) {
            PetShopImage(
                url = line.imageUrl,
                contentDescription = line.productName,
                modifier = Modifier
                    .size(64.dp)
                    .background(SurfaceMuted, RoundedCornerShape(PetHealRadius.md))
            )
            Spacer(Modifier.width(12.dp))
            Column(modifier = Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.Top) {
                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            text = line.productName,
                            fontSize = 13.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = OnBackground,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis
                        )
                        Text(
                            text = line.variantName,
                            fontSize = 11.sp,
                            color = TextSecondary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                    IconButton(onClick = onRemove, modifier = Modifier.size(28.dp)) {
                        Icon(
                            Icons.Filled.DeleteOutline,
                            contentDescription = "Hapus ${line.productName}",
                            tint = TextTertiary,
                            modifier = Modifier.size(18.dp)
                        )
                    }
                }

                Spacer(Modifier.height(8.dp))

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        text = formatRupiah(line.lineTotal.toDouble()),
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        color = Primary,
                        modifier = Modifier.weight(1f)
                    )
                    QuantityStepper(
                        quantity = line.quantity,
                        onDecrease = onDecrease,
                        onIncrease = onIncrease,
                        canIncrease = line.quantity < line.availableStock
                    )
                }

                if (line.quantity >= line.availableStock) {
                    Text(
                        "Maksimal stok tersedia",
                        fontSize = 10.sp,
                        color = TextTertiary
                    )
                }
            }
        }
    }
}

@Composable
private fun ClinicPickupCard(clinicName: String) {
    Card(
        colors = CardDefaults.cardColors(containerColor = InfoSoft),
        shape = RoundedCornerShape(PetHealRadius.lg),
        border = BorderStroke(1.dp, Info.copy(alpha = 0.2f)),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Row(
            modifier = Modifier.padding(14.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                Icons.Filled.LocalHospital,
                contentDescription = null,
                tint = Info,
                modifier = Modifier.size(20.dp)
            )
            Spacer(Modifier.width(10.dp))
            Column {
                Text(
                    "Diambil di $clinicName",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground
                )
                Text(
                    "Tunjukkan nomor pesanan saat mengambil produk.",
                    fontSize = 11.sp,
                    color = TextSecondary
                )
            }
        }
    }
}

@Composable
private fun PaymentMethodOption(
    title: String,
    description: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    selected: Boolean,
    enabled: Boolean,
    onClick: () -> Unit
) {
    Surface(
        onClick = onClick,
        enabled = enabled,
        shape = RoundedCornerShape(PetHealRadius.lg),
        color = if (selected) PrimaryLight else Surface,
        border = BorderStroke(1.dp, if (selected) Primary else Outline)
    ) {
        Row(
            modifier = Modifier.padding(14.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                icon,
                contentDescription = null,
                tint = if (enabled) Primary else TextTertiary,
                modifier = Modifier.size(20.dp)
            )
            Spacer(Modifier.width(12.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    title,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = if (enabled) OnBackground else TextTertiary
                )
                Text(
                    description,
                    fontSize = 11.sp,
                    lineHeight = 16.sp,
                    color = TextSecondary
                )
            }
            RadioButton(
                selected = selected,
                onClick = if (enabled) onClick else null,
                enabled = enabled,
                colors = RadioButtonDefaults.colors(selectedColor = Primary)
            )
        }
    }
}

@Composable
private fun SummaryCard(subtotal: Long) {
    Card(
        colors = CardDefaults.cardColors(containerColor = Surface),
        shape = RoundedCornerShape(PetHealRadius.lg),
        border = BorderStroke(1.dp, Outline),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(modifier = Modifier.fillMaxWidth()) {
                Text("Subtotal", fontSize = 12.sp, color = TextSecondary, modifier = Modifier.weight(1f))
                Text(
                    formatRupiah(subtotal.toDouble()),
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Medium,
                    color = OnBackground
                )
            }
            Spacer(Modifier.height(8.dp))
            Row(modifier = Modifier.fillMaxWidth()) {
                Text("Biaya layanan", fontSize = 12.sp, color = TextSecondary, modifier = Modifier.weight(1f))
                Text("Tidak ada", fontSize = 12.sp, color = TextSecondary)
            }
            Spacer(Modifier.height(10.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(Outline))
            Spacer(Modifier.height(10.dp))
            Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "Total",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground,
                    modifier = Modifier.weight(1f)
                )
                Text(
                    formatRupiah(subtotal.toDouble()),
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = Primary
                )
            }
        }
    }
}

@Composable
private fun CheckoutBar(total: Long, isSubmitting: Boolean, onSubmit: () -> Unit) {
    val navPadding = WindowInsets.navigationBars.asPaddingValues().calculateBottomPadding()
    Surface(color = Surface, shadowElevation = 12.dp) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp, vertical = 12.dp)
                .padding(bottom = navPadding),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text("Total pembayaran", fontSize = 10.sp, color = TextSecondary)
                Text(
                    formatRupiah(total.toDouble()),
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                    color = OnBackground
                )
            }
            Button(
                onClick = onSubmit,
                enabled = !isSubmitting,
                modifier = Modifier.height(46.dp),
                shape = RoundedCornerShape(PetHealRadius.md),
                colors = ButtonDefaults.buttonColors(containerColor = Primary),
                contentPadding = PaddingValues(horizontal = 22.dp)
            ) {
                if (isSubmitting) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(18.dp),
                        color = Color.White,
                        strokeWidth = 2.dp
                    )
                } else {
                    Icon(Icons.Filled.Payments, contentDescription = null, modifier = Modifier.size(17.dp))
                    Spacer(Modifier.width(8.dp))
                    Text("Buat Pesanan", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                }
            }
        }
    }
}
