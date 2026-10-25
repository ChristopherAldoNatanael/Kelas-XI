package com.christopheraldoo.petheal.ui.navigation

import android.util.Log
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.christopheraldoo.petheal.ui.screens.auth.CompleteSetupScreen
import com.christopheraldoo.petheal.ui.screens.auth.LoginScreen
import com.christopheraldoo.petheal.ui.screens.auth.OnboardingScreen
import com.christopheraldoo.petheal.ui.screens.auth.OtpVerificationScreen
import com.christopheraldoo.petheal.ui.screens.auth.RegisterScreen
import com.christopheraldoo.petheal.ui.screens.auth.SplashScreen
import com.christopheraldoo.petheal.ui.screens.booking.BookingDetailScreen
import com.christopheraldoo.petheal.ui.screens.booking.BookingsScreen
import com.christopheraldoo.petheal.ui.screens.booking.BookingStartScreen
import com.christopheraldoo.petheal.ui.screens.booking.CreateBookingScreen
import com.christopheraldoo.petheal.ui.screens.doctor.DoctorDetailScreen
import com.christopheraldoo.petheal.ui.screens.doctor.DoctorsScreen
import com.christopheraldoo.petheal.ui.screens.home.HomeScreen
import com.christopheraldoo.petheal.ui.screens.medicalrecord.MedicalRecordDetailScreen
import com.christopheraldoo.petheal.ui.screens.medicalrecord.MedicalRecordsScreen
import com.christopheraldoo.petheal.ui.screens.pet.AddPetScreen
import com.christopheraldoo.petheal.ui.screens.pet.EditPetScreen
import com.christopheraldoo.petheal.ui.screens.pet.PetDetailScreen
import com.christopheraldoo.petheal.ui.screens.pet.PetsScreen
import com.christopheraldoo.petheal.ui.screens.notification.NotificationsScreen
import com.christopheraldoo.petheal.ui.screens.profile.EditProfileScreen
import com.christopheraldoo.petheal.ui.screens.profile.ProfileScreen
import com.christopheraldoo.petheal.ui.screens.settings.AboutScreen
import com.christopheraldoo.petheal.ui.screens.settings.HelpSupportScreen
import com.christopheraldoo.petheal.ui.screens.settings.PrivacySecurityScreen
import com.christopheraldoo.petheal.ui.screens.payment.PaymentScreen
import com.christopheraldoo.petheal.ui.screens.payment.PaymentResultScreen
import com.christopheraldoo.petheal.R
import com.christopheraldoo.petheal.ui.components.ErrorState
import com.christopheraldoo.petheal.ui.components.navigateToTab
import com.christopheraldoo.petheal.ui.components.route
import androidx.compose.ui.res.stringResource

@Composable
fun PetHealNavHost(
    navController: NavHostController = rememberNavController(),
    viewModel: NavigationViewModel = hiltViewModel()
) {
    // PHASE 7 (§21): a 401 on any authenticated call ends the session
    // exactly once — return to login without loops or crashes.
    LaunchedEffect(Unit) {
        viewModel.sessionEvents.collect { event ->
            if (event === com.christopheraldoo.petheal.data.local.SessionEvents.SessionExpired) {
                android.util.Log.i("PetHealNavHost", "Session expired (401) — navigating to login")
                navController.navigate(Screen.Login.route) {
                    popUpTo(0) { inclusive = true }
                }
            }
        }
    }

    NavHost(
        navController = navController,
        startDestination = Screen.Splash.route
    ) {
        // PHASE 9: unified tab switching — singleTop + popUpTo(Home) keeps one
        // tab entry on the stack (no stacking) and clears sub-screens above it.
        // saveState/restoreState membuat filter, pencarian, dan posisi scroll
        // tiap tab TETAP ada saat berpindah tab (tidak reset / fetch ulang).
        // Mekanisme tab kini hidup di satu tempat (navigateToTab) supaya
        // entry point non-tab (tombol di detail, hasil bayar, notifikasi)
        // tidak bisa lagi diam-diam menumpuk entri tab kedua.
        val navigateTab: (com.christopheraldoo.petheal.ui.components.PetHealTab) -> Unit = { tab ->
            navController.navigateToTab(tab)
        }
        // Splash Screen
        composable(Screen.Splash.route) {
            SplashScreen(
                onNavigateToLogin = {
                    navController.navigate(Screen.Login.route) {
                        popUpTo(Screen.Splash.route) { inclusive = true }
                    }
                },
                onNavigateToHome = {
                    navController.navigate(Screen.Home.route) {
                        popUpTo(Screen.Splash.route) { inclusive = true }
                    }
                },
                onNavigateToOnboarding = {
                    navController.navigate(Screen.Onboarding.route) {
                        popUpTo(Screen.Splash.route) { inclusive = true }
                    }
                }
            )
        }

        // Onboarding Screen
        composable(Screen.Onboarding.route) {
            OnboardingScreen(
                onNavigateToLogin = {
                    navController.navigate(Screen.Login.route) {
                        popUpTo(Screen.Onboarding.route) { inclusive = true }
                    }
                }
            )
        }

        // Main Screens
        composable(Screen.Login.route) {
            LoginScreen(
                onLoginSuccess = {
                    navController.navigate(Screen.Home.route) {
                        popUpTo(Screen.Login.route) { inclusive = true }
                    }
                },
                onNavigateToCompleteSetup = {
                    navController.navigate(Screen.CompleteSetup.route) {
                        popUpTo(Screen.Login.route) { inclusive = true }
                    }
                },
                onNavigateToRegister = {
                    navController.navigate(Screen.Register.route)
                }
            )
        }

        composable(Screen.Register.route) {
            RegisterScreen(
                onRegisterSuccess = {
                    navController.navigate(Screen.Home.route) {
                        popUpTo(Screen.Login.route) { inclusive = true }
                    }
                },
                onNavigateToCompleteSetup = {
                    navController.navigate(Screen.CompleteSetup.route) {
                        popUpTo(Screen.Register.route) { inclusive = true }
                    }
                },
                onNavigateToOtpVerification = { email ->
                    navController.navigate(Screen.VerifyOtp.createRoute(email))
                },
                onNavigateBack = {
                    navController.popBackStack()
                }
            )
        }

        // Verifikasi kode OTP pendaftaran email (akun dibuat di layar ini).
        composable(
            route = Screen.VerifyOtp.route,
            arguments = listOf(navArgument("email") { type = NavType.StringType })
        ) { entry ->
            OtpVerificationScreen(
                email = entry.arguments?.getString("email").orEmpty(),
                onVerified = {
                    navController.navigate(Screen.Home.route) {
                        popUpTo(Screen.Login.route) { inclusive = true }
                    }
                },
                onNavigateToCompleteSetup = {
                    navController.navigate(Screen.CompleteSetup.route) {
                        popUpTo(Screen.Register.route) { inclusive = true }
                    }
                },
                onNavigateBack = {
                    navController.popBackStack()
                }
            )
        }

        // PHASE 7: clinic-less accounts finish binding here, then Home.
        composable(Screen.CompleteSetup.route) {
            CompleteSetupScreen(
                onSetupComplete = {
                    navController.navigate(Screen.Home.route) {
                        popUpTo(0) { inclusive = true }
                    }
                },
                onLoggedOut = {
                    navController.navigate(Screen.Login.route) {
                        popUpTo(0) { inclusive = true }
                    }
                }
            )
        }

        // Main Screens
        composable(Screen.Home.route) {
            HomeScreen(
                onNavigateToPets = { navigateTab(com.christopheraldoo.petheal.ui.components.PetHealTab.Pets) },
                onNavigateToDoctors = { navController.navigate(Screen.Doctors.route) },
                onNavigateToBookingStart = { navController.navigate(Screen.BookingStart.route) },
                onNavigateToDoctor = { doctorId, autoReview ->
                    navController.navigate(Screen.DoctorDetail.createRoute(doctorId, autoReview))
                },
                onNavigateToBookings = { navigateTab(com.christopheraldoo.petheal.ui.components.PetHealTab.Bookings) },
                onNavigateToBookingDetail = { bookingId ->
                    navController.navigate(Screen.BookingDetail.createRoute(bookingId))
                },
                onNavigateToMedicalRecords = { navigateTab(com.christopheraldoo.petheal.ui.components.PetHealTab.Records) },
                onNavigateToProfile = { navigateTab(com.christopheraldoo.petheal.ui.components.PetHealTab.Profile) },
                onNavigateToNotifications = { navController.navigate(Screen.Notifications.route) },
                onTabSelected = navigateTab
            )
        }

        // Pet Screens
        composable(Screen.Pets.route) {
            PetsScreen(
                onNavigateBack = { navController.popBackStack() },
                onNavigateToPetDetail = { petId ->
                    navController.navigate(Screen.PetDetail.createRoute(petId))
                },
                onNavigateToAddPet = { navController.navigate(Screen.AddPet.route) },
                onTabSelected = navigateTab
            )
        }

        // "pets/add" must be before "pets/{petId}"
        composable(Screen.AddPet.route) {
            AddPetScreen(
                onNavigateBack = { navController.popBackStack() },
                onPetAdded = { navController.popBackStack() }
            )
        }

        composable(
            route = Screen.EditPet.route,
            arguments = listOf(navArgument("petId") { type = NavType.IntType })
        ) { backStackEntry ->
            val petId = backStackEntry.arguments?.getInt("petId") ?: return@composable
            EditPetScreen(
                petId = petId,
                onNavigateBack = { navController.popBackStack() },
                onPetUpdated = { navController.popBackStack() }
            )
        }

        composable(
            route = Screen.PetDetail.route,
            arguments = listOf(navArgument("petId") { type = NavType.IntType })
        ) { backStackEntry ->
            val petId = backStackEntry.arguments?.getInt("petId") ?: return@composable
            PetDetailScreen(
                petId = petId,
                onNavigateBack = { navController.popBackStack() },
                onNavigateToEdit = { navController.navigate(Screen.EditPet.createRoute(petId)) },
                // Repro user: tombol ini pernah membuka tab lewat navigate()
                // langsung sehingga tab "Rekam Medis" terduplikasi di atas
                // PetDetail dan tombol tab berikutnya mendarat tidak menentu.
                onNavigateToMedicalRecords = {
                    navigateTab(com.christopheraldoo.petheal.ui.components.PetHealTab.Records)
                }
            )
        }

        // Doctor Screens
        composable(Screen.Doctors.route) {
            DoctorsScreen(
                onNavigateBack = { navController.popBackStack() },
                onNavigateToDoctorDetail = { doctorId ->
                    navController.navigate(Screen.DoctorDetail.createRoute(doctorId))
                }
            )
        }

        composable(
            route = Screen.DoctorDetail.route,
            arguments = listOf(
                navArgument("doctorId") { type = NavType.IntType },
                navArgument("autoReview") {
                    type = NavType.BoolType
                    defaultValue = false
                }
            )
        ) { backStackEntry ->
            val doctorId = backStackEntry.arguments?.getInt("doctorId") ?: return@composable
            val autoReview = backStackEntry.arguments?.getBoolean("autoReview") ?: false
            DoctorDetailScreen(
                doctorId = doctorId,
                autoOpenReview = autoReview,
                onNavigateBack = { navController.popBackStack() }
            )
        }

        // Booking Screens
        composable(Screen.Bookings.route) {
            BookingsScreen(
                onNavigateBack = { navController.popBackStack() },
                onNavigateToDoctors = { navController.navigate(Screen.Doctors.route) },
                onNavigateToBookingStart = { navController.navigate(Screen.BookingStart.route) },
                onNavigateToBookingDetail = { bookingId ->
                    navController.navigate(Screen.BookingDetail.createRoute(bookingId))
                },
                onNavigateToPayment = { bookingId, isDp, totalAmount, isRemaining ->
                    navController.navigate(Screen.Payment.createRoute(bookingId, isDp, totalAmount, isRemaining))
                },
                onTabSelected = navigateTab
            )
        }

        // "bookings/start" + "bookings/create/{doctorId}/{petId}" must be before "bookings/{bookingId}"
        composable(Screen.BookingStart.route) {
            BookingStartScreen(
                onNavigateBack = { navController.popBackStack() },
                onNavigateToAddPet = { navController.navigate(Screen.AddPet.route) },
                onContinue = { doctorId, petId ->
                    navController.navigate(Screen.CreateBooking.createRoute(doctorId, petId))
                }
            )
        }

        composable(
            route = Screen.CreateBooking.route,
            arguments = listOf(
                navArgument("doctorId") { type = NavType.IntType },
                navArgument("petId") { type = NavType.IntType }
            )
        ) { backStackEntry ->
            val doctorId = backStackEntry.arguments?.getInt("doctorId") ?: return@composable
            val petId = backStackEntry.arguments?.getInt("petId") ?: return@composable
            CreateBookingScreen(
                doctorId = doctorId,
                petId = petId,
                onNavigateBack = { navController.popBackStack() },
                onBookingCreated = { bookingId, isDp, amount, methodsCsv ->
                    // Navigate to payment screen with correct payment type and amount
                    navController.navigate(Screen.Payment.createRoute(bookingId, isDp, amount, methodsCsv = methodsCsv)) {
                        popUpTo(Screen.CreateBooking.route) { inclusive = true }
                    }
                }
            )
        }

        composable(
            route = Screen.BookingDetail.route,
            arguments = listOf(navArgument("bookingId") { type = NavType.IntType })
        ) { backStackEntry ->
            val bookingId = backStackEntry.arguments?.getInt("bookingId") ?: return@composable
            BookingDetailScreen(
                bookingId = bookingId,
                onNavigateBack = { navController.popBackStack() },
                onNavigateToPayment = { payBookingId, isDp, amount, isRemaining ->
                    navController.navigate(Screen.Payment.createRoute(payBookingId, isDp, amount, isRemaining))
                },
                onNavigateToMedicalRecord = { recordId ->
                    navController.navigate(Screen.MedicalRecordDetail.createRoute(recordId))
                },
                onNavigateToMedicalExtraPayment = { recordId, payBookingId, amount ->
                    navController.navigate(Screen.MedicalExtraPayment.createRoute(payBookingId, recordId, amount))
                }
            )
        }

        // Medical Record Screens
        composable(Screen.MedicalRecords.route) {
            MedicalRecordsScreen(
                onNavigateBack = { navController.popBackStack() },
                onNavigateToRecordDetail = { recordId ->
                    navController.navigate(Screen.MedicalRecordDetail.createRoute(recordId))
                },
                onTabSelected = navigateTab
            )
        }

        composable(
            route = Screen.MedicalRecordDetail.route,
            arguments = listOf(navArgument("recordId") { type = NavType.IntType })
        ) { backStackEntry ->
            val recordId = backStackEntry.arguments?.getInt("recordId") ?: return@composable
            MedicalRecordDetailScreen(
                recordId = recordId,
                onNavigateBack = { navController.popBackStack() },
                onNavigateToExtraPayment = { bookingId, payRecordId, amount ->
                    navController.navigate(Screen.MedicalExtraPayment.createRoute(bookingId, payRecordId, amount))
                },
                onNavigateToDoctor = { doctorId ->
                    navController.navigate(Screen.DoctorDetail.createRoute(doctorId, true))
                }
            )
        }

        // Profile Screens
        composable(Screen.Profile.route) {
            ProfileScreen(
                onNavigateBack = { navController.popBackStack() },
                onNavigateToEdit = { navController.navigate(Screen.EditProfile.route) },
                onLogout = {
                    navController.navigate(Screen.Login.route) {
                        popUpTo(0) { inclusive = true }
                    }
                },
                onNavigateToNotifications = { navController.navigate(Screen.Notifications.route) },
                onNavigateToPrivacy = { navController.navigate(Screen.PrivacySecurity.route) },
                onNavigateToHelp = { navController.navigate(Screen.HelpSupport.route) },
                onNavigateToAbout = { navController.navigate(Screen.About.route) },
                onTabSelected = navigateTab
            )
        }

        composable(Screen.EditProfile.route) {
            EditProfileScreen(
                onNavigateBack = { navController.popBackStack() },
                onProfileUpdated = { navController.popBackStack() }
            )
        }

        // Notifications Screen
        composable(Screen.Notifications.route) {
            NotificationsScreen(
                onNavigateBack = { navController.popBackStack() },
                onOpenBooking = { bookingId -> navController.navigate(Screen.BookingDetail.createRoute(bookingId)) },
                onOpenMedicalRecord = { recordId -> navController.navigate(Screen.MedicalRecordDetail.createRoute(recordId)) },
                onOpenPet = { petId -> navController.navigate(Screen.PetDetail.createRoute(petId)) },
                onOpenDoctor = { doctorId -> navController.navigate(Screen.DoctorDetail.createRoute(doctorId)) },
                // Kelas bug yang sama dengan PetDetail: buka tab dari
                // sub-halaman wajib lewat mekanisme tab, bukan navigate().
                onOpenBookings = { navigateTab(com.christopheraldoo.petheal.ui.components.PetHealTab.Bookings) },
                onOpenPets = { navigateTab(com.christopheraldoo.petheal.ui.components.PetHealTab.Pets) }
            )
        }

        // Settings Screens
        composable(Screen.PrivacySecurity.route) {
            PrivacySecurityScreen(
                onNavigateBack = { navController.popBackStack() }
            )
        }

        composable(Screen.HelpSupport.route) {
            HelpSupportScreen(
                onNavigateBack = { navController.popBackStack() }
            )
        }

        composable(Screen.About.route) {
            AboutScreen(
                onNavigateBack = { navController.popBackStack() }
            )
        }

        // Payment Screen
        composable(
            route = Screen.MedicalExtraPayment.route,
            arguments = listOf(
                navArgument("bookingId") { type = NavType.IntType },
                navArgument("recordId") { type = NavType.IntType },
                navArgument("amount") { type = NavType.FloatType }
            )
        ) { backStackEntry ->
            val bookingId = backStackEntry.arguments?.getInt("bookingId") ?: return@composable
            val recordId = backStackEntry.arguments?.getInt("recordId") ?: return@composable
            val amount = backStackEntry.arguments?.getFloat("amount")?.toDouble() ?: 0.0

            val paymentNavViewModel = hiltViewModel<PaymentNavViewModel>()
            val paymentNavState by paymentNavViewModel.state.collectAsState()

            LaunchedEffect(bookingId) {
                paymentNavViewModel.loadBookingAndUser(bookingId)
            }

            when {
                paymentNavState.isLoading -> {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator(color = com.christopheraldoo.petheal.ui.theme.Primary)
                    }
                }
                paymentNavState.error != null -> {
                    // Jalur error standar app: bukan teks mentah, tapi komponen
                    // bersama + copy dari strings.xml.
                    ErrorState(
                        title = stringResource(R.string.error_payment_load_title),
                        message = stringResource(R.string.error_payment_load_message),
                        primaryActionText = stringResource(R.string.action_retry),
                        onPrimaryActionClick = { paymentNavViewModel.loadBookingAndUser(bookingId) },
                        secondaryActionText = stringResource(R.string.action_back),
                        onSecondaryActionClick = { navController.popBackStack() }
                    )
                }
                paymentNavState.booking != null -> {
                    val readyBooking = paymentNavState.booking
                    if (readyBooking != null) {
                    PaymentScreen(
                        booking = readyBooking,
                        user = paymentNavState.user,
                        isDpPayment = false,
                        totalAmount = amount,
                        isRemainingPayment = false,
                        medicalRecordId = recordId,
                        onPaymentSuccess = { orderId ->
                            navController.navigate(Screen.PaymentResult.createRoute(orderId, "success", "Pembayaran tambahan rekam medis berhasil!")) {
                                // popUpTo butuh POLA route destination, bukan
                                // route terisi — string terisi tidak match dan
                                // diam-diam tidak mem-pop apa pun.
                                popUpTo(Screen.MedicalExtraPayment.route) { inclusive = true }
                            }
                        },
                        onPaymentPending = { orderId ->
                            navController.navigate(Screen.PaymentResult.createRoute(orderId, "pending", "Pembayaran tambahan menunggu penyelesaian.")) {
                                // popUpTo butuh POLA route destination, bukan
                                // route terisi — string terisi tidak match dan
                                // diam-diam tidak mem-pop apa pun.
                                popUpTo(Screen.MedicalExtraPayment.route) { inclusive = true }
                            }
                        },
                        onPaymentFailed = { errorMsg ->
                            navController.navigate(Screen.PaymentResult.createRoute("medical-$recordId", "failed", errorMsg)) {
                                // popUpTo butuh POLA route destination, bukan
                                // route terisi — string terisi tidak match dan
                                // diam-diam tidak mem-pop apa pun.
                                popUpTo(Screen.MedicalExtraPayment.route) { inclusive = true }
                            }
                        },
                        onNavigateBack = { navController.popBackStack() },
                        onBookingUpdated = { paymentNavViewModel.notifyMedicalUpdated() }
                    )
                    }
                }
            }
        }

        composable(
            route = Screen.Payment.route,
            arguments = listOf(
                navArgument("bookingId") { type = NavType.IntType },
                navArgument("isDp") { type = NavType.BoolType },
                navArgument("totalAmount") { type = NavType.FloatType },
                navArgument("isRemaining") { 
                    type = NavType.BoolType
                    defaultValue = false
                },
                navArgument("methods") {
                    type = NavType.StringType
                    defaultValue = ""
                }
            )
        ) { backStackEntry ->
            val bookingId = backStackEntry.arguments?.getInt("bookingId") ?: return@composable
            val isDp = backStackEntry.arguments?.getBoolean("isDp") ?: false
            val totalAmount = backStackEntry.arguments?.getFloat("totalAmount")?.toDouble() ?: 0.0
            val isRemaining = backStackEntry.arguments?.getBoolean("isRemaining") ?: false
            // Kode enabled_payments Midtrans dari pilihan di Buat Booking
            // ("" = daftar lengkap default). Tidak mengubah alur lain.
            val methodsCsv = backStackEntry.arguments?.getString("methods").orEmpty()
            val enabledPayments = methodsCsv.split(",")
                .map { it.trim() }
                .filter { it.isNotEmpty() }
                .ifEmpty { null }

            val paymentNavViewModel = hiltViewModel<PaymentNavViewModel>()
            val paymentNavState by paymentNavViewModel.state.collectAsState()

            LaunchedEffect(bookingId) {
                paymentNavViewModel.loadBookingAndUser(bookingId)
            }

            when {
                paymentNavState.isLoading -> {
                    Box(
                        Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center
                    ) {
                        CircularProgressIndicator(
                            color = com.christopheraldoo.petheal.ui.theme.Primary
                        )
                    }
                }
                paymentNavState.error != null -> {
                    // Sama seperti MedicalExtraPayment: satu jalur error bersama.
                    ErrorState(
                        title = stringResource(R.string.error_payment_load_title),
                        message = stringResource(R.string.error_payment_load_message),
                        primaryActionText = stringResource(R.string.action_retry),
                        onPrimaryActionClick = { paymentNavViewModel.loadBookingAndUser(bookingId) },
                        secondaryActionText = stringResource(R.string.action_back),
                        onSecondaryActionClick = { navController.popBackStack() }
                    )
                }
                paymentNavState.booking != null -> {
                    val readyBooking = paymentNavState.booking
                    if (readyBooking != null) {
                    PaymentScreen(
                        booking = readyBooking,
                        user = paymentNavState.user,
                        isDpPayment = isDp,
                        totalAmount = totalAmount,
                        isRemainingPayment = isRemaining, // NEW: Pass isRemaining flag
                        enabledPayments = enabledPayments,
                        onPaymentSuccess = { orderId ->
                            navController.navigate(
                                Screen.PaymentResult.createRoute(orderId, "success", "Pembayaran berhasil!", bookingId)
                            ) {
                                // Pola route, bukan route terisi (lihat catatan
                                // popUpTo di PaymentScreen medical di atas).
                                popUpTo(Screen.Payment.route) { inclusive = true }
                            }
                        },
                        onPaymentPending = { orderId ->
                            navController.navigate(
                                Screen.PaymentResult.createRoute(orderId, "pending", "Pembayaran menunggu. Segera selesaikan.", bookingId)
                            ) {
                                // Pola route, bukan route terisi (lihat catatan
                                // popUpTo di PaymentScreen medical di atas).
                                popUpTo(Screen.Payment.route) { inclusive = true }
                            }
                        },
                        onPaymentFailed = { errorMsg ->
                            navController.navigate(
                                Screen.PaymentResult.createRoute("booking-$bookingId", "failed", errorMsg, bookingId)
                            ) {
                                // Pola route, bukan route terisi (lihat catatan
                                // popUpTo di PaymentScreen medical di atas).
                                popUpTo(Screen.Payment.route) { inclusive = true }
                            }
                        },
                        onNavigateBack = { navController.popBackStack() },
                        onBookingUpdated = {
                            Log.d("PaymentNav", "Booking updated, requesting bookings refresh")
                            paymentNavViewModel.notifyBookingUpdated()
                        }
                    )
                    }
                }
            }
        }

        // Payment Result Screen
        composable(
            route = Screen.PaymentResult.route,
            arguments = listOf(
                navArgument("orderId") { type = NavType.StringType },
                navArgument("status") { type = NavType.StringType },
                navArgument("message") { type = NavType.StringType },
                navArgument("bookingId") {
                    type = NavType.IntType
                    defaultValue = 0
                }
            )
        ) { backStackEntry ->
            val orderId = backStackEntry.arguments?.getString("orderId") ?: ""
            val status = backStackEntry.arguments?.getString("status") ?: "failed"
            val message = backStackEntry.arguments?.getString("message") ?: ""
            val bookingId = backStackEntry.arguments?.getInt("bookingId") ?: 0

            val paymentNavViewModel = hiltViewModel<PaymentNavViewModel>()
            val paymentNavState by paymentNavViewModel.state.collectAsState()

            LaunchedEffect(bookingId) {
                if (bookingId > 0) paymentNavViewModel.loadBookingAndUser(bookingId)
            }

            PaymentResultScreen(
                orderId = orderId,
                status = status,
                message = message,
                booking = paymentNavState.booking,
                clinicName = paymentNavState.clinicName,
                clinicAddress = paymentNavState.clinicAddress,
                clinicColor = paymentNavState.clinicColor,
                // navigateTab mem-pop PaymentResult (dan sub-layar di atas
                // Home) sekaligus mencegah entri Home/Bookings ganda —
                // sebelumnya kedua tombol ini mendorong tab baru di atas
                // Home lama, menghasilkan dua entri Home di back stack.
                onNavigateToBookings = {
                    navigateTab(com.christopheraldoo.petheal.ui.components.PetHealTab.Bookings)
                },
                onNavigateBack = {
                    navigateTab(com.christopheraldoo.petheal.ui.components.PetHealTab.Home)
                }
            )
        }
    }
}
