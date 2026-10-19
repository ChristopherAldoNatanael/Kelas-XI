package com.christopheraldoo.petheal.ui.screens.auth

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.animation.togetherWith
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearOutSlowInEasing
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.safeDrawingPadding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.Refresh
import androidx.compose.material.icons.rounded.WifiOff
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.BuildConfig
import com.christopheraldoo.petheal.R
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.PrimaryDark
import com.christopheraldoo.petheal.ui.theme.Tertiary
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary

@Composable
fun SplashScreen(
    onNavigateToLogin: () -> Unit,
    onNavigateToHome: () -> Unit,
    onNavigateToOnboarding: () -> Unit,
    viewModel: SplashViewModel = hiltViewModel()
) {
    val isLoggedIn by viewModel.isLoggedIn.collectAsState(initial = null)
    val hasSeenOnboarding by viewModel.hasSeenOnboarding.collectAsState(initial = null)
    val uiState by viewModel.state.collectAsState()

    val brandName = stringResource(R.string.app_name)

    val progress by animateFloatAsState(
        targetValue = uiState.progress,
        animationSpec = tween(durationMillis = 420, easing = FastOutSlowInEasing),
        label = "splash_progress"
    )

    // Gate navigation on the real start-up state. DataStore emits async, so
    // reading with an `initial = false` default could misroute (e.g. a
    // logged-in user briefly flashing through onboarding) on slow I/O.
    LaunchedEffect(uiState.isReady, isLoggedIn, hasSeenOnboarding) {
        if (!uiState.isReady) return@LaunchedEffect
        val loggedIn = isLoggedIn ?: return@LaunchedEffect
        val seenOnboarding = hasSeenOnboarding ?: return@LaunchedEffect
        when {
            loggedIn -> onNavigateToHome()
            !seenOnboarding -> onNavigateToOnboarding()
            else -> onNavigateToLogin()
        }
    }

    // Single, restrained entrance: the brand settles into place, the rest fades.
    var appeared by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) { appeared = true }
    val brandScale by animateFloatAsState(
        targetValue = if (appeared) 1f else 0.9f,
        animationSpec = tween(durationMillis = 640, easing = FastOutSlowInEasing),
        label = "brand_scale"
    )
    val brandAlpha by animateFloatAsState(
        targetValue = if (appeared) 1f else 0f,
        animationSpec = tween(durationMillis = 620, easing = LinearOutSlowInEasing),
        label = "brand_alpha"
    )
    val brandLift by animateDpAsState(
        targetValue = if (appeared) 0.dp else 10.dp,
        animationSpec = tween(durationMillis = 640, easing = FastOutSlowInEasing),
        label = "brand_lift"
    )
    val footerAlpha by animateFloatAsState(
        targetValue = if (appeared) 1f else 0f,
        animationSpec = tween(durationMillis = 700, delayMillis = 160, easing = LinearOutSlowInEasing),
        label = "footer_alpha"
    )

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
    ) {
        // A single soft brand light behind the mark — no rainbow gradients.
        Box(
            modifier = Modifier
                .align(Alignment.TopCenter)
                .offset(y = (-150).dp)
                .size(440.dp)
                .background(
                    Brush.radialGradient(
                        colors = listOf(Primary.copy(alpha = 0.16f), Color.Transparent)
                    )
                )
        )

        Column(
            modifier = Modifier
                .fillMaxSize()
                .safeDrawingPadding(),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Spacer(modifier = Modifier.weight(1f))

            BrandMark(
                brandName = brandName,
                lift = brandLift,
                modifier = Modifier.graphicsLayer {
                    scaleX = brandScale
                    scaleY = brandScale
                    alpha = brandAlpha
                }
            )

            Spacer(modifier = Modifier.weight(1f))

            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 30.dp)
                    .padding(bottom = 36.dp)
                    .graphicsLayer { alpha = footerAlpha },
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                AnimatedVisibility(
                    visible = uiState.isOffline,
                    enter = fadeIn(tween(240)) + expandVertically(tween(240)),
                    exit = fadeOut(tween(180)) + shrinkVertically(tween(200))
                ) {
                    OfflineNotice(
                        onRetry = viewModel::retryConnection,
                        onContinue = viewModel::continueWithoutConnection
                    )
                }

                Spacer(modifier = Modifier.height(if (uiState.isOffline) 22.dp else 0.dp))

                ProgressTrack(progress = progress, muted = uiState.isOffline)

                Spacer(modifier = Modifier.height(12.dp))

                AnimatedContent(
                    targetState = uiState.stage,
                    transitionSpec = {
                        fadeIn(tween(220)) togetherWith fadeOut(tween(150))
                    },
                    contentAlignment = Alignment.Center,
                    label = "splash_status",
                    modifier = Modifier.fillMaxWidth()
                ) { stage ->
                    Text(
                        text = statusLabel(stage),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        letterSpacing = 0.4.sp,
                        color = if (uiState.isOffline) Tertiary else TextSecondary,
                        textAlign = TextAlign.Center,
                        modifier = Modifier.fillMaxWidth()
                    )
                }

                Spacer(modifier = Modifier.height(18.dp))

                Text(
                    text = stringResource(R.string.splash_version, BuildConfig.VERSION_NAME),
                    fontSize = 11.sp,
                    color = TextTertiary,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth()
                )
            }
        }
    }
}

@Composable
private fun BrandMark(
    brandName: String,
    lift: Dp,
    modifier: Modifier = Modifier
) {
    Column(
        // -24.dp optically centres the block against the footer; `lift` is the
        // entrance offset that settles to 0.
        modifier = modifier.offset(y = (-24).dp + lift),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Surface(
            modifier = Modifier.size(104.dp),
            shape = RoundedCornerShape(28.dp),
            color = MaterialTheme.colorScheme.surface,
            border = BorderStroke(1.dp, Outline),
            shadowElevation = 10.dp
        ) {
            Box(
                modifier = Modifier.fillMaxSize(),
                contentAlignment = Alignment.Center
            ) {
                // Fixed app identity: drawable/logo_android.png (rendered from
                // android-logo.jpeg). The source ships the mark on a white canvas
                // with a baked-in card shadow, so it is scaled up ~1.5x inside the
                // clipped tile — the mark fills the tile and the shadow ring is
                // pushed outside the clip. Tenant branding stays in-app only.
                Image(
                    painter = painterResource(id = R.drawable.logo_android),
                    contentDescription = brandName,
                    contentScale = ContentScale.Fit,
                    modifier = Modifier.size(158.dp)
                )
            }
        }

        Spacer(modifier = Modifier.height(26.dp))

        Text(
            text = brandName,
            fontSize = 30.sp,
            fontWeight = FontWeight.ExtraBold,
            letterSpacing = (-0.6).sp,
            color = OnBackground,
            textAlign = TextAlign.Center,
            maxLines = 1,
            modifier = Modifier.widthIn(max = 300.dp)
        )

        Spacer(modifier = Modifier.height(8.dp))

        Text(
            text = stringResource(R.string.splash_tagline),
            fontSize = 14.sp,
            fontWeight = FontWeight.Medium,
            lineHeight = 20.sp,
            color = TextSecondary,
            textAlign = TextAlign.Center,
            modifier = Modifier.widthIn(max = 268.dp)
        )
    }
}

@Composable
private fun ProgressTrack(progress: Float, muted: Boolean) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(4.dp)
            .clip(RoundedCornerShape(50))
            .background(Outline)
    ) {
        Box(
            modifier = Modifier
                .fillMaxHeight()
                .fillMaxWidth(progress)
                .clip(RoundedCornerShape(50))
                .background(
                    Brush.horizontalGradient(
                        colors = if (muted) {
                            listOf(Tertiary.copy(alpha = 0.9f), Tertiary)
                        } else {
                            listOf(Primary, PrimaryDark)
                        }
                    )
                )
        )
    }
}

@Composable
private fun OfflineNotice(
    onRetry: () -> Unit,
    onContinue: () -> Unit
) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(18.dp),
        color = MaterialTheme.colorScheme.surface,
        border = BorderStroke(1.dp, Outline),
        shadowElevation = 2.dp
    ) {
        Column(modifier = Modifier.padding(horizontal = 20.dp, vertical = 18.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    modifier = Modifier
                        .size(40.dp)
                        .clip(CircleShape)
                        .background(Tertiary.copy(alpha = 0.14f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Rounded.WifiOff,
                        contentDescription = null,
                        tint = Tertiary,
                        modifier = Modifier.size(20.dp)
                    )
                }

                Spacer(modifier = Modifier.width(14.dp))

                Column {
                    Text(
                        text = stringResource(R.string.splash_offline_title),
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        color = OnBackground
                    )
                    Spacer(modifier = Modifier.height(2.dp))
                    Text(
                        text = stringResource(R.string.splash_offline_message),
                        fontSize = 12.sp,
                        lineHeight = 17.sp,
                        color = TextSecondary
                    )
                }
            }

            Spacer(modifier = Modifier.height(16.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                OutlinedButton(
                    onClick = onContinue,
                    modifier = Modifier
                        .weight(1f)
                        .height(44.dp),
                    shape = RoundedCornerShape(12.dp),
                    border = BorderStroke(1.dp, Outline),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = TextSecondary)
                ) {
                    Text(
                        text = stringResource(R.string.splash_offline_continue),
                        fontSize = 13.sp,
                        fontWeight = FontWeight.SemiBold
                    )
                }

                Button(
                    onClick = onRetry,
                    modifier = Modifier
                        .weight(1f)
                        .height(44.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Primary,
                        contentColor = Color.White
                    )
                ) {
                    Icon(
                        imageVector = Icons.Rounded.Refresh,
                        contentDescription = null,
                        modifier = Modifier.size(16.dp)
                    )
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(
                        text = stringResource(R.string.splash_retry),
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }
        }
    }
}

@Composable
private fun statusLabel(stage: SplashStage): String = when (stage) {
    SplashStage.PREPARING -> stringResource(R.string.splash_status_preparing)
    SplashStage.RESTORING_SESSION -> stringResource(R.string.splash_status_restoring)
    SplashStage.CHECKING_CONNECTION -> stringResource(R.string.splash_status_connecting)
    SplashStage.SYNCING -> stringResource(R.string.splash_status_syncing)
    SplashStage.WAITING_FOR_NETWORK -> stringResource(R.string.splash_status_offline)
    SplashStage.READY -> stringResource(R.string.splash_status_ready)
}
