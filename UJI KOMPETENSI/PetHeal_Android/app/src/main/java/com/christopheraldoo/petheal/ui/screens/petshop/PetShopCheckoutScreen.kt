package com.christopheraldoo.petheal.ui.screens.petshop

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountBalanceWallet
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.LocalHospital
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.ShoppingBag
import androidx.compose.material.icons.filled.Storefront
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.RadioButton
import androidx.compose.material3.RadioButtonDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.model.PetShopOrder
import com.christopheraldoo.petheal.data.repository.PetShopCartLine
import com.christopheraldoo.petheal.ui.components.EmptyState
import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.Info
import com.christopheraldoo.petheal.ui.theme.InfoSoft
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
        snackbarHost = { SnackbarHost(snackbarHostState, modifier = Modifier.padding(16.dp)) },
        topBar = {
            PetShopHeader(
                title = "Keranjang",
                subtitle = if (uiState.isEmpty) "Belum ada produk dipilih"
                else "${uiState.itemCount} item · ${uiState.clinicName}",
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
            contentPadding = PaddingValues(horizontal = ShopTheme.gutter, vertical = 16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            item {
                ShopSectionTitle(
                    text = "Produk pesanan",
                    trailing = {
                        Row(
                            modifier = Modifier
                                .clip(RoundedCornerShape(PetHealRadius.pill))
                                .background(PrimaryLight)
                                .clickable(onClick = onContinueShopping)
                                .padding(horizontal = 10.dp, vertical = 5.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                imageVector = Icons.Filled.Add,
                                contentDescription = null,
                                tint = Primary,
                                modifier = Modifier.size(13.dp)
                            )
                            Spacer(Modifier.width(4.dp))
                            Text(
                                text = "Tambah produk",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = Primary
                            )
                        }
                    }
                )
            }

            item {
                // Satu kartu untuk semua baris keranjang, dipisah garis tipis:
                // lebih tenang dibanding tumpukan kartu per produk.
                ShopCard {
                    Column {
                        uiState.lines.forEachIndexed { index, line ->
                            CartLineRow(
                                line = line,
                                onDecrease = { viewModel.updateQuantity(line.variantId, line.quantity - 1) },
                                onIncrease = { viewModel.updateQuantity(line.variantId, line.quantity + 1) },
                                onRemove = { viewModel.removeLine(line.variantId) }
                            )
                            if (index != uiState.lines.lastIndex) {
                                Box(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(start = 84.dp)
                                        .height(1.dp)
                                        .background(Outline)
                                )
                            }
                        }
                    }
                }
            }

            item {
                ClinicPickupCard(uiState.clinicName)
            }

            item {
                Spacer(Modifier.height(4.dp))
                ShopSectionTitle("Metode pembayaran")
            }

            item {
                PaymentMethodOption(
                    title = "Bayar online",
                    description = if (uiState.isOnlineAvailable) {
                        "Aman lewat Midtrans: transfer bank, QRIS, e-wallet, kartu kredit."
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
                Spacer(Modifier.height(4.dp))
                ShopSectionTitle("Catatan & kontak")
            }

            item {
                ShopCard {
                    Column(
                        modifier = Modifier.padding(14.dp),
                        verticalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        ShopTextField(
                            value = uiState.phone,
                            onValueChange = viewModel::onPhoneChange,
                            label = "Nomor telepon (opsional)",
                            placeholder = "08xxxxxxxxxx",
                            keyboardType = KeyboardType.Phone
                        )
                        ShopTextField(
                            value = uiState.notes,
                            onValueChange = viewModel::onNotesChange,
                            label = "Catatan untuk klinik (opsional)",
                            placeholder = "Contoh: tolong siapkan sebelum jam 15.00",
                            keyboardType = KeyboardType.Text,
                            singleLine = false
                        )
                    }
                }
            }

            if (uiState.error != null) {
                item { PetShopInlineError(uiState.error.orEmpty()) }
            }

            item {
                ShopSectionTitle("Ringkasan pembayaran")
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

/** Baris produk di keranjang: foto, nama, varian, harga, pengatur jumlah. */
@Composable
private fun CartLineRow(
    line: PetShopCartLine,
    onDecrease: () -> Unit,
    onIncrease: () -> Unit,
    onRemove: () -> Unit
) {
    Row(modifier = Modifier.padding(start = 12.dp, end = 12.dp, top = 12.dp, bottom = 12.dp)) {
        PetShopImage(
            url = line.imageUrl,
            contentDescription = line.productName,
            modifier = Modifier
                .size(60.dp)
                .clip(RoundedCornerShape(ShopTheme.innerRadius))
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
                        overflow = TextOverflow.Ellipsis,
                        lineHeight = 17.sp
                    )
                    Spacer(Modifier.height(2.dp))
                    Text(
                        text = line.variantName,
                        fontSize = 11.sp,
                        color = TextSecondary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                }
                Box(
                    modifier = Modifier
                        .size(26.dp)
                        .clip(CircleShape)
                        .background(SurfaceMuted)
                        .clickable(onClick = onRemove),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Filled.Close,
                        contentDescription = "Hapus ${line.productName}",
                        tint = TextSecondary,
                        modifier = Modifier.size(14.dp)
                    )
                }
            }

            Spacer(Modifier.height(10.dp))

            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = formatRupiah(line.lineTotal.toDouble()),
                    fontSize = 13.5.sp,
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
                Spacer(Modifier.height(2.dp))
                Text(
                    "Maksimal stok tersedia",
                    fontSize = 10.sp,
                    color = TextTertiary
                )
            }
        }
    }
}

@Composable
private fun ClinicPickupCard(clinicName: String) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(ShopTheme.cardRadius),
        color = InfoSoft
    ) {
        Row(
            modifier = Modifier.padding(14.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(38.dp)
                    .clip(RoundedCornerShape(11.dp))
                    .background(Outline),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Filled.LocalHospital,
                    contentDescription = null,
                    tint = Info,
                    modifier = Modifier.size(19.dp)
                )
            }
            Spacer(Modifier.width(11.dp))
            Column {
                Text(
                    text = "Diambil di $clinicName",
                    fontSize = 12.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground
                )
                Text(
                    text = "Tunjukkan nomor pesanan saat mengambil produk.",
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
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(ShopTheme.cardRadius),
        color = if (selected) PrimaryLight else Surface,
        shadowElevation = if (selected) 0.dp else 2.dp,
        border = BorderStroke(1.dp, if (selected) Primary else Color.Transparent)
    ) {
        Row(
            modifier = Modifier.padding(start = 14.dp, end = 8.dp, top = 14.dp, bottom = 14.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(38.dp)
                    .clip(RoundedCornerShape(11.dp))
                    .background(if (enabled) Surface else SurfaceMuted),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = if (enabled) Primary else TextTertiary,
                    modifier = Modifier.size(19.dp)
                )
            }
            Spacer(Modifier.width(12.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = title,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = if (enabled) OnBackground else TextTertiary
                )
                Text(
                    text = description,
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
    ShopCard {
        Column(modifier = Modifier.padding(16.dp)) {
            SummaryRow("Subtotal", formatRupiah(subtotal.toDouble()))
            Spacer(Modifier.height(8.dp))
            SummaryRow("Biaya layanan", "Tidak ada")
            Spacer(Modifier.height(12.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(Outline))
            Spacer(Modifier.height(12.dp))
            Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "Total",
                    fontSize = 13.5.sp,
                    fontWeight = FontWeight.Bold,
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
private fun SummaryRow(label: String, value: String) {
    Row(modifier = Modifier.fillMaxWidth()) {
        Text(label, fontSize = 12.sp, color = TextSecondary, modifier = Modifier.weight(1f))
        Text(value, fontSize = 12.sp, fontWeight = FontWeight.Medium, color = OnBackground)
    }
}

@Composable
private fun ShopTextField(
    value: String,
    onValueChange: (String) -> Unit,
    label: String,
    placeholder: String,
    keyboardType: KeyboardType,
    singleLine: Boolean = true
) {
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        modifier = Modifier.fillMaxWidth(),
        label = { Text(label, fontSize = 12.sp) },
        placeholder = { Text(placeholder, fontSize = 12.5.sp, color = TextTertiary) },
        singleLine = singleLine,
        minLines = if (singleLine) 1 else 2,
        maxLines = if (singleLine) 1 else 4,
        keyboardOptions = KeyboardOptions(keyboardType = keyboardType),
        shape = RoundedCornerShape(PetHealRadius.md),
        colors = OutlinedTextFieldDefaults.colors(
            focusedContainerColor = Surface,
            unfocusedContainerColor = Surface,
            focusedBorderColor = Primary,
            unfocusedBorderColor = Outline,
            focusedLabelColor = Primary,
            unfocusedLabelColor = TextSecondary
        )
    )
}

@Composable
private fun CheckoutBar(total: Long, isSubmitting: Boolean, onSubmit: () -> Unit) {
    Surface(
        color = Surface,
        shadowElevation = 12.dp,
        shape = RoundedCornerShape(topStart = ShopTheme.cardRadius, topEnd = ShopTheme.cardRadius)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .imePadding()
                .padding(horizontal = 20.dp, vertical = 12.dp),
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
                contentPadding = PaddingValues(horizontal = 20.dp)
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
