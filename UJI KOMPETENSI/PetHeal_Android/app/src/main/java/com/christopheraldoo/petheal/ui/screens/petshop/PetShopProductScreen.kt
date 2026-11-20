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
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.LocalHospital
import androidx.compose.material.icons.filled.ShoppingBag
import androidx.compose.material.icons.filled.ShoppingCart
import androidx.compose.material.icons.filled.Storefront
import androidx.compose.material.icons.outlined.ReceiptLong
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
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
import androidx.compose.foundation.border
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.model.PetShopClinicAvailability
import com.christopheraldoo.petheal.ui.components.ErrorState
import com.christopheraldoo.petheal.ui.components.shimmerEffect
import com.christopheraldoo.petheal.ui.location.rememberLocationPermission
import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.OutlineStrong
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

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Background)
    ) {
        when {
            uiState.isLoading -> ProductSkeleton(onNavigateBack, onOpenCart, onOpenOrders, uiState.cartCount)
            uiState.error != null || product == null -> ErrorState(
                title = "Produk tidak tersedia",
                message = uiState.error ?: "Produk ini sudah tidak dijual.",
                primaryActionText = "Coba lagi",
                onPrimaryActionClick = { viewModel.load() }
            )
            else -> Column(modifier = Modifier.fillMaxSize()) {
                Column(
                    modifier = Modifier
                        .weight(1f)
                        .verticalScroll(rememberScrollState())
                ) {
                    PhotoHero(
                        product = product,
                        cartCount = uiState.cartCount,
                        onNavigateBack = onNavigateBack,
                        onOpenCart = onOpenCart,
                        onOpenOrders = onOpenOrders
                    )

                    // Lembaran konten menaiki foto: pencapaian visual khas toko,
                    // sekaligus memakai pola yang sama dengan detail hewan.
                    Surface(
                        modifier = Modifier
                            .fillMaxWidth()
                            .offset(y = (-20).dp),
                        shape = RoundedCornerShape(topStart = ShopTheme.headerBottom, topEnd = ShopTheme.headerBottom),
                        color = Background
                    ) {
                        Column(modifier = Modifier.padding(20.dp)) {
                            HeadBlock(product, uiState.selectedVariant)

                            if (product.variants.isNotEmpty()) {
                                Spacer(Modifier.height(20.dp))
                                ShopSectionTitle("Varian")
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
                            }

                            if (uiState.pickupClinics.isNotEmpty()) {
                                Spacer(Modifier.height(20.dp))
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
                            }

                            if (product.clinic != null) {
                                Spacer(Modifier.height(20.dp))
                                SellerCard(product.clinic.name.orEmpty(), product.clinic.address, product.clinic.onlinePaymentAvailable)
                            }

                            if (!product.description.isNullOrBlank()) {
                                Spacer(Modifier.height(20.dp))
                                ShopSectionTitle("Tentang produk")
                                Spacer(Modifier.height(8.dp))
                                Text(
                                    text = product.description.orEmpty(),
                                    fontSize = 13.sp,
                                    lineHeight = 20.sp,
                                    color = TextSecondary
                                )
                            }

                            Spacer(Modifier.height(16.dp))
                            PetShopInfoBanner(
                                "Stok diambil dari klinik penjual. Pesanan diambil langsung di klinik, bukan dikirim."
                            )
                            Spacer(Modifier.height(8.dp))
                        }
                    }
                }

                if (product.purchasableVariants.isNotEmpty()) {
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
        }
    }
}

/** Foto produk full-bleed dengan tombol melayang di atasnya. */
@Composable
private fun PhotoHero(
    product: com.christopheraldoo.petheal.data.model.PetShopProduct,
    cartCount: Int,
    onNavigateBack: () -> Unit,
    onOpenCart: () -> Unit,
    onOpenOrders: () -> Unit
) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .aspectRatio(1.05f)
    ) {
        PetShopPhoto(
            url = product.imageUrl,
            contentDescription = product.name,
            categorySlug = product.category?.slug,
            categoryName = product.category?.name,
            iconSize = 72.dp,
            modifier = Modifier.fillMaxSize()
        )

        // Scrim tipis di belakang tombol: hanya untuk keterbacaan di foto
        // terang, bukan hiasan. Karena itu paling gelap di bagian atas.
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(96.dp)
                .background(
                    Brush.verticalGradient(
                        0f to Color.Black.copy(alpha = 0.20f),
                        1f to Color.Transparent
                    )
                )
                .align(Alignment.TopCenter)
        )

        Row(
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
                .padding(horizontal = 12.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Di atas foto, tombol memakai alas putih solid: kontras tetap
            // terjaga apa pun warna gambarnya.
            ShopRoundButton(
                icon = Icons.Filled.ArrowBack,
                contentDescription = "Kembali",
                onClick = onNavigateBack,
                tint = OnBackground,
                container = Color.White.copy(alpha = 0.92f)
            )
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                ShopRoundButton(
                    icon = Icons.Outlined.ReceiptLong,
                    contentDescription = "Pesanan saya",
                    onClick = onOpenOrders,
                    tint = OnBackground,
                    container = Color.White.copy(alpha = 0.92f)
                )
                ShopRoundButton(
                    icon = Icons.Filled.ShoppingBag,
                    contentDescription = "Keranjang",
                    onClick = onOpenCart,
                    badgeCount = cartCount,
                    tint = OnBackground,
                    container = Color.White.copy(alpha = 0.92f)
                )
            }
        }
    }
}

@Composable
private fun HeadBlock(
    product: com.christopheraldoo.petheal.data.model.PetShopProduct,
    selectedVariant: com.christopheraldoo.petheal.data.model.PetShopVariant?
) {
    if (!product.brand.isNullOrBlank()) {
        Text(
            text = product.brand.orEmpty(),
            fontSize = 10.5.sp,
            fontWeight = FontWeight.SemiBold,
            color = TextTertiary,
            letterSpacing = 0.7.sp
        )
        Spacer(Modifier.height(4.dp))
    }

    Text(
        text = product.name,
        fontSize = 21.sp,
        fontWeight = FontWeight.Bold,
        color = OnBackground,
        lineHeight = 27.sp
    )

    Spacer(Modifier.height(8.dp))

    Row(verticalAlignment = Alignment.CenterVertically) {
        if (product.category?.name != null) {
            PetShopPill(product.category.name, PrimaryLight, Primary)
            Spacer(Modifier.width(8.dp))
        }
        Text(
            text = if (product.soldCount > 0) "${product.soldCount} terjual" else "Produk baru",
            fontSize = 11.sp,
            color = TextSecondary
        )
    }

    Spacer(Modifier.height(16.dp))

    Row(verticalAlignment = Alignment.Bottom) {
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = formatRupiah((selectedVariant?.effectivePrice ?: product.priceMin ?: 0L).toDouble()),
                fontSize = 26.sp,
                fontWeight = FontWeight.Bold,
                color = Primary,
                lineHeight = 30.sp
            )
            if (selectedVariant?.discountPrice != null) {
                Text(
                    text = formatRupiah(selectedVariant.price.toDouble()),
                    fontSize = 13.sp,
                    color = TextTertiary,
                    textDecoration = TextDecoration.LineThrough
                )
            }
        }
        val stock = selectedVariant?.availableStock ?: product.totalAvailableStock
        if (stock <= 5) {
            Surface(
                shape = RoundedCornerShape(PetHealRadius.pill),
                color = if (stock <= 0) SurfaceMuted else WarningSoft
            ) {
                Text(
                    text = if (stock <= 0) "Stok habis" else "Sisa $stock",
                    modifier = Modifier.padding(horizontal = 11.dp, vertical = 6.dp),
                    fontSize = 10.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = if (stock <= 0) TextTertiary else WarningText
                )
            }
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
            .clip(RoundedCornerShape(ShopTheme.innerRadius))
            .clickable(enabled = enabled, onClick = onClick),
        shape = RoundedCornerShape(ShopTheme.innerRadius),
        color = when {
            !enabled -> SurfaceMuted
            selected -> PrimaryLight
            else -> Surface
        },
        shadowElevation = if (selected) 1.dp else 0.dp,
        border = BorderStroke(
            1.dp,
            when {
                selected -> Primary
                else -> Outline
            }
        )
    ) {
        Row(
            modifier = Modifier.padding(start = 14.dp, end = 12.dp, top = 13.dp, bottom = 13.dp),
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
                    fontWeight = FontWeight.Bold,
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
            Spacer(Modifier.width(8.dp))
            if (selected) {
                Icon(
                    Icons.Filled.CheckCircle,
                    contentDescription = "Varian dipilih",
                    tint = Primary,
                    modifier = Modifier.size(20.dp)
                )
            } else {
                Box(
                    modifier = Modifier
                        .size(18.dp)
                        .clip(CircleShape)
                        .background(Surface)
                        .border(1.2.dp, OutlineStrong, CircleShape)
                )
            }
        }
    }
}

@Composable
private fun SellerCard(name: String, address: String?, onlinePaymentAvailable: Boolean) {
    ShopCard {
        Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
            Box(
                modifier = Modifier
                    .size(44.dp)
                    .clip(RoundedCornerShape(ShopTheme.innerRadius))
                    .background(PrimaryLight),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Filled.Storefront,
                    contentDescription = null,
                    tint = Primary,
                    modifier = Modifier.size(22.dp)
                )
            }
            Spacer(Modifier.width(12.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = name,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground
                )
                if (!address.isNullOrBlank()) {
                    Text(
                        text = address,
                        fontSize = 11.sp,
                        lineHeight = 16.sp,
                        color = TextSecondary,
                        maxLines = 2
                    )
                }
            }
            if (onlinePaymentAvailable) {
                PetShopPill("Pembayaran online", PrimaryLight, Primary)
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

    ShopSectionTitle("Ambil di klinik")
    Spacer(Modifier.height(4.dp))
    Text(
        "Pesanan diambil langsung di klinik. Harga dan stok mengikuti klinik yang dipilih.",
        fontSize = 11.sp,
        lineHeight = 16.sp,
        color = TextSecondary
    )
    Spacer(Modifier.height(12.dp))

    if (!hasLocation) {
        Surface(
            color = SurfaceMuted,
            shape = RoundedCornerShape(ShopTheme.innerRadius),
            modifier = Modifier.fillMaxWidth()
        ) {
            Column(modifier = Modifier.padding(13.dp)) {
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
                    fontSize = 10.5.sp,
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
                    Spacer(Modifier.height(10.dp))
                    Button(
                        onClick = locationAction,
                        colors = ButtonDefaults.buttonColors(containerColor = Primary),
                        shape = RoundedCornerShape(PetHealRadius.md),
                        contentPadding = PaddingValues(horizontal = 14.dp, vertical = 7.dp)
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
        Spacer(Modifier.height(12.dp))
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
                    .clip(RoundedCornerShape(ShopTheme.innerRadius))
                    .clickable(enabled = !isSwitching) { onSelect(clinic) },
                shape = RoundedCornerShape(ShopTheme.innerRadius),
                color = when {
                    !canServe -> SurfaceMuted
                    selected -> PrimaryLight
                    else -> Surface
                },
                shadowElevation = if (selected) 1.dp else 0.dp,
                border = BorderStroke(1.dp, if (selected) Primary else Outline)
            ) {
                Row(
                    modifier = Modifier.padding(start = 14.dp, end = 12.dp, top = 12.dp, bottom = 12.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Box(
                        modifier = Modifier
                            .size(36.dp)
                            .clip(RoundedCornerShape(10.dp))
                            .background(if (canServe) PrimaryLight else SurfaceMuted),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            Icons.Filled.LocalHospital,
                            contentDescription = null,
                            tint = if (canServe) Primary else TextTertiary,
                            modifier = Modifier.size(18.dp)
                        )
                    }
                    Spacer(Modifier.width(10.dp))
                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            clinic.name,
                            fontSize = 13.sp,
                            fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Normal,
                            color = if (canServe) OnBackground else TextTertiary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
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
                    }
                    if (price != null) {
                        Text(
                            formatRupiah(price.toDouble()),
                            fontSize = 12.5.sp,
                            fontWeight = FontWeight.Bold,
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
private fun PurchaseBar(
    price: Long,
    quantity: Int,
    canAdd: Boolean,
    onDecrease: () -> Unit,
    onIncrease: () -> Unit,
    onAdd: () -> Unit
) {
    Surface(
        color = Surface,
        shadowElevation = 12.dp,
        shape = RoundedCornerShape(topStart = ShopTheme.cardRadius, topEnd = ShopTheme.cardRadius)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .padding(horizontal = 20.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text("Subtotal", fontSize = 10.sp, color = TextSecondary)
                Text(
                    formatRupiah((price * quantity).toDouble()),
                    fontSize = 14.5.sp,
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

@Composable
private fun ProductSkeleton(
    onNavigateBack: () -> Unit,
    onOpenCart: () -> Unit,
    onOpenOrders: () -> Unit,
    cartCount: Int
) {
    Box(modifier = Modifier.fillMaxSize()) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .aspectRatio(1.05f)
                    .background(SurfaceMuted)
                    .shimmerEffect(true)
            )
            Column(modifier = Modifier.padding(20.dp)) {
                Box(
                    modifier = Modifier
                        .width(90.dp)
                        .height(10.dp)
                        .clip(RoundedCornerShape(PetHealRadius.sm))
                        .background(SurfaceMuted)
                        .shimmerEffect(true)
                )
                Spacer(Modifier.height(10.dp))
                Box(
                    modifier = Modifier
                        .fillMaxWidth(0.85f)
                        .height(17.dp)
                        .clip(RoundedCornerShape(PetHealRadius.sm))
                        .background(SurfaceMuted)
                        .shimmerEffect(true)
                )
                Spacer(Modifier.height(8.dp))
                Box(
                    modifier = Modifier
                        .fillMaxWidth(0.55f)
                        .height(17.dp)
                        .clip(RoundedCornerShape(PetHealRadius.sm))
                        .background(SurfaceMuted)
                        .shimmerEffect(true)
                )
                Spacer(Modifier.height(24.dp))
                Box(
                    modifier = Modifier
                        .width(140.dp)
                        .height(22.dp)
                        .clip(RoundedCornerShape(PetHealRadius.sm))
                        .background(SurfaceMuted)
                        .shimmerEffect(true)
                )
            }
        }
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
                .padding(horizontal = 12.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            ShopRoundButton(
                icon = Icons.Filled.ArrowBack,
                contentDescription = "Kembali",
                onClick = onNavigateBack
            )
            ShopRoundButton(
                icon = Icons.Filled.ShoppingBag,
                contentDescription = "Keranjang",
                onClick = onOpenCart,
                badgeCount = cartCount
            )
        }
        Box(
            modifier = Modifier
                .align(Alignment.BottomStart)
                .padding(16.dp)
                .size(22.dp)
        ) {
            CircularProgressIndicator(
                color = Primary,
                strokeWidth = 2.dp,
                modifier = Modifier.fillMaxSize()
            )
        }
    }
}