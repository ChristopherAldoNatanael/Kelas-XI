package com.christopheraldoo.petheal.ui.screens.petshop

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
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
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.ImageNotSupported
import androidx.compose.material.icons.filled.Pets
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material.icons.filled.ShoppingBag
import androidx.compose.material.icons.outlined.ReceiptLong
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.christopheraldoo.petheal.data.model.PetShopOrder
import com.christopheraldoo.petheal.data.model.PetShopProduct
import com.christopheraldoo.petheal.ui.theme.AccentViolet
import com.christopheraldoo.petheal.ui.theme.ErrorSoft
import com.christopheraldoo.petheal.ui.theme.Info
import com.christopheraldoo.petheal.ui.theme.InfoSoft
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.PetHealRadius
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.PrimaryLight
import com.christopheraldoo.petheal.ui.theme.Secondary
import com.christopheraldoo.petheal.ui.theme.SecondaryLight
import com.christopheraldoo.petheal.ui.theme.Surface
import com.christopheraldoo.petheal.ui.theme.SurfaceMuted
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.ui.theme.WarningSoft
import com.christopheraldoo.petheal.ui.theme.WarningText
import com.christopheraldoo.petheal.util.formatRupiah

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

/** Header standar Pet Shop: kembali, judul, pesanan, keranjang. */
@Composable
fun PetShopHeader(
    title: String,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    onBack: (() -> Unit)? = null,
    cartCount: Int = 0,
    onOpenCart: (() -> Unit)? = null,
    onOpenOrders: (() -> Unit)? = null
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .statusBarsPadding()
            .padding(horizontal = 16.dp, vertical = 12.dp)
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            if (onBack != null) {
                IconButton(onClick = onBack) {
                    Icon(Icons.Filled.ArrowBack, contentDescription = "Kembali", tint = OnBackground)
                }
                Spacer(Modifier.width(4.dp))
            }
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = title,
                    fontSize = 20.sp,
                    fontWeight = FontWeight.Bold,
                    color = OnBackground,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                if (!subtitle.isNullOrBlank()) {
                    Text(
                        text = subtitle,
                        fontSize = 12.sp,
                        color = TextSecondary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                }
            }

            if (onOpenOrders != null) {
                IconButton(onClick = onOpenOrders) {
                    Icon(Icons.Outlined.ReceiptLong, contentDescription = "Pesanan saya", tint = OnBackground)
                }
            }

            if (onOpenCart != null) {
                Box {
                    IconButton(onClick = onOpenCart) {
                        Icon(Icons.Filled.ShoppingBag, contentDescription = "Keranjang", tint = OnBackground)
                    }
                    if (cartCount > 0) {
                        Box(
                            modifier = Modifier
                                .align(Alignment.TopEnd)
                                .padding(top = 4.dp, end = 2.dp)
                                .size(18.dp)
                                .clip(CircleShape)
                                .background(Primary),
                            contentAlignment = Alignment.Center
                        ) {
                            Text(
                                text = if (cartCount > 99) "99+" else cartCount.toString(),
                                fontSize = 9.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color.White
                            )
                        }
                    }
                }
            }
        }
    }
}

/** Kartu produk untuk grid katalog. */
@Composable
fun PetShopProductCard(
    product: PetShopProduct,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier.clickable(onClick = onClick),
        shape = RoundedCornerShape(PetHealRadius.lg),
        colors = CardDefaults.cardColors(containerColor = Surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
        border = BorderStroke(1.dp, Outline)
    ) {
        Column {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .aspectRatio(1f)
                    .background(SurfaceMuted)
            ) {
                if (!product.imageUrl.isNullOrBlank()) {
                    AsyncImage(
                        model = product.imageUrl,
                        contentDescription = product.name,
                        contentScale = ContentScale.Crop,
                        modifier = Modifier.fillMaxWidth().aspectRatio(1f)
                    )
                } else {
                    Icon(
                        Icons.Filled.Pets,
                        contentDescription = null,
                        tint = TextTertiary,
                        modifier = Modifier.align(Alignment.Center).size(44.dp)
                    )
                }

                if (product.hasDiscount) {
                    Surface(
                        modifier = Modifier.align(Alignment.TopStart).padding(8.dp),
                        shape = RoundedCornerShape(PetHealRadius.sm),
                        color = MaterialTheme.colorScheme.error
                    ) {
                        Text(
                            "Diskon",
                            modifier = Modifier.padding(horizontal = 7.dp, vertical = 3.dp),
                            fontSize = 9.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White
                        )
                    }
                }

                if (product.totalAvailableStock <= 5) {
                    Surface(
                        modifier = Modifier.align(Alignment.BottomEnd).padding(8.dp),
                        shape = RoundedCornerShape(PetHealRadius.sm),
                        color = WarningSoft
                    ) {
                        Text(
                            "Sisa ${product.totalAvailableStock}",
                            modifier = Modifier.padding(horizontal = 6.dp, vertical = 3.dp),
                            fontSize = 9.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = WarningText
                        )
                    }
                }
            }

            Column(modifier = Modifier.padding(10.dp)) {
                Text(
                    text = product.name,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground,
                    maxLines = 2,
                    minLines = 2,
                    overflow = TextOverflow.Ellipsis,
                    lineHeight = 17.sp
                )

                Spacer(Modifier.height(4.dp))

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        Icons.Filled.Pets,
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
                        overflow = TextOverflow.Ellipsis
                    )
                }

                Spacer(Modifier.height(6.dp))

                Text(
                    text = priceLabel(product.priceMin, product.priceMax),
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = Primary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
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
        IconButton(onClick = onDecrease, modifier = Modifier.size(34.dp)) {
            Icon(Icons.Filled.Remove, contentDescription = "Kurangi", modifier = Modifier.size(17.dp))
        }
        Text(
            text = quantity.toString(),
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            color = OnBackground,
            modifier = Modifier.width(24.dp)
        )
        IconButton(
            onClick = onIncrease,
            enabled = canIncrease,
            modifier = Modifier.size(34.dp)
        ) {
            Icon(
                Icons.Filled.Add,
                contentDescription = "Tambah",
                modifier = Modifier.size(17.dp),
                tint = if (canIncrease) OnBackground else TextTertiary
            )
        }
    }
}

@Composable
fun PetShopImage(
    url: String?,
    contentDescription: String?,
    modifier: Modifier = Modifier
) {
    Box(modifier = modifier.background(SurfaceMuted), contentAlignment = Alignment.Center) {
        if (!url.isNullOrBlank()) {
            AsyncImage(
                model = url,
                contentDescription = contentDescription,
                contentScale = ContentScale.Crop,
                modifier = Modifier.fillMaxSize()
            )
        } else {
            Icon(
                Icons.Filled.ImageNotSupported,
                contentDescription = null,
                tint = TextTertiary,
                modifier = Modifier.size(26.dp)
            )
        }
    }
}

fun cartSummaryLabel(count: Int, total: Long): String =
    "$count item · ${formatRupiah(total.toDouble())}"

@Composable
fun PetShopInlineError(message: String, modifier: Modifier = Modifier) {
    Surface(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(PetHealRadius.md),
        color = ErrorSoft
    ) {
        Text(
            text = message,
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp),
            fontSize = 12.sp,
            color = MaterialTheme.colorScheme.error,
            lineHeight = 18.sp
        )
    }
}

@Composable
fun PetShopInfoBanner(message: String, modifier: Modifier = Modifier) {
    Surface(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(PetHealRadius.md),
        color = InfoSoft
    ) {
        Text(
            text = message,
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp),
            fontSize = 12.sp,
            color = Info,
            lineHeight = 18.sp
        )
    }
}
