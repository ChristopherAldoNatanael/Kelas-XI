package com.christopheraldoo.petheal.ui.screens.petshop

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.CleaningServices
import androidx.compose.material.icons.filled.ContentCut
import androidx.compose.material.icons.filled.FlutterDash
import androidx.compose.material.icons.filled.Info
import androidx.compose.material.icons.filled.Inventory2
import androidx.compose.material.icons.filled.Luggage
import androidx.compose.material.icons.filled.Medication
import androidx.compose.material.icons.filled.Pets
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material.icons.filled.SetMeal
import androidx.compose.material.icons.filled.ShoppingBag
import androidx.compose.material.icons.filled.Storefront
import androidx.compose.material.icons.filled.Toys
import androidx.compose.material.icons.filled.Water
import androidx.compose.material.icons.outlined.ReceiptLong
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.christopheraldoo.petheal.data.model.PetShopOrder
import com.christopheraldoo.petheal.data.model.PetShopProduct
import com.christopheraldoo.petheal.ui.theme.AccentViolet
import com.christopheraldoo.petheal.ui.theme.Error
import com.christopheraldoo.petheal.ui.theme.ErrorSoft
import com.christopheraldoo.petheal.ui.theme.Info
import com.christopheraldoo.petheal.ui.theme.InfoSoft
import com.christopheraldoo.petheal.ui.theme.InfoStrong
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.PetHealRadius
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.PrimaryDark
import com.christopheraldoo.petheal.ui.theme.PrimaryLight
import com.christopheraldoo.petheal.ui.theme.Secondary
import com.christopheraldoo.petheal.ui.theme.SecondaryLight
import com.christopheraldoo.petheal.ui.theme.Surface
import com.christopheraldoo.petheal.ui.theme.SurfaceMuted
import com.christopheraldoo.petheal.ui.theme.Tertiary
import com.christopheraldoo.petheal.ui.theme.TertiaryLight
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.ui.theme.WarningDeep
import com.christopheraldoo.petheal.ui.theme.WarningSoft
import com.christopheraldoo.petheal.ui.theme.WarningText
import com.christopheraldoo.petheal.util.formatRupiah

/**
 * Bahasa visual Pet Shop.
 *
 * Pet Shop tetap satu keluarga dengan layar klinis PetHeal (hijau merek,
 * kartu putih, gutter 20 dp, ritme 8 dp) tetapi sengaja dibedakan supaya
 * terasa seperti toko, bukan formulir medis:
 *
 * - Kepala layar satu warna solid (tanpa gradien & tanpa bidang dekoratif):
 *   warna merek yang rata membuat katalog terbaca seperti rak toko sungguhan,
 *   bukan banner promosi.
 * - Sudut kartu 18 dp (sedikit lebih tegas dari 20–22 dp layar klinis) dan
 *   bayangan tipis, bukan garis tepi — kartu produk jadi terlihat "berdiri".
 * - Aksen hangat [Tertiary] hanya untuk label promo, sehingga harga tetap
 *   hijau dan promo langsung terbaca.
 * - Harga selalu jadi elemen paling tebal di kartu: itu informasi yang dicari
 *   pembeli lebih dulu.
 */
object ShopTheme {
    val gutter = 20.dp
    val cardRadius = 18.dp
    val innerRadius = 14.dp
    val headerBottom = 26.dp
    val promo = Tertiary
    val promoSoft = TertiaryLight

    /** Warna kepala halaman Pet Shop: satu warna solid, tanpa gradasi. */
    val headerColor = PrimaryDark
}

/** Label + warna status pesanan, satu sumber untuk semua layar Pet Shop. */
data class PetShopStatusStyle(val label: String, val container: Color, val content: Color)

@Composable
fun orderStatusStyle(status: String): PetShopStatusStyle = when (status) {
    PetShopOrder.STATUS_PENDING_PAYMENT -> PetShopStatusStyle(
        "Menunggu pembayaran", WarningSoft, WarningText
    )
    PetShopOrder.STATUS_AWAITING_CONFIRMATION -> PetShopStatusStyle(
        "Menunggu konfirmasi", InfoSoft, Info
    )
    PetShopOrder.STATUS_ACCEPTED -> PetShopStatusStyle(
        "Diterima klinik", InfoSoft, Info
    )
    PetShopOrder.STATUS_PREPARING -> PetShopStatusStyle(
        "Sedang disiapkan", AccentViolet.copy(alpha = 0.12f), AccentViolet
    )
    PetShopOrder.STATUS_READY_FOR_PICKUP -> PetShopStatusStyle(
        "Siap diambil", SecondaryLight, Secondary
    )
    PetShopOrder.STATUS_COMPLETED -> PetShopStatusStyle(
        "Selesai", PrimaryLight, Primary
    )
    PetShopOrder.STATUS_REJECTED -> PetShopStatusStyle(
        "Ditolak", ErrorSoft, MaterialTheme.colorScheme.error
    )
    PetShopOrder.STATUS_CANCELLED -> PetShopStatusStyle(
        "Dibatalkan", SurfaceMuted, TextSecondary
    )
    PetShopOrder.STATUS_EXPIRED -> PetShopStatusStyle(
        "Kedaluwarsa", SurfaceMuted, TextTertiary
    )
    else -> PetShopStatusStyle(status.replace('_', ' '), SurfaceMuted, TextSecondary)
}

fun paymentStatusLabel(status: String): String = when (status) {
    PetShopOrder.PAYMENT_UNPAID -> "Belum dibayar"
    PetShopOrder.PAYMENT_PENDING -> "Menunggu pembayaran"
    PetShopOrder.PAYMENT_PAID -> "Lunas"
    "failed" -> "Pembayaran gagal"
    "expired" -> "Pembayaran kedaluwarsa"
    "refund_pending" -> "Refund diproses"
    "partially_refunded" -> "Refund sebagian"
    "refunded" -> "Dana dikembalikan"
    "chargeback" -> "Chargeback"
    else -> status.replace('_', ' ')
}

@Composable
fun paymentStatusStyle(status: String): PetShopStatusStyle = when (status) {
    PetShopOrder.PAYMENT_PAID -> PetShopStatusStyle("Lunas", PrimaryLight, Primary)
    PetShopOrder.PAYMENT_PENDING -> PetShopStatusStyle("Menunggu pembayaran", WarningSoft, WarningText)
    PetShopOrder.PAYMENT_UNPAID -> PetShopStatusStyle("Belum dibayar", SurfaceMuted, TextSecondary)
    "refund_pending", "partially_refunded" -> PetShopStatusStyle("Refund diproses", WarningSoft, WarningText)
    "refunded" -> PetShopStatusStyle("Dana dikembalikan", InfoSoft, Info)
    else -> PetShopStatusStyle(paymentStatusLabel(status), ErrorSoft, MaterialTheme.colorScheme.error)
}

@Composable
fun PetShopStatusBadge(style: PetShopStatusStyle, modifier: Modifier = Modifier) {
    Surface(
        modifier = modifier,
        shape = RoundedCornerShape(PetHealRadius.pill),
        color = style.container
    ) {
        Text(
            text = style.label,
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp),
            fontSize = 11.sp,
            fontWeight = FontWeight.SemiBold,
            color = style.content
        )
    }
}

/**
 * Kepala layar Pet Shop: pita hijau rata, sudut bawah membulat, tombol
 * bulat putih untuk kembali/pesanan/keranjang, dan slot [content] untuk elemen
 * khas halaman (kolom pencarian di katalog, ringkasan di detail).
 */
@Composable
fun PetShopHeader(
    title: String,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    onBack: (() -> Unit)? = null,
    cartCount: Int = 0,
    onOpenCart: (() -> Unit)? = null,
    onOpenOrders: (() -> Unit)? = null,
    content: (@Composable ColumnScope.() -> Unit)? = null
) {
    Box(
        modifier = modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(bottomStart = ShopTheme.headerBottom, bottomEnd = ShopTheme.headerBottom))
            .background(ShopTheme.headerColor)
    ) {

        Column(
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
                .padding(top = 6.dp)
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(start = 12.dp, end = 14.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                if (onBack != null) {
                    ShopRoundButton(
                        icon = Icons.Filled.ArrowBack,
                        contentDescription = "Kembali",
                        onClick = onBack
                    )
                    Spacer(Modifier.width(10.dp))
                }

                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = title,
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    if (!subtitle.isNullOrBlank()) {
                        Text(
                            text = subtitle,
                            fontSize = 11.5.sp,
                            color = Color.White.copy(alpha = 0.82f),
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                }

                if (onOpenOrders != null) {
                    ShopRoundButton(
                        icon = Icons.Outlined.ReceiptLong,
                        contentDescription = "Pesanan saya",
                        onClick = onOpenOrders
                    )
                    Spacer(Modifier.width(8.dp))
                }

                if (onOpenCart != null) {
                    ShopRoundButton(
                        icon = Icons.Filled.ShoppingBag,
                        contentDescription = "Keranjang",
                        onClick = onOpenCart,
                        badgeCount = cartCount
                    )
                }
            }

            if (content != null) {
                Spacer(Modifier.height(14.dp))
                Column(modifier = Modifier.padding(horizontal = ShopTheme.gutter), content = content)
            }

            Spacer(Modifier.height(16.dp))
        }
    }
}

/** Tombol bulat translusen khas kepala layar Pet Shop. */
@Composable
fun ShopRoundButton(
    icon: ImageVector,
    contentDescription: String?,
    onClick: () -> Unit,
    badgeCount: Int = 0,
    tint: Color = Color.White,
    container: Color = Color.White.copy(alpha = 0.18f)
) {
    Box {
        Box(
            modifier = Modifier
                .size(42.dp)
                .clip(CircleShape)
                .background(container)
                .clickable(onClick = onClick),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = contentDescription,
                tint = tint,
                modifier = Modifier.size(21.dp)
            )
        }
        if (badgeCount > 0) {
            Box(
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .size(18.dp)
                    .clip(CircleShape)
                    .background(Error),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = if (badgeCount > 99) "99+" else badgeCount.toString(),
                    fontSize = 9.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color.White
                )
            }
        }
    }
}

/** Kartu putih standar Pet Shop: sudut 18 dp, bayangan tipis, tanpa garis tepi. */
@Composable
fun ShopCard(
    modifier: Modifier = Modifier,
    content: @Composable ColumnScope.() -> Unit
) {
    Surface(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(ShopTheme.cardRadius),
        color = Surface,
        shadowElevation = 2.dp
    ) {
        Column(content = content)
    }
}

/** Judul bagian dengan aksi opsional di kanan (mis. "Lihat semua"). */
@Composable
fun ShopSectionTitle(
    text: String,
    modifier: Modifier = Modifier,
    trailing: @Composable (() -> Unit)? = null
) {
    Row(
        modifier = modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = text,
            fontSize = 14.sp,
            fontWeight = FontWeight.Bold,
            color = OnBackground,
            modifier = Modifier.weight(1f)
        )
        trailing?.invoke()
    }
}

/** Ikon & aksen kategori produk — dipakai kartu produk, katalog, dan detail. */
data class CategoryArtwork(val icon: ImageVector, val accent: Color)

fun categoryArtwork(categorySlug: String?, categoryName: String?): CategoryArtwork {
    val key = "${categorySlug.orEmpty()} ${categoryName.orEmpty()}".lowercase()
    return when {
        // Makanan diperiksa lebih dulu supaya pakan ikan/burung tetap tampil
        // sebagai pakan, bukan sebagai hewannya.
        key.contains("makanan") || key.contains("pakan") -> CategoryArtwork(Icons.Filled.SetMeal, Primary)
        key.contains("obat") || key.contains("vitamin") -> CategoryArtwork(Icons.Filled.Medication, Info)
        key.contains("mainan") -> CategoryArtwork(Icons.Filled.Toys, AccentViolet)
        key.contains("pasir") || key.contains("litter") ->
            CategoryArtwork(Icons.Filled.CleaningServices, Secondary)
        key.contains("kandang") || key.contains("tas") -> CategoryArtwork(Icons.Filled.Luggage, PrimaryDark)
        key.contains("grooming") -> CategoryArtwork(Icons.Filled.ContentCut, Secondary)
        key.contains("ikan") -> CategoryArtwork(Icons.Filled.Water, InfoStrong)
        key.contains("burung") -> CategoryArtwork(Icons.Filled.FlutterDash, WarningDeep)
        key.contains("hamster") || key.contains("kelinci") -> CategoryArtwork(Icons.Filled.Pets, AccentViolet)
        key.contains("aksesoris") || key.contains("perlengkapan") ->
            CategoryArtwork(Icons.Filled.Inventory2, PrimaryDark)
        else -> CategoryArtwork(Icons.Filled.Pets, Primary)
    }
}

/**
 * Ubin kategori berbentuk kotak ikon + label. Dipakai sebagai rel kategori di
 * katalog: lebih mudah dipindai daripada deretan chip teks, karena ikon
 * memberi bentuk yang bisa dikenali sebelum membaca labelnya.
 */
@Composable
fun ShopCategoryTile(
    name: String,
    slug: String?,
    selected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val artwork = if (name == "Semua") {
        CategoryArtwork(Icons.Filled.Inventory2, Primary)
    } else {
        categoryArtwork(slug, name)
    }

    Column(
        modifier = modifier
            .width(72.dp)
            .clip(RoundedCornerShape(ShopTheme.innerRadius))
            .clickable(onClick = onClick)
            .padding(vertical = 6.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Box(
            modifier = Modifier
                .size(52.dp)
                .clip(RoundedCornerShape(ShopTheme.innerRadius))
                .background(if (selected) Primary else artwork.accent.copy(alpha = 0.12f)),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = artwork.icon,
                contentDescription = null,
                tint = if (selected) Color.White else artwork.accent,
                modifier = Modifier.size(24.dp)
            )
        }
        Spacer(Modifier.height(6.dp))
        Text(
            text = name,
            fontSize = 10.5.sp,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
            color = if (selected) Primary else TextSecondary,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis
        )
    }
}

/**
 * Kartu produk untuk grid katalog.
 *
 * Hierarki yang dipakai: foto → nama (2 baris tetap supaya grid rata) → klinik
 * penjual → harga. Harga adalah satu-satunya teks hijau tebal di kartu sehingga
 * mata langsung menemukannya.
 */
@Composable
fun PetShopProductCard(
    product: PetShopProduct,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Surface(
        modifier = modifier
            .clip(RoundedCornerShape(ShopTheme.cardRadius))
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(ShopTheme.cardRadius),
        color = Surface,
        shadowElevation = 2.dp
    ) {
        Column {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .aspectRatio(1f)
            ) {
                PetShopPhoto(
                    url = product.imageUrl,
                    contentDescription = product.name,
                    categorySlug = product.category?.slug,
                    categoryName = product.category?.name,
                    iconSize = 38.dp,
                    modifier = Modifier.fillMaxSize()
                )

                if (product.hasDiscount) {
                    Surface(
                        modifier = Modifier.align(Alignment.TopStart).padding(8.dp),
                        shape = RoundedCornerShape(PetHealRadius.sm),
                        color = ShopTheme.promo
                    ) {
                        Text(
                            "Promo",
                            modifier = Modifier.padding(horizontal = 7.dp, vertical = 3.dp),
                            fontSize = 9.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White
                        )
                    }
                }

                val stock = product.totalAvailableStock
                if (stock <= 5) {
                    Surface(
                        modifier = Modifier.align(Alignment.BottomStart).padding(8.dp),
                        shape = RoundedCornerShape(PetHealRadius.sm),
                        color = if (stock <= 0) SurfaceMuted else WarningSoft
                    ) {
                        Text(
                            if (stock <= 0) "Stok habis" else "Sisa $stock",
                            modifier = Modifier.padding(horizontal = 6.dp, vertical = 3.dp),
                            fontSize = 9.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = if (stock <= 0) TextTertiary else WarningText
                        )
                    }
                }
            }

            Column(modifier = Modifier.padding(start = 10.dp, end = 10.dp, top = 9.dp, bottom = 10.dp)) {
                Text(
                    text = product.name,
                    fontSize = 12.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground,
                    maxLines = 2,
                    minLines = 2,
                    overflow = TextOverflow.Ellipsis,
                    lineHeight = 16.sp
                )

                Spacer(Modifier.height(5.dp))

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        Icons.Filled.Storefront,
                        contentDescription = null,
                        tint = TextTertiary,
                        modifier = Modifier.size(11.dp)
                    )
                    Spacer(Modifier.width(3.dp))
                    Text(
                        text = product.clinic?.name.orEmpty(),
                        fontSize = 10.sp,
                        color = TextTertiary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f)
                    )
                    if (product.soldCount > 0) {
                        Text(
                            text = "${product.soldCount} terjual",
                            fontSize = 9.5.sp,
                            color = TextTertiary
                        )
                    }
                }

                Spacer(Modifier.height(7.dp))

                Row(verticalAlignment = Alignment.Bottom) {
                    Column(modifier = Modifier.weight(1f)) {
                        if (product.hasPriceRange) {
                            Text(
                                text = "Mulai dari",
                                fontSize = 9.sp,
                                color = TextTertiary
                            )
                        }
                        Text(
                            text = priceLabel(product.priceMin, product.priceMax),
                            fontSize = 13.5.sp,
                            fontWeight = FontWeight.Bold,
                            color = Primary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                    Icon(
                        imageVector = Icons.Filled.ChevronRight,
                        contentDescription = null,
                        tint = Primary.copy(alpha = 0.55f),
                        modifier = Modifier.size(11.dp)
                    )
                    Spacer(Modifier.width(2.dp))
                }
            }
        }
    }
}

/** "Rp 25.000" atau "Rp 25.000 – Rp 40.000". */
fun priceLabel(min: Long?, max: Long?): String = when {
    min == null && max == null -> "Harga belum tersedia"
    min != null && max != null && min != max ->
        "${formatRupiah(min.toDouble())} – ${formatRupiah(max.toDouble())}"
    else -> formatRupiah((min ?: max ?: 0L).toDouble())
}

/** Pengatur jumlah: dua tombol 34 dp dengan angka tebal di tengah. */
@Composable
fun QuantityStepper(
    quantity: Int,
    onDecrease: () -> Unit,
    onIncrease: () -> Unit,
    modifier: Modifier = Modifier,
    canIncrease: Boolean = true
) {
    Row(
        modifier = modifier
            .clip(RoundedCornerShape(PetHealRadius.md))
            .background(SurfaceMuted),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Box(
            modifier = Modifier
                .size(34.dp)
                .clickable(onClick = onDecrease),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                Icons.Filled.Remove,
                contentDescription = "Kurangi",
                tint = OnBackground,
                modifier = Modifier.size(16.dp)
            )
        }
        Text(
            text = quantity.toString(),
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            color = OnBackground,
            modifier = Modifier.width(22.dp),
            textAlign = androidx.compose.ui.text.style.TextAlign.Center
        )
        Box(
            modifier = Modifier
                .size(34.dp)
                .clickable(enabled = canIncrease, onClick = onIncrease),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                Icons.Filled.Add,
                contentDescription = "Tambah",
                tint = if (canIncrease) OnBackground else TextTertiary,
                modifier = Modifier.size(16.dp)
            )
        }
    }
}

/** Thumbnail kecil di keranjang, checkout, dan rincian pesanan. */
@Composable
fun PetShopImage(
    url: String?,
    contentDescription: String?,
    modifier: Modifier = Modifier
) {
    PetShopPhoto(
        url = url,
        contentDescription = contentDescription,
        iconSize = 18.dp,
        modifier = modifier
    )
}

/**
 * Foto produk dengan ilustrasi kategori sebagai lapisan dasar. Ilustrasi itu
 * yang tampil selama foto dimuat maupun saat URL kosong/gagal dimuat, jadi
 * katalog tidak pernah menampilkan kotak kosong dan tidak berkedip putih saat
 * foto selesai dimuat.
 */
@Composable
fun PetShopPhoto(
    url: String?,
    contentDescription: String?,
    categorySlug: String? = null,
    categoryName: String? = null,
    modifier: Modifier = Modifier,
    iconSize: Dp = 36.dp
) {
    var failed by remember(url) { mutableStateOf(false) }

    Box(modifier = modifier) {
        ProductArtworkTile(
            categorySlug = categorySlug,
            categoryName = categoryName,
            iconSize = iconSize,
            modifier = Modifier.fillMaxSize()
        )
        if (!url.isNullOrBlank() && !failed) {
            AsyncImage(
                model = url,
                contentDescription = contentDescription,
                contentScale = ContentScale.Crop,
                onError = { failed = true },
                modifier = Modifier.fillMaxSize()
            )
        }
    }
}

@Composable
private fun ProductArtworkTile(
    categorySlug: String?,
    categoryName: String?,
    iconSize: Dp,
    modifier: Modifier = Modifier
) {
    val artwork = categoryArtwork(categorySlug, categoryName)

    Box(
        modifier = modifier.background(artwork.accent.copy(alpha = 0.07f)),
        contentAlignment = Alignment.Center
    ) {
        Icon(
            imageVector = artwork.icon,
            contentDescription = null,
            tint = artwork.accent,
            modifier = Modifier.size(iconSize)
        )
    }
}

fun cartSummaryLabel(count: Int, total: Long): String =
    "$count item · ${formatRupiah(total.toDouble())}"

/** Peringatan sebaris (error) dengan ikon, sudut 14 dp. */
@Composable
fun PetShopInlineError(message: String, modifier: Modifier = Modifier) {
    Surface(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(ShopTheme.innerRadius),
        color = ErrorSoft
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 11.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(9.dp)
        ) {
            Icon(
                imageVector = Icons.Filled.Info,
                contentDescription = null,
                tint = Error,
                modifier = Modifier.size(16.dp)
            )
            Text(
                text = message,
                fontSize = 12.sp,
                color = Error,
                lineHeight = 17.sp,
                modifier = Modifier.weight(1f)
            )
        }
    }
}

/** Catatan informasi (bukan error) dengan ikon, sudut 14 dp. */
@Composable
fun PetShopInfoBanner(message: String, modifier: Modifier = Modifier) {
    Surface(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(ShopTheme.innerRadius),
        color = InfoSoft
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 11.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(9.dp)
        ) {
            Icon(
                imageVector = Icons.Filled.Info,
                contentDescription = null,
                tint = Info,
                modifier = Modifier.size(16.dp)
            )
            Text(
                text = message,
                fontSize = 12.sp,
                color = Info,
                lineHeight = 17.sp,
                modifier = Modifier.weight(1f)
            )
        }
    }
}

/**
 * Garis putus-putus. Dipakai sebagai pemisah bergaya struk belanja pada kartu
 * pembayaran, kode pengambilan, dan bukti pembayaran — pola yang sama seperti
 * struk kertas, sehingga mata langsung mengenalinya sebagai dokumen transaksi.
 */
@Composable
fun DashedDivider(
    modifier: Modifier = Modifier,
    color: Color = Outline
) {
    Canvas(
        modifier = modifier
            .fillMaxWidth()
            .height(1.dp)
    ) {
        drawLine(
            color = color,
            start = Offset(0f, size.height / 2f),
            end = Offset(size.width, size.height / 2f),
            strokeWidth = size.height,
            pathEffect = PathEffect.dashPathEffect(floatArrayOf(7f, 6f), 0f)
        )
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
