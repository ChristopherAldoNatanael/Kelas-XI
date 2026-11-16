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
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.asPaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBars
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.LocalHospital
import androidx.compose.material.icons.filled.Pets
import androidx.compose.material.icons.filled.ShoppingCart
import androidx.compose.material.icons.filled.Storefront
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import com.christopheraldoo.petheal.data.model.PetShopClinicAvailability
import com.christopheraldoo.petheal.ui.components.ErrorState
import com.christopheraldoo.petheal.ui.location.rememberLocationPermission
import com.christopheraldoo.petheal.ui.theme.AccentViolet
import com.christopheraldoo.petheal.ui.theme.Background
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
import com.christopheraldoo.petheal.ui.theme.WarningSoft
import com.christopheraldoo.petheal.ui.theme.WarningText
import com.christopheraldoo.petheal.util.LocationHelper
import com.christopheraldoo.petheal.util.LocationResult
import com.christopheraldoo.petheal.util.formatRupiah
import kotlinx.coroutines.launch

@Composable
fun PetShopProductScreen(
    onNavigateBack: () -> Unit,
    onOpenCart: () -> Unit,
    onOpenOrders: () -> Unit,
    viewModel: PetShopProductViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val product = uiState.product
    val context = LocalContext.current

    // Izin lokasi dikelola satu pintu (ui.location): dialog hanya muncul bila
    // memang belum diizinkan, dan izin yang sudah ada tidak ditanyakan lagi.
    // Menolak izin tidak memblokir Pet Shop — klinik tetap bisa dipilih manual.
    val locationPermission = rememberLocationPermission()

    // Bila izin sudah diberikan di layar lain (mis. Cari Klinik), jarak langsung
    // dihitung tanpa menampilkan ajakan izin lagi. `locationRequest` hanya
    // dinaikkan saat pengguna meminta ulang, dan TIDAK di-reset di dalam effect
    // (mereset kunci LaunchedEffect akan membatalkan pembacaan lokasi).
    var locationRequest by remember {
        mutableIntStateOf(if (locationPermission.isGranted) 1 else 0)
    }

    LaunchedEffect(locationRequest, locationPermission.isGranted, uiState.product != null) {
        if (locationRequest == 0 || !locationPermission.isGranted) return@LaunchedEffect
        // Tunggu produk selesai dimuat dulu supaya reload berkoordinat tidak
        // bertabrakan dengan load awal tanpa koordinat.
        if (uiState.product == null) return@LaunchedEffect
        when (val result = LocationHelper.currentLocation(context)) {
            is LocationResult.Success -> viewModel.onLocationAvailable(result.latitude, result.longitude)
            else -> viewModel.onLocationUnavailable()
        }
    }

    LaunchedEffect(uiState.notice) {
        uiState.notice?.let { viewModel.consumeNotice() }
    }

    Scaffold(
        containerColor = Background,
        topBar = {
            PetShopHeader(
                title = "Detail Produk",
                onBack = onNavigateBack,
                cartCount = uiState.cartCount,
                onOpenCart = onOpenCart,
                onOpenOrders = onOpenOrders
            )
        },
        bottomBar = {
            if (product != null && product.purchasableVariants.isNotEmpty()) {
                PurchaseBar(
                    price = (uiState.selectedVariant?.effectivePrice ?: 0L),
                    quantity = uiState.quantity,
                    canAdd = uiState.canAddToCart,
                    onDecrease = viewModel::decrementQuantity,
                    onIncrease = viewModel::incrementQuantity,
                    onAdd = viewModel::addToCart
                )
            }
        }
    ) { innerPadding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .background(Background)
        ) {
            when {
                uiState.isLoading -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = Primary)
                }
                uiState.error != null || product == null -> ErrorState(
                    title = "Produk tidak tersedia",
                    message = uiState.error ?: "Produk ini sudah tidak dijual.",
                    primaryActionText = "Coba lagi",
                    onPrimaryActionClick = { viewModel.load() }
                )
                else -> Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .verticalScroll(rememberScrollState())
                ) {
                    ProductImage(product.imageUrl, product.name)

                    Column(modifier = Modifier.padding(20.dp)) {
                        if (!product.brand.isNullOrBlank()) {
                            Text(
                                text = product.brand.orEmpty(),
                                fontSize = 11.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = TextTertiary,
                                letterSpacing = 0.6.sp
                            )
                            Spacer(Modifier.height(4.dp))
                        }

                        Text(
                            text = product.name,
                            fontSize = 22.sp,
                            fontWeight = FontWeight.Bold,
                            color = OnBackground,
                            lineHeight = 28.sp
                        )

                        Spacer(Modifier.height(8.dp))

                        Row(verticalAlignment = Alignment.CenterVertically) {
                            if (product.category?.name != null) {
                                PetShopPill(product.category.name, InfoSoft, Primary)
                                Spacer(Modifier.width(8.dp))
                            }
                            Text(
                                text = if (product.soldCount > 0) "${product.soldCount} terjual" else "Produk baru",
                                fontSize = 11.sp,
                                color = TextSecondary
                            )
                        }

                        Spacer(Modifier.height(16.dp))

                        val selected = uiState.selectedVariant
                        Row(verticalAlignment = Alignment.Bottom) {
                            Text(
                                text = formatRupiah((selected?.effectivePrice ?: product.priceMin ?: 0L).toDouble()),
                                fontSize = 24.sp,
                                fontWeight = FontWeight.Bold,
                                color = Primary
                            )
                            if (selected?.discountPrice != null) {
                                Spacer(Modifier.width(8.dp))
                                Text(
                                    text = formatRupiah(selected.price.toDouble()),
                                    fontSize = 13.sp,
                                    color = TextTertiary,
                                    textDecoration = TextDecoration.LineThrough,
                                    modifier = Modifier.padding(bottom = 3.dp)
                                )
                            }
                        }

                        Spacer(Modifier.height(20.dp))

                        if (product.variants.isNotEmpty()) {
                            Text(
                                "Pilih varian",
                                fontSize = 14.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = OnBackground
                            )
                            Spacer(Modifier.height(10.dp))
                            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                product.variants.forEach { variant ->
                                    VariantRow(
                                        name = variant.name,
                                        price = variant.effectivePrice,
                                        originalPrice = variant.discountPrice?.let { variant.price },
                                        stock = variant.availableStock,
                                        selected = variant.id == uiState.selectedVariantId,
                                        enabled = variant.isAvailable && variant.availableStock > 0,
                                        onClick = { viewModel.selectVariant(variant.id) }
                                    )
                                }
                            }
                            Spacer(Modifier.height(20.dp))
                        }

                        PickupClinicSection(
                            clinics = uiState.pickupClinics,
                            selectedClinicId = uiState.selectedClinicId,
                            quantity = uiState.quantity,
                            hasLocation = uiState.hasLocation,
                            locationUnavailable = uiState.locationUnavailable,
                            permissionGranted = locationPermission.isGranted,
                            canRequestPermission = locationPermission.canRequest,
                            isSwitching = uiState.isSwitchingClinic,
                            onRequestLocation = {
                                locationRequest++
                                if (!locationPermission.isGranted) locationPermission.request()
                            },
                            onOpenSettings = locationPermission::openSettings,
                            onSelect = viewModel::selectClinic
                        )
                        Spacer(Modifier.height(20.dp))

                        SellerCard(product)
                        Spacer(Modifier.height(12.dp))

                        if (!product.description.isNullOrBlank()) {
                            Text(
                                "Tentang produk",
                                fontSize = 14.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = OnBackground
                            )
                            Spacer(Modifier.height(6.dp))
                            Text(
                                text = product.description,
                                fontSize = 13.sp,
                                lineHeight = 20.sp,
                                color = TextSecondary
                            )
                            Spacer(Modifier.height(16.dp))
                        }

                        Surface(
                            color = SurfaceMuted,
                            shape = RoundedCornerShape(PetHealRadius.md)
                        ) {
                            Row(
                                modifier = Modifier.padding(14.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(
                                    Icons.Filled.ShoppingCart,
                                    contentDescription = null,
                                    tint = TextSecondary,
                                    modifier = Modifier.size(18.dp)
                                )
                                Spacer(Modifier.width(10.dp))
                                Text(
                                    "Stok diambil dari klinik penjual. Pesanan diambil langsung di klinik, bukan dikirim.",
                                    fontSize = 11.sp,
                                    lineHeight = 17.sp,
                                    color = TextSecondary
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun ProductImage(url: String?, name: String) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .aspectRatio(1.15f)
            .background(SurfaceMuted)
    ) {
        if (!url.isNullOrBlank()) {
            AsyncImage(
                model = url,
                contentDescription = name,
                contentScale = ContentScale.Crop,
                modifier = Modifier.fillMaxSize()
            )
        } else {
            Icon(
                Icons.Filled.Pets,
                contentDescription = null,
                tint = TextTertiary,
                modifier = Modifier.align(Alignment.Center).size(72.dp)
            )
        }
    }
}

@Composable
private fun VariantRow(
    name: String,
    price: Long,
    originalPrice: Long?,
    stock: Int,
    selected: Boolean,
    enabled: Boolean,
    onClick: () -> Unit
) {
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(PetHealRadius.md))
            .clickable(enabled = enabled, onClick = onClick),
        shape = RoundedCornerShape(PetHealRadius.md),
        color = when {
            !enabled -> SurfaceMuted
            selected -> PrimaryLight
            else -> Surface
        },
        border = BorderStroke(
            1.dp,
            when {
                selected -> Primary
                else -> Outline
            }
        )
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = name,
                    fontSize = 13.sp,
                    fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Normal,
                    color = if (enabled) OnBackground else TextTertiary
                )
                Text(
                    text = when {
                        !enabled -> "Stok habis"
                        stock <= 5 -> "Tersisa $stock"
                        else -> "Stok $stock"
                    },
                    fontSize = 11.sp,
                    color = if (!enabled || stock <= 5) WarningText else TextSecondary
                )
            }
            Column(horizontalAlignment = Alignment.End) {
                Text(
                    text = formatRupiah(price.toDouble()),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = if (enabled) Primary else TextTertiary
                )
                if (originalPrice != null) {
                    Text(
                        text = formatRupiah(originalPrice.toDouble()),
                        fontSize = 10.sp,
                        color = TextTertiary,
                        textDecoration = TextDecoration.LineThrough
                    )
                }
            }
        }
    }
}

@Composable
private fun SellerCard(product: com.christopheraldoo.petheal.data.model.PetShopProduct) {
    val clinic = product.clinic ?: return

    Card(
        colors = CardDefaults.cardColors(containerColor = Surface),
        shape = RoundedCornerShape(PetHealRadius.lg),
        border = BorderStroke(1.dp, Outline),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
            Box(
                modifier = Modifier
                    .size(42.dp)
                    .clip(RoundedCornerShape(PetHealRadius.md))
                    .background(PrimaryLight),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    Icons.Filled.Storefront,
                    contentDescription = null,
                    tint = Primary,
                    modifier = Modifier.size(21.dp)
                )
            }
            Spacer(Modifier.width(12.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = clinic.name.orEmpty(),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground
                )
                if (!clinic.address.isNullOrBlank()) {
                    Text(
                        text = clinic.address,
                        fontSize = 11.sp,
                        lineHeight = 16.sp,
                        color = TextSecondary,
                        maxLines = 2
                    )
                }
            }
            if (clinic.onlinePaymentAvailable) {
                PetShopPill("Online", PrimaryLight, Primary)
            }
        }
    }
}

@Composable
private fun PickupClinicSection(
    clinics: List<PetShopClinicAvailability>,
    selectedClinicId: Int?,
    quantity: Int,
    hasLocation: Boolean,
    locationUnavailable: Boolean,
    permissionGranted: Boolean,
    canRequestPermission: Boolean,
    isSwitching: Boolean,
    onRequestLocation: () -> Unit,
    onOpenSettings: () -> Unit,
    onSelect: (PetShopClinicAvailability) -> Unit
) {
    if (clinics.isEmpty()) return

    Text(
        "Ambil di Klinik",
        fontSize = 14.sp,
        fontWeight = FontWeight.SemiBold,
        color = OnBackground
    )
    Spacer(Modifier.height(4.dp))
    Text(
        "Pesanan diambil langsung di klinik. Harga dan stok mengikuti klinik yang dipilih.",
        fontSize = 11.sp,
        lineHeight = 16.sp,
        color = TextSecondary
    )
    Spacer(Modifier.height(10.dp))

    if (!hasLocation) {
        Surface(
            color = SurfaceMuted,
            shape = RoundedCornerShape(PetHealRadius.md),
            modifier = Modifier.fillMaxWidth()
        ) {
            Column(modifier = Modifier.padding(12.dp)) {
                // Izin ditolak permanen: tidak ada dialog lagi, jadi arahkan
                // ke pengaturan alih-alih mengaku "mengaktifkan lokasi".
                val permissionBlocked = !permissionGranted && !canRequestPermission
                Text(
                    when {
                        permissionBlocked -> "Lokasi tidak diaktifkan"
                        permissionGranted && locationUnavailable -> "Lokasi belum ditemukan"
                        permissionGranted -> "Mencari lokasi Anda…"
                        else -> "Aktifkan lokasi untuk melihat jarak klinik"
                    },
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground
                )
                Text(
                    when {
                        permissionBlocked ->
                            "Anda tetap bisa memilih klinik di bawah secara manual. Jarak tidak ditampilkan."
                        permissionGranted && locationUnavailable ->
                            "Pastikan GPS aktif, lalu coba lagi. Klinik tetap bisa dipilih manual."
                        permissionGranted ->
                            "Jarak klinik dihitung dari lokasi Anda saat ini."
                        else ->
                            "Lokasi hanya dipakai sekali untuk menghitung jarak — tidak dilacak di latar belakang."
                    },
                    fontSize = 10.sp,
                    lineHeight = 15.sp,
                    color = TextSecondary
                )
                // Izin yang sudah diberikan tidak pernah diminta ulang; yang
                // ditawarkan hanya pengambilan lokasi lagi atau pengaturan.
                val locationAction: (() -> Unit)? = when {
                    permissionGranted && locationUnavailable -> onRequestLocation
                    permissionGranted -> null
                    canRequestPermission -> onRequestLocation
                    else -> onOpenSettings
                }
                if (locationAction != null) {
                    Spacer(Modifier.height(8.dp))
                    Button(
                        onClick = locationAction,
                        colors = ButtonDefaults.buttonColors(containerColor = Primary),
                        shape = RoundedCornerShape(PetHealRadius.md),
                        contentPadding = PaddingValues(horizontal = 14.dp, vertical = 6.dp)
                    ) {
                        Text(
                            when {
                                permissionGranted -> "Coba ambil lokasi lagi"
                                canRequestPermission -> "Izinkan lokasi"
                                else -> "Buka pengaturan aplikasi"
                            },
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                    }
                }
            }
        }
        Spacer(Modifier.height(10.dp))
    }

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        clinics.forEach { clinic ->
            val availability = clinic.product
            val availableStock = availability?.variants
                ?.filter { it.isAvailable }
                ?.maxOfOrNull { it.availableStock }
                ?: 0
            val canServe = availability == null || availableStock >= quantity
            val selected = clinic.id == selectedClinicId
            val price = availability?.variants
                ?.filter { it.isAvailable && it.availableStock > 0 }
                ?.minOfOrNull { it.effectivePrice }

            Surface(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(PetHealRadius.md))
                    .clickable(enabled = !isSwitching) { onSelect(clinic) },
                shape = RoundedCornerShape(PetHealRadius.md),
                color = when {
                    !canServe -> SurfaceMuted
                    selected -> PrimaryLight
                    else -> Surface
                },
                border = BorderStroke(1.dp, if (selected) Primary else Outline)
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 14.dp, vertical = 12.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        Icons.Filled.LocalHospital,
                        contentDescription = null,
                        tint = if (canServe) Primary else TextTertiary,
                        modifier = Modifier.size(20.dp)
                    )
                    Spacer(Modifier.width(10.dp))
                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            clinic.name,
                            fontSize = 13.sp,
                            fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Normal,
                            color = if (canServe) OnBackground else TextTertiary
                        )
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(
                                clinic.distanceLabel ?: "Jarak belum tersedia",
                                fontSize = 10.sp,
                                color = TextSecondary
                            )
                            Text(" · ", fontSize = 10.sp, color = TextTertiary)
                            Text(
                                when {
                                    availability == null -> "Stok mengikuti klinik"
                                    availableStock <= 0 -> "Stok habis"
                                    !canServe -> "Stok tersisa $availableStock"
                                    else -> "Stok tersedia"
                                },
                                fontSize = 10.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = if (canServe) Primary else WarningText
                            )
                        }
                        if (!clinic.address.isNullOrBlank()) {
                            Text(
                                clinic.address,
                                fontSize = 10.sp,
                                lineHeight = 14.sp,
                                color = TextSecondary,
                                maxLines = 1
                            )
                        }
                    }
                    if (price != null) {
                        Text(
                            formatRupiah(price.toDouble()),
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = if (canServe) Primary else TextTertiary
                        )
                    }
                    if (selected) {
                        Spacer(Modifier.width(6.dp))
                        Icon(
                            Icons.Filled.CheckCircle,
                            contentDescription = "Klinik dipilih",
                            tint = Primary,
                            modifier = Modifier.size(16.dp)
                        )
                    }
                }
            }
        }
    }

    if (isSwitching) {
        Spacer(Modifier.height(8.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            CircularProgressIndicator(color = Primary, strokeWidth = 2.dp, modifier = Modifier.size(14.dp))
            Spacer(Modifier.width(8.dp))
            Text("Memuat harga & stok klinik…", fontSize = 10.sp, color = TextSecondary)
        }
    }
}

@Composable
fun PetShopPill(text: String, background: Color, content: Color) {
    Surface(shape = RoundedCornerShape(PetHealRadius.pill), color = background) {
        Text(
            text = text,
            modifier = Modifier.padding(horizontal = 9.dp, vertical = 4.dp),
            fontSize = 10.sp,
            fontWeight = FontWeight.SemiBold,
            color = content
        )
    }
}

@Composable
private fun PurchaseBar(
    price: Long,
    quantity: Int,
    canAdd: Boolean,
    onDecrease: () -> Unit,
    onIncrease: () -> Unit,
    onAdd: () -> Unit
) {
    val navPadding = WindowInsets.navigationBars.asPaddingValues().calculateBottomPadding()
    Surface(color = Surface, shadowElevation = 12.dp) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp, vertical = 12.dp)
                .padding(bottom = navPadding),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text("Subtotal", fontSize = 10.sp, color = TextSecondary)
                Text(
                    formatRupiah((price * quantity).toDouble()),
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = OnBackground
                )
            }
            Spacer(Modifier.width(14.dp))
            QuantityStepper(
                quantity = quantity,
                onDecrease = onDecrease,
                onIncrease = onIncrease,
                canIncrease = canAdd
            )
            Spacer(Modifier.width(12.dp))
            Button(
                onClick = onAdd,
                enabled = canAdd,
                modifier = Modifier.weight(1f).height(46.dp),
                shape = RoundedCornerShape(PetHealRadius.md),
                colors = ButtonDefaults.buttonColors(
                    containerColor = Primary,
                    disabledContainerColor = SurfaceMuted,
                    disabledContentColor = TextTertiary
                )
            ) {
                Icon(Icons.Filled.ShoppingCart, contentDescription = null, modifier = Modifier.size(17.dp))
                Spacer(Modifier.width(8.dp))
                Text("Tambah", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
            }
        }
    }
}
