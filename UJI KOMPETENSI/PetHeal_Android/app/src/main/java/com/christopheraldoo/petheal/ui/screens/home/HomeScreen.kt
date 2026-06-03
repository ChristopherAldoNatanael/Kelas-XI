package com.christopheraldoo.petheal.ui.screens.home

import android.util.Log
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.platform.LocalContext
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import coil.request.ImageRequest
import com.christopheraldoo.petheal.data.model.Booking
import com.christopheraldoo.petheal.data.repository.NotificationRepository
import com.christopheraldoo.petheal.util.ThumbnailImage
import com.christopheraldoo.petheal.util.buildPhotoUrl

private const val TAG = "HomePhoto"

// ── Brand tokens ─────────────────────────────────────────────────────────────
private val HomePrimary       = Color(0xFF2BEE6C)
private val HomeBgDark        = Color(0xFF102216)
private val HomeBgLight       = Color(0xFFF6F8F6)
private val HomeSurfaceDark   = Color(0xFF1C2E22)
private val HomeSurfaceLight  = Color(0xFFFFFFFF)
private val HomeBorderDark    = Color(0x0DFFFFFF)   // white/5
private val HomeBorderLight   = Color(0xFFE2E8F0)
private val HomeTextSecondary = Color(0xFF9DB9A6)

@Composable
fun HomeScreen(
    onNavigateToPets: () -> Unit,
    onNavigateToDoctors: () -> Unit,
    onNavigateToBookings: () -> Unit,
    onNavigateToMedicalRecords: () -> Unit,
    onNavigateToProfile: () -> Unit,
    onNavigateToNotifications: () -> Unit = {},
    viewModel: HomeViewModel = hiltViewModel()
){
    val uiState by viewModel.uiState.collectAsState()
    val isDark = isSystemInDarkTheme()

    val bgColor      = if (isDark) HomeBgDark      else HomeBgLight
    val surfaceColor = if (isDark) HomeSurfaceDark  else HomeSurfaceLight
    val borderColor  = if (isDark) HomeBorderDark   else HomeBorderLight
    val textPrimary  = if (isDark) Color.White      else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(bgColor)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .padding(bottom = 88.dp)     // space for bottom nav
        ) {

            // ── Header ────────────────────────────────────────────────
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 24.dp)
                    .padding(top = 40.dp, bottom = 8.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    // Avatar
                    Box(
                        modifier = Modifier
                            .size(48.dp)
                            .clip(CircleShape)
                            .border(2.dp, HomePrimary.copy(alpha = 0.2f), CircleShape)
                            .background(HomePrimary.copy(alpha = 0.15f))
                    ) {
                        if (uiState.userPhoto != null) {
                            // ✅ OPTIMIZED: ThumbnailImage resizes to 100px BEFORE decode
                            ThumbnailImage(
                                model = uiState.userPhoto,
                                contentDescription = "Profile Picture",
                                modifier = Modifier.fillMaxSize().clip(CircleShape)
                            )
                        } else {
                            Icon(
                                Icons.Filled.Person,
                                contentDescription = null,
                                tint = HomePrimary,
                                modifier = Modifier
                                    .size(28.dp)
                                    .align(Alignment.Center)
                            )                        }
                    }
                    Column {
                        Text(
                            text = "Welcome back,",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Medium,
                            color = textSecondary
                        )
                        // Show first name from profile; while loading show shimmer-like dots
                        val firstName = uiState.userName
                            .trim()
                            .split(" ")
                            .firstOrNull { it.isNotBlank() }
                            ?: ""
                        if (firstName.isBlank()) {
                            Box(
                                modifier = Modifier
                                    .width(90.dp)
                                    .height(22.dp)
                                    .clip(RoundedCornerShape(6.dp))
                                    .background(textPrimary.copy(alpha = 0.12f))
                            )
                        } else {
                            Text(
                                text = "$firstName!",
                                fontSize = 20.sp,
                                fontWeight = FontWeight.Bold,
                                color = textPrimary
                            )
                        }
                    }
                }                // Notification bell with unread badge
                Box(
                    modifier = Modifier
                        .size(40.dp)
                        .clip(CircleShape)
                        .background(surfaceColor)
                        .clickable { onNavigateToNotifications() },
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        Icons.Filled.Notifications,
                        contentDescription = "Notifications",
                        tint = textPrimary,
                        modifier = Modifier.size(24.dp)
                    )
                    // Unread badge
                    if (uiState.unreadNotificationCount > 0) {
                        Box(
                            modifier = Modifier
                                .align(Alignment.TopEnd)
                                .offset(x = 2.dp, y = (-2).dp)
                                .size(if (uiState.unreadNotificationCount > 9) 18.dp else 15.dp)
                                .clip(CircleShape)
                                .background(Color(0xFFEF4444)),
                            contentAlignment = Alignment.Center
                        ) {
                            Text(
                                text = if (uiState.unreadNotificationCount > 99) "99+"
                                       else uiState.unreadNotificationCount.toString(),
                                color = Color.White,
                                fontSize = 8.sp,
                                fontWeight = FontWeight.Bold
                            )
                        }
                    }
                }
            }

            // ── Upcoming Booking ──────────────────────────────────────
            Column(modifier = Modifier.padding(horizontal = 24.dp, vertical = 16.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        "Upcoming Booking",
                        fontSize = 18.sp, fontWeight = FontWeight.Bold,
                        color = textPrimary
                    )
                    Text(
                        "See All",
                        fontSize = 12.sp, fontWeight = FontWeight.SemiBold,
                        color = HomePrimary,
                        modifier = Modifier.clickable { onNavigateToBookings() }
                    )
                }
                Spacer(modifier = Modifier.height(12.dp))

                // ✅ OPTIMIZATION: Show Skeleton immediately while data loads (feels instant)
                if (uiState.isBookingLoading && uiState.upcomingBooking == null) {
                    BookingCardSkeleton(isDark = isDark, surfaceColor = surfaceColor, borderColor = borderColor)
                } else {
                    Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = surfaceColor),
                    border = androidx.compose.foundation.BorderStroke(1.dp, borderColor),
                    elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            // Left: booking info
                            Column(
                                modifier = Modifier.weight(1f),
                                verticalArrangement = Arrangement.spacedBy(12.dp)
                            ) {
                                // Pet name chip
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    Box(
                                        modifier = Modifier
                                            .size(32.dp)
                                            .clip(CircleShape)
                                            .background(HomePrimary.copy(alpha = 0.2f)),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(
                                            Icons.Filled.Pets,
                                            contentDescription = null,
                                            tint = HomePrimary,
                                            modifier = Modifier.size(18.dp)
                                        )
                                    }
                                    Text(
                                        text = if (uiState.upcomingBooking != null)
                                            "${uiState.upcomingBooking!!.pet?.name ?: "Pet"} (${uiState.upcomingBooking!!.pet?.species ?: ""})"
                                        else "No upcoming booking",
                                        fontSize = 13.sp, fontWeight = FontWeight.SemiBold,
                                        color = if (isDark) Color(0xFFCBD5E1) else Color(0xFF334155),
                                        maxLines = 1, overflow = TextOverflow.Ellipsis
                                    )
                                }
                                // Doctor
                                Column {
                                    Text(
                                        text = uiState.upcomingBooking?.doctor?.name ?: "–",
                                        fontSize = 15.sp, fontWeight = FontWeight.Bold,
                                        color = textPrimary
                                    )
                                    Text(
                                        text = if (uiState.upcomingBooking != null)
                                            "${uiState.upcomingBooking!!.doctor?.specialization ?: "Veterinarian"} • General Checkup"
                                        else "Book an appointment",
                                        fontSize = 12.sp, color = textSecondary
                                    )
                                }
                                // Time chip
                                Row(
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(bgColor)
                                        .padding(horizontal = 8.dp, vertical = 6.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                ) {
                                    Icon(
                                        Icons.Filled.Schedule,
                                        contentDescription = null,
                                        tint = HomePrimary,
                                        modifier = Modifier.size(16.dp)
                                    )
                                    Text(
                                        text = if (uiState.upcomingBooking != null)
                                            "${uiState.upcomingBooking!!.bookingTime ?: ""} · ${uiState.upcomingBooking!!.bookingDate ?: ""}"
                                        else "–",
                                        fontSize = 12.sp, fontWeight = FontWeight.Medium,
                                        color = if (isDark) Color(0xFFCBD5E1) else Color(0xFF334155)
                                    )
                                }
                            }                            // Doctor photo
                            Box(
                                modifier = Modifier
                                    .size(96.dp)
                                    .clip(RoundedCornerShape(12.dp))
                                    .background(if (isDark) Color(0xFF1E293B) else Color(0xFFE2E8F0))
                            ) {
                                val doctorPhoto = remember(uiState.upcomingBooking) {
                                    buildPhotoUrl(uiState.upcomingBooking?.doctor?.photo)
                                }
                                if (!doctorPhoto.isNullOrBlank()) {
                                    // ✅ OPTIMIZED: ThumbnailImage resizes to 100px BEFORE decode → huge memory savings!
                                    ThumbnailImage(
                                        model = doctorPhoto,
                                        contentDescription = "Doctor",
                                        modifier = Modifier.fillMaxSize().clip(CircleShape)
                                    )
                                } else {
                                    Icon(
                                        Icons.Filled.Person,
                                        contentDescription = null,
                                        tint = if (isDark) Color(0xFF475569) else Color(0xFF94A3B8),
                                        modifier = Modifier
                                            .size(44.dp)
                                            .align(Alignment.Center)
                                    )
                                }
                            }
                        }
                        Spacer(modifier = Modifier.height(16.dp))
                        // Action row
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Button(
                                onClick = {
                                    if (uiState.upcomingBooking != null) onNavigateToBookings()
                                    else onNavigateToBookings()
                                },
                                modifier = Modifier
                                    .weight(1f)
                                    .height(42.dp),
                                shape = RoundedCornerShape(8.dp),
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = HomePrimary,
                                    contentColor = HomeBgDark
                                )
                            ) {
                                Text(
                                    if (uiState.upcomingBooking != null) "View Details" else "Book Now",
                                    fontSize = 14.sp, fontWeight = FontWeight.Bold
                                )
                            }
                            OutlinedIconButton(
                                onClick = { },
                                modifier = Modifier.size(42.dp),
                                shape = RoundedCornerShape(8.dp),
                                border = androidx.compose.foundation.BorderStroke(1.dp, borderColor),
                                colors = IconButtonDefaults.outlinedIconButtonColors(
                                    contentColor = textSecondary
                                )
                            ) {
                                Icon(
                                    Icons.Filled.Call,
                                    contentDescription = "Call",
                                    modifier = Modifier.size(20.dp)
                                )
                            }
                        }
                    }
                }
                }
            }

            // ── Quick Actions ─────────────────────────────────────────
            Column(modifier = Modifier.padding(horizontal = 24.dp, vertical = 8.dp)) {
                Text(
                    "Quick Actions",
                    fontSize = 18.sp, fontWeight = FontWeight.Bold,
                    color = textPrimary
                )
                Spacer(modifier = Modifier.height(16.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    QuickActionItem(
                        icon = Icons.Filled.CalendarMonth,
                        label = "Book\nAppt.",
                        bgColor = Color(0xFFDBEAFE),
                        bgColorDark = Color(0x336B9FFF),
                        iconColor = Color(0xFF2563EB),
                        iconColorDark = Color(0xFF93C5FD),
                        isDark = isDark,
                        onClick = onNavigateToDoctors
                    )
                    QuickActionItem(
                        icon = Icons.Filled.Pets,
                        label = "My\nPets",
                        bgColor = Color(0xFFF3E8FF),
                        bgColorDark = Color(0x33A855F7),
                        iconColor = Color(0xFF9333EA),
                        iconColorDark = Color(0xFFD8B4FE),
                        isDark = isDark,
                        onClick = onNavigateToPets
                    )
                    QuickActionItem(
                        icon = Icons.Filled.Article,
                        label = "Medical\nRecords",
                        bgColor = Color(0xFFFFEDD5),
                        bgColorDark = Color(0x33F97316),
                        iconColor = Color(0xFFEA580C),
                        iconColorDark = Color(0xFFFDBA74),
                        isDark = isDark,
                        onClick = onNavigateToMedicalRecords
                    )
                    QuickActionItem(
                        icon = Icons.Filled.Chat,
                        label = "Consult\nDoctor",
                        bgColor = Color(0xFFFFE4E6),
                        bgColorDark = Color(0x33EC4899),
                        iconColor = Color(0xFFDB2777),
                        iconColorDark = Color(0xFFF9A8D4),
                        isDark = isDark,
                        onClick = onNavigateToDoctors
                    )
                }
            }

            // ── Health Tips ───────────────────────────────────────────
            Column(modifier = Modifier.padding(top = 16.dp, bottom = 8.dp)) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 24.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        "Health Tips",
                        fontSize = 18.sp, fontWeight = FontWeight.Bold,
                        color = textPrimary
                    )
                }
                Spacer(modifier = Modifier.height(12.dp))
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState())
                        .padding(horizontal = 24.dp),
                    horizontalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    HealthTipCard(
                        imageUrl = "https://lh3.googleusercontent.com/aida-public/AB6AXuCM0DQP85aKKUNdLcsJBJs5Fd2zqyJLha64gB7VjyGegqH4r554kZX7v3NJBdvPhildH2iJLlJDJaWQto8rYOuj867UnlpFdLotuH3NshA5crJYYuwXC9Iohc45R-kXx1HUVSBr6uFr3xNH2T7z-ZKP_LyVA_tcMO1JK87nmQE1QWqCn3mEm2z-XqVZypMrVMGWoji8qjO4m3qMow0ZZU2-D_d9arUzoLjO0GV526jEtRYGALpeecaDCNfNG0S-p6GD5WHEjzVPbN0",
                        category = "Nutrition",
                        categoryColor = HomePrimary,
                        title = "Best Diet for Puppies",
                        description = "Learn what nutrients are essential for your growing puppy's development.",
                        surfaceColor = surfaceColor,
                        borderColor = borderColor,
                        textPrimary = textPrimary,
                        textSecondary = textSecondary
                    )
                    HealthTipCard(
                        imageUrl = "https://lh3.googleusercontent.com/aida-public/AB6AXuDpk2AgI23yLIHRLGyKAgTQ7FUa21qinguSCaki3CAY2yz-6ttMUBM-WbnXyFx-Hw2i7DdBqXjZCd7fnd0ngbjPV3IAWILBkq-cZxR67NopI7GZwd_VepQsGn6VRSDcDyGgjCvS4XmMNQmfCIEHL8cJfmlOX1IRU-7jLeb4TjV0UJov0J6BBz0ldpOToUhnji9AtRtwBtCUJk_ZbENNVMEJRV3OimMoazRwD5AoZBl3m8MWawWY88_xeXda_OMd-OS-YrrFY3K5jow",
                        category = "Behavior",
                        categoryColor = Color(0xFF3B82F6),
                        title = "Understanding Cat Purrs",
                        description = "Why do cats purr? It's not always because they are happy.",
                        surfaceColor = surfaceColor,
                        borderColor = borderColor,
                        textPrimary = textPrimary,
                        textSecondary = textSecondary
                    )
                    HealthTipCard(
                        imageUrl = "https://lh3.googleusercontent.com/aida-public/AB6AXuA4dZzA3_H-H82Oe_LrsmaQcLX5sQepaViJjwGfXmL9klupomWEcVwdqKxMAiwQpfYRklWyaCNDp1nmTB_6FWnyMd8ViznYBwSc1MZnAAYZGORlnQ6JCyRPzry191rPDu8tHyUNBv94nA2vMfzXjNC6z9FhTxNYh7MSei3dCg32MzHlZ81XjLjMh43_96O6XBMhFrkzxQwRkMvik7xWH2_NGNd7q4fjr3wrzMUYBc4cn78cEiRTTcSmqobi27GdLIWslNEdmeByuNE",
                        category = "Wellness",
                        categoryColor = Color(0xFFEA580C),
                        title = "Regular Vet Checkups",
                        description = "Annual checkups are key to keeping your pet healthy and catching issues early.",
                        surfaceColor = surfaceColor,
                        borderColor = borderColor,
                        textPrimary = textPrimary,
                        textSecondary = textSecondary
                    )
                }
                Spacer(modifier = Modifier.height(8.dp))
            }
        }

        // ── Bottom Navigation Bar ─────────────────────────────────────
        BottomNavBar(
            modifier = Modifier.align(Alignment.BottomCenter),
            isDark = isDark,
            surfaceColor = surfaceColor,
            borderColor = borderColor,
            textPrimary = textPrimary,
            onHome = { /* already here */ },
            onPets = onNavigateToPets,
            onBookings = onNavigateToBookings,
            onProfile = onNavigateToProfile
        )
    }
}

// ── Quick Action Item ─────────────────────────────────────────────────────────
@Composable
private fun QuickActionItem(
    icon: ImageVector,
    label: String,
    bgColor: Color,
    bgColorDark: Color,
    iconColor: Color,
    iconColorDark: Color,
    isDark: Boolean,
    onClick: () -> Unit
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier.clickable(onClick = onClick)
    ) {
        Box(
            modifier = Modifier
                .size(56.dp)
                .clip(RoundedCornerShape(16.dp))
                .background(if (isDark) bgColorDark else bgColor),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = label,
                tint = if (isDark) iconColorDark else iconColor,
                modifier = Modifier.size(28.dp)
            )
        }
        Text(
            text = label,
            fontSize = 11.sp, fontWeight = FontWeight.Medium,
            color = if (isDark) Color(0xFFCBD5E1) else Color(0xFF475569),
            lineHeight = 14.sp,
            textAlign = androidx.compose.ui.text.style.TextAlign.Center
        )
    }
}

// ── Health Tip Card ───────────────────────────────────────────────────────────
@Composable
private fun HealthTipCard(
    imageUrl: String,
    category: String,
    categoryColor: Color,
    title: String,
    description: String,
    surfaceColor: Color,
    borderColor: Color,
    textPrimary: Color,
    textSecondary: Color
) {
    Card(
        modifier = Modifier.width(260.dp),
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = surfaceColor),
        border = androidx.compose.foundation.BorderStroke(1.dp, borderColor),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column {
            AsyncImage(
                model = imageUrl,
                contentDescription = title,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(128.dp),
                contentScale = ContentScale.Crop
            )
            Column(modifier = Modifier.padding(12.dp)) {
                Text(
                    text = category.uppercase(),
                    fontSize = 10.sp, fontWeight = FontWeight.Bold,
                    color = categoryColor,
                    letterSpacing = 1.sp
                )
                Spacer(modifier = Modifier.height(4.dp))
                Text(
                    text = title,
                    fontSize = 13.sp, fontWeight = FontWeight.Bold,
                    color = textPrimary, maxLines = 1, overflow = TextOverflow.Ellipsis
                )
                Spacer(modifier = Modifier.height(4.dp))
                Text(
                    text = description,
                    fontSize = 11.sp, color = textSecondary,
                    maxLines = 2, overflow = TextOverflow.Ellipsis,
                    lineHeight = 16.sp
                )
            }
        }
    }
}

// ── Bottom Navigation Bar ─────────────────────────────────────────────────────
@Composable
private fun BottomNavBar(
    modifier: Modifier = Modifier,
    isDark: Boolean,
    surfaceColor: Color,
    borderColor: Color,
    textPrimary: Color,
    onHome: () -> Unit,
    onPets: () -> Unit,
    onBookings: () -> Unit,
    onProfile: () -> Unit
) {
    val borderTop = if (isDark) Color(0x0DFFFFFF) else Color(0xFFE2E8F0)
    Surface(
        modifier = modifier.fillMaxWidth(),
        color = if (isDark) surfaceColor.copy(alpha = 0.95f) else surfaceColor,
        tonalElevation = 0.dp,
        shadowElevation = 8.dp
    ) {
        Column {
            Divider(color = borderTop, thickness = 1.dp)
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 24.dp)
                    .padding(top = 12.dp, bottom = 20.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Bottom
            ) {
                // Home (active)
                NavBarItem(
                    icon = Icons.Filled.Home,
                    label = "Home",
                    isActive = true,
                    isDark = isDark,
                    onClick = onHome
                )
                NavBarItem(
                    icon = Icons.Filled.Pets,
                    label = "Pets",
                    isActive = false,
                    isDark = isDark,
                    onClick = onPets
                )
                NavBarItem(
                    icon = Icons.Filled.CalendarMonth,
                    label = "Bookings",
                    isActive = false,
                    isDark = isDark,
                    onClick = onBookings
                )
                NavBarItem(
                    icon = Icons.Filled.Person,
                    label = "Profile",
                    isActive = false,
                    isDark = isDark,
                    onClick = onProfile
                )
            }
        }
    }
}

@Composable
private fun NavBarItem(
    icon: ImageVector,
    label: String,
    isActive: Boolean,
    isDark: Boolean,
    onClick: () -> Unit
) {
    Column(
        modifier = Modifier
            .width(64.dp)
            .clickable(onClick = onClick),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(4.dp)
    ) {
        Box(
            modifier = Modifier
                .size(32.dp)
                .clip(CircleShape)
                .background(
                    if (isActive) HomePrimary.copy(alpha = 0.1f) else Color.Transparent
                ),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = label,
                tint = if (isActive) HomePrimary
                       else if (isDark) Color(0xFF64748B) else Color(0xFF94A3B8),
                modifier = Modifier.size(24.dp)
            )
        }
        Text(
            text = label,
            fontSize = 10.sp, fontWeight = FontWeight.Medium,
            color = if (isActive) (if (isDark) Color.White else Color(0xFF0F172A))
                    else if (isDark) Color(0xFF64748B) else Color(0xFF94A3B8)
        )
    }
}

// ✅ OPTIMIZATION: Skeleton UI for Booking Card (Prevents blank screen feeling)
@Composable
private fun BookingCardSkeleton(
    isDark: Boolean,
    surfaceColor: Color,
    borderColor: Color
) {
    val skeletonColor = if (isDark) Color(0xFF2E4536) else Color(0xFFE2E8F0)
    Card(
        modifier = Modifier.fillMaxWidth().height(160.dp),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = surfaceColor),
        border = androidx.compose.foundation.BorderStroke(1.dp, borderColor),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Box(modifier = Modifier.fillMaxSize().padding(16.dp)) {
            Column(
                modifier = Modifier.align(Alignment.CenterStart),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Box(Modifier.size(120.dp).height(16.dp).clip(RoundedCornerShape(8.dp)).background(skeletonColor))
                Box(Modifier.size(160.dp).height(20.dp).clip(RoundedCornerShape(8.dp)).background(skeletonColor))
                Box(Modifier.size(100.dp).height(32.dp).clip(RoundedCornerShape(8.dp)).background(skeletonColor))
            }
            Box(
                modifier = Modifier
                    .size(96.dp)
                    .align(Alignment.CenterEnd)
                    .clip(RoundedCornerShape(12.dp))
                    .background(skeletonColor)
            )
        }
    }
}
