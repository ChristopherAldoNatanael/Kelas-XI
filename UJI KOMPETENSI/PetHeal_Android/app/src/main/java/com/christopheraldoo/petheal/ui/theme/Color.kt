package com.christopheraldoo.petheal.ui.theme

import androidx.compose.ui.graphics.Color

// PetHeal production palette: clinical, warm, and high contrast.
val Primary = Color(0xFF18C964)
val PrimaryDark = Color(0xFF087A3A)
val PrimaryLight = Color(0xFFE8F9EF)

val Secondary = Color(0xFF0EA5A5)
val SecondaryDark = Color(0xFF0F766E)
val SecondaryLight = Color(0xFFE6FFFB)

val Tertiary = Color(0xFFF59E0B)
val TertiaryLight = Color(0xFFFFF7E6)

val Background = Color(0xFFF6F8F6)
val Surface = Color(0xFFFFFFFF)
val SurfaceVariant = Color(0xFFF8FAFC)
val Outline = Color(0xFFE2E8F0)
val Error = Color(0xFFDC2626)

val OnPrimary = Color(0xFFFFFFFF)
val OnSecondary = Color(0xFFFFFFFF)
val OnBackground = Color(0xFF0F172A)
val OnSurface = Color(0xFF0F172A)
val TextSecondary = Color(0xFF64748B)
val TextTertiary = Color(0xFF94A3B8)
val OnError = Color(0xFFFFFFFF)

// ── Semantic tokens ──────────────────────────────────────────────────────────
// Nilai di bawah ini sebelumnya ditulis ulang sebagai hex mentah di 15+ layar.
// Layar WAJIB merujuk ke token ini, bukan menambah Color(0xFF...) baru.
val ErrorStrong = Color(0xFFEF4444)        // aksi destruktif (hapus, batal)
val ErrorSoft = Color(0xFFFFEBEE)          // latar banner error
val Success = Color(0xFF10B981)            // konfirmasi positif
val SuccessSoft = Color(0xFFE8F5E9)        // latar banner sukses
val SuccessDeep = Color(0xFF047857)        // teks sukses di latar soft
val Info = Color(0xFF0EA5E9)               // informasi / jadwal
val InfoStrong = Color(0xFF3B82F6)         // biru aksen (in-progress, bank transfer)
val InfoSoft = Color(0xFFE0F2FE)           // latar info
val WarningDeep = Color(0xFFEA580C)        // peringatan kuat (teks)
val WarningSoft = Color(0xFFFEF3C7)        // latar peringatan
val WarningText = Color(0xFF92400E)        // teks peringatan di latar soft
val Neutral = Color(0xFF6B7280)            // status netral / tidak diketahui
val AccentViolet = Color(0xFF8B5CF6)       // aksen E-Wallet & follow-up
val OutlineStrong = Color(0xFFCBD5E1)      // garis input / elemen nonaktif
val Star = Color(0xFFFFC857)               // rating bintang
val OnPrimaryStrong = Color(0xFF052E14)    // teks/ikon di atas Primary
val PrimaryWash = Color(0xFFF2FBF5)        // gradasi putih→hijau (onboarding/auth)
val SurfaceMuted = Color(0xFFF1F5F9)       // baris/divider halus
val TextMuted = Color(0xFF334155)          // label sekunder lebih tegas

val StatusPending = Color(0xFFF59E0B)
val StatusConfirmed = Color(0xFF18C964)
val StatusCompleted = Color(0xFF2563EB)
val StatusCancelled = Color(0xFFEF4444)
val StatusRejected = Color(0xFFB91C1C)
val StatusPaid = Color(0xFF0EA5A5)

val DogColor = Color(0xFF8D6E63)
val CatColor = Color(0xFFFFB74D)
val BirdColor = Color(0xFF4FC3F7)
val RabbitColor = Color(0xFFE0E0E0)
val OtherColor = Color(0xFF90A4AE)
