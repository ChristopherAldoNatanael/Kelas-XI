package com.christopheraldoo.petheal.ui.screens.auth

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowForward
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage

// ── Brand colors ────────────────────────────────────────────────────────────
private val OnboardingPrimary   = Color(0xFF2BEE6C)
private val OnboardingBgDark    = Color(0xFF102216)
private val OnboardingBgLight   = Color(0xFFF6F8F6)

// ── Page data ────────────────────────────────────────────────────────────────
private data class OnboardingPage(
    val imageUrl: String,
    val imageDesc: String,
    val title: String,          // plain part before the highlight
    val titleHighlight: String, // colored part
    val titleSuffix: String,    // plain part after the highlight (can be empty)
    val subtitle: String
)

private val pages = listOf(
    OnboardingPage(
        imageUrl = "https://lh3.googleusercontent.com/aida-public/AB6AXuCO9tiyZINtsRBmaXrw320CGl_BPTG3xLD6dAhCJ1JI1VNfHPRyEqkb2TAYIo2ShT2ooY2UDv67hxFkTXhLvHmDF2s9s6a3f4YKTLAq0rmPacxlv7oGUU4nY7q-SHuEVM7QY8YPviuE8GmlwzAEuiMGIx7yYHlkIcMLsa4KPEoPSHKAWA_uKTI8Oalj3m_50lsnnEZckvC64PBjpgrkEsl9TzgFFXZaS4AyYyOA402CO4fmfAOx2L7eLA3C0xpXaWX1dNsyG0oxC6E",
        imageDesc = "Happy veterinarian hugging a golden retriever dog",
        title = "Welcome to ",
        titleHighlight = "PetHeal",
        titleSuffix = "",
        subtitle = "Your pet's health, our top priority. Connect with the best veterinarians instantly."
    ),
    OnboardingPage(
        imageUrl = "https://lh3.googleusercontent.com/aida-public/AB6AXuBxjbgqe_leDZBLJ94dVMRNXKs1mVixnPqAGEZQoIy4PXQaXGx8TFxxMEjgLFT4qS-3eVP1hGHxjU4ZEHrqoUvCLCX4j6i32F7aFIQ6yiGlVgn3WmVhOmVHKaFGFh0BV1MMtBNiFqYNjC0XmF1sRJUkHDpFCxuatmfVDTzJCGGFb7VbpWGPQQY5u62Ik_IqJYRKXiAx_sQX2vlMOAFbSzN7iqGNwl3XSxPVl2NmSPqHEqpnBn4E0s9c5u4WFIK-BrRV3KFNMwWQ",
        imageDesc = "Veterinarian examining a cat on a table",
        title = "Book a ",
        titleHighlight = "Vet Visit",
        titleSuffix = " Easily",
        subtitle = "Schedule appointments with certified vets in just a few taps. No waiting rooms."
    ),
    OnboardingPage(
        imageUrl = "https://lh3.googleusercontent.com/aida-public/AB6AXuBrq5x-Nj6hIJp-2FUc8Y0tLqHkfYjDI4qKkzl_T5BbwCpZJIAqHwQ0yMzWqeRzKn9aARIV_Ydl_VjKX6qOKBg6bJZ1VR9MZRh0b7j4tXLcfmjLiMcqf-xdIsBHpVdifTmLhpCzaVpFWBiqf2QvyHDJGj9JHBcOjCbFiJt-RNqBMiWiUQrBZCF67NG6mjRoGblrLlbDXqLUFdB4vFIL2DP_fFSvIefhMOHifYQsV9p6gWygjqiF2U8PeAl",
        imageDesc = "Person using a phone with a pet health app",
        title = "Track Your Pet's ",
        titleHighlight = "Health",
        titleSuffix = " Record",
        subtitle = "Keep all medical records, vaccinations, and prescriptions organized in one place."
    )
)

@Composable
fun OnboardingScreen(
    onNavigateToLogin: () -> Unit,
    viewModel: OnboardingViewModel = hiltViewModel()
) {
    var currentPage by remember { mutableIntStateOf(0) }
    val isDark = androidx.compose.foundation.isSystemInDarkTheme()
    val bgColor = if (isDark) OnboardingBgDark else OnboardingBgLight

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(bgColor)
    ) {
        Column(
            modifier = Modifier.fillMaxSize()
        ) {
            // ── Header ───────────────────────────────────────────────────
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp)
                    .padding(top = 16.dp, bottom = 8.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Spacer(modifier = Modifier.width(48.dp))
                Spacer(modifier = Modifier.weight(1f))
                TextButton(
                    onClick = {
                        viewModel.markOnboardingSeen()
                        onNavigateToLogin()
                    },
                    modifier = Modifier.width(48.dp)
                ) {
                    Text(
                        text = "Skip",
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        color = if (isDark) Color(0xFFD1FAE5) else Color(0xFF64748B)
                    )
                }
            }

            // ── Animated page content ────────────────────────────────────
            AnimatedContent(
                targetState = currentPage,
                transitionSpec = {
                    (slideInHorizontally(tween(350)) { it } + fadeIn(tween(350))) togetherWith
                    (slideOutHorizontally(tween(350)) { -it } + fadeOut(tween(350)))
                },
                label = "onboarding_page",
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth()
            ) { page ->
                OnboardingPageContent(
                    pageData = pages[page],
                    isDark = isDark,
                    bgColor = bgColor
                )
            }

            // ── Footer: dots + button ────────────────────────────────────
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 24.dp)
                    .padding(bottom = 40.dp, top = 16.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(32.dp)
            ) {
                // Pagination dots
                Row(
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    pages.indices.forEach { index ->
                        val isActive = index == currentPage
                        Box(
                            modifier = Modifier
                                .height(8.dp)
                                .width(if (isActive) 32.dp else 8.dp)
                                .clip(CircleShape)
                                .background(
                                    if (isActive) OnboardingPrimary
                                    else if (isDark) Color(0xFF334155) else Color(0xFFCBD5E1)
                                )
                        )
                    }
                }

                // Next / Get Started button
                Button(
                    onClick = {
                        if (currentPage < pages.lastIndex) {
                            currentPage++
                        } else {
                            viewModel.markOnboardingSeen()
                            onNavigateToLogin()
                        }
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(56.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = OnboardingPrimary,
                        contentColor = OnboardingBgDark
                    ),
                    elevation = ButtonDefaults.buttonElevation(defaultElevation = 4.dp)
                ) {
                    Text(
                        text = if (currentPage < pages.lastIndex) "Next" else "Get Started",
                        fontSize = 17.sp,
                        fontWeight = FontWeight.Bold
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Icon(
                        imageVector = Icons.Filled.ArrowForward,
                        contentDescription = null,
                        modifier = Modifier.size(20.dp)
                    )
                }
            }

            // Safe area spacer
            Spacer(modifier = Modifier.height(8.dp))
        }
    }
}

@Composable
private fun OnboardingPageContent(
    pageData: OnboardingPage,
    isDark: Boolean,
    bgColor: Color
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(horizontal = 24.dp)
            .padding(vertical = 16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        // ── Illustration ─────────────────────────────────────────────
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .aspectRatio(1f)
                .padding(bottom = 32.dp),
            contentAlignment = Alignment.Center
        ) {
            // Soft glowing background blob
            Box(
                modifier = Modifier
                    .fillMaxSize(0.9f)
                    .clip(CircleShape)
                    .background(OnboardingPrimary.copy(alpha = if (isDark) 0.05f else 0.10f))
                    .graphicsLayer { renderEffect = null }
            )

            // Image with rounded corners + gradient overlay
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .clip(RoundedCornerShape(16.dp))
            ) {
                AsyncImage(
                    model = pageData.imageUrl,
                    contentDescription = pageData.imageDesc,
                    contentScale = ContentScale.Crop,
                    modifier = Modifier.fillMaxSize()
                )
                // Subtle bottom gradient to blend into background
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(
                            Brush.verticalGradient(
                                colorStops = arrayOf(
                                    0.6f to Color.Transparent,
                                    1.0f to bgColor.copy(alpha = 0.20f)
                                )
                            )
                        )
                )
            }
        }

        // ── Text content ──────────────────────────────────────────────
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(12.dp),
            modifier = Modifier.widthIn(max = 320.dp)
        ) {
            Text(
                text = buildAnnotatedString {
                    append(pageData.title)
                    withStyle(SpanStyle(color = OnboardingPrimary)) {
                        append(pageData.titleHighlight)
                    }
                    append(pageData.titleSuffix)
                },
                fontSize = 32.sp,
                fontWeight = FontWeight.ExtraBold,
                color = if (isDark) Color(0xFFF8FAFC) else Color(0xFF0F172A),
                textAlign = TextAlign.Center,
                lineHeight = 38.sp
            )

            Text(
                text = pageData.subtitle,
                fontSize = 15.sp,
                fontWeight = FontWeight.Medium,
                color = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B),
                textAlign = TextAlign.Center,
                lineHeight = 22.sp
            )
        }
    }
}
