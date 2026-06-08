package com.christopheraldoo.petheal.ui.screens.auth

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Pets
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage

private val PrimaryGreen = Color(0xFF2BEE6C)
private val SplashBg = Color(0xFFF6F8F6)
private val SplashTextPrimary = Color(0xFF0F172A)
private val SplashTextSecondary = Color(0xFF64748B)

@Composable
fun SplashScreen(
    onNavigateToLogin: () -> Unit,
    onNavigateToHome: () -> Unit,
    onNavigateToOnboarding: () -> Unit,
    viewModel: SplashViewModel = hiltViewModel()
) {
    val isLoggedIn by viewModel.isLoggedIn.collectAsState(initial = false)
    val hasSeenOnboarding by viewModel.hasSeenOnboarding.collectAsState(initial = false)
    val rawProgress by viewModel.progress.collectAsState()

    val animatedProgress by animateFloatAsState(
        targetValue = rawProgress,
        animationSpec = tween(durationMillis = 300),
        label = "progress"
    )

    LaunchedEffect(rawProgress) {
        if (rawProgress >= 1f) {
            when {
                isLoggedIn -> onNavigateToHome()
                !hasSeenOnboarding -> onNavigateToOnboarding()
                else -> onNavigateToLogin()
            }
        }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(Color.White, SplashBg)
                )
            )
    ) {
        AsyncImage(
            model = "https://lh3.googleusercontent.com/aida-public/AB6AXuBVG1aED6MifuWEhP138rLf305Fkn3ZnOsj1nh1_4HKqq5QdH83XBa3RqtKhTbdLotjtm_yd0XMtjTkJgZTlLoiP56nLERNEll9qFtQKiXOUW8glUWB70LjuC2dYsjxgpez9PowLqcl5cLcSSK3wIM721K9D35VcttKQ4oK0m5bvS6JxpHZIuD6dZ4PNRBqxI5PGRC_ldfE4A_SxebNDTEhXiCEl4yrdXsKDS_SlGhpWgquINk_YPBpHNmpyjkcmqN5VH-C232OpHs",
            contentDescription = "Happy dogs running in park",
            contentScale = ContentScale.Crop,
            modifier = Modifier
                .fillMaxSize()
                .graphicsLayer { alpha = 0.16f }
        )

        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Brush.verticalGradient(
                        colorStops = arrayOf(
                            0.0f to Color.White.copy(alpha = 0.15f),
                            0.70f to SplashBg.copy(alpha = 0.35f),
                            1.0f to SplashBg
                        )
                    )
                )
        )

        Column(
            modifier = Modifier.fillMaxSize(),
            verticalArrangement = Arrangement.SpaceBetween
        ) {
            Column(
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth(),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.Center
            ) {
                Box(
                    modifier = Modifier
                        .size(104.dp)
                        .clip(CircleShape)
                        .background(PrimaryGreen.copy(alpha = 0.14f))
                        .border(
                            width = 1.dp,
                            color = PrimaryGreen.copy(alpha = 0.28f),
                            shape = CircleShape
                        ),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Filled.Pets,
                        contentDescription = "PetHeal logo",
                        tint = PrimaryGreen,
                        modifier = Modifier.size(56.dp)
                    )
                }

                Spacer(modifier = Modifier.height(24.dp))

                Text(
                    text = "PetHeal",
                    fontSize = 40.sp,
                    fontWeight = FontWeight.Bold,
                    color = SplashTextPrimary,
                    letterSpacing = (-1).sp,
                    textAlign = TextAlign.Center
                )

                Spacer(modifier = Modifier.height(8.dp))

                Text(
                    text = "Caring for your best friend",
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Medium,
                    color = SplashTextSecondary,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.widthIn(max = 280.dp)
                )
            }

            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 32.dp)
                    .padding(bottom = 48.dp),
                verticalArrangement = Arrangement.spacedBy(0.dp)
            ) {
                androidx.compose.foundation.layout.Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(bottom = 6.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.Bottom
                ) {
                    Text(
                        text = "LOADING",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = SplashTextSecondary,
                        letterSpacing = 1.5.sp
                    )
                    Text(
                        text = "${(animatedProgress * 100).toInt()}%",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryGreen
                    )
                }

                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(6.dp)
                        .clip(RoundedCornerShape(50))
                        .background(Color(0xFFE2E8F0))
                ) {
                    Box(
                        modifier = Modifier
                            .fillMaxHeight()
                            .fillMaxWidth(animatedProgress)
                            .clip(RoundedCornerShape(50))
                            .background(
                                Brush.horizontalGradient(
                                    colors = listOf(PrimaryGreen, PrimaryGreen)
                                )
                            )
                    )
                }

                Spacer(modifier = Modifier.height(20.dp))

                Text(
                    text = "v1.0.8 (c) 2024 PetHeal Inc.",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Normal,
                    color = SplashTextSecondary,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth()
                )
            }
        }
    }
}
