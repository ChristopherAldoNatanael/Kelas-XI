package com.christopheraldoo.petheal.ui.navigation

import android.util.Log
import androidx.compose.runtime.Composable
import com.christopheraldoo.petheal.ui.util.collectAsStateLifecycleAware
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
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
import androidx.navigation.NavOptionsBuilder
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.christopheraldoo.petheal.ui.screens.auth.CompleteSetupScreen
import com.christopheraldoo.petheal.ui.screens.discovery.ClinicDetailScreen
import com.christopheraldoo.petheal.ui.screens.discovery.ClinicDiscoveryScreen
import com.christopheraldoo.petheal.ui.screens.discovery.PriceCompareScreen
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
import com.christopheraldoo.petheal.ui.screens.servicerequest.ServiceRequestsScreen
import com.christopheraldoo.petheal.ui.screens.medicalrecord.MedicalRecordsScreen
import com.christopheraldoo.petheal.ui.screens.pet.AddPetScreen
import com.christopheraldoo.petheal.ui.screens.pet.EditPetScreen
import com.christopheraldoo.petheal.ui.screens.pet.PetDetailScreen
import com.christopheraldoo.petheal.ui.screens.pet.PetsScreen
import com.christopheraldoo.petheal.ui.screens.petshop.PetShopCheckoutScreen
import com.christopheraldoo.petheal.ui.screens.petshop.PetShopOrderDetailScreen
import com.christopheraldoo.petheal.ui.screens.petshop.PetShopOrdersScreen
import com.christopheraldoo.petheal.ui.screens.petshop.PetShopProductScreen
import com.christopheraldoo.petheal.ui.screens.petshop.PetShopReceiptScreen
import com.christopheraldoo.petheal.ui.screens.petshop.PetShopScreen
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
import com.christopheraldoo.petheal.ui.components.navigateBackOrHome
import com.christopheraldoo.petheal.ui.components.navigateToTab
import com.christopheraldoo.petheal.ui.components.route
import androidx.compose.ui.res.stringResource
import com.christopheraldoo.petheal.ui.util.collectAsStateLifecycleAware

@Composable
fun PetHealNavHost(
    navController: NavHostController = rememberNavController(),
    viewModel: NavigationViewModel = hiltViewModel()
) {
    // Klinik aktif untuk transaksi (dipakai memutuskan tujuan tombol booking
    // di layar perbandingan harga). Tidak mengubah klinik apa pun.
    val activeClinicSlug by viewModel.activeClinicSlug.collectAsStateLifecycleAware(initial = null)

    // ── Gerbang navigasi (anti double-tap) ────────────────────────────────
    // Dua ketukan cepat pada tombol yang sama tidak boleh menumpuk dua layar
    // identik (gejala: Back terasa harus ditekan 2x). Semua tombol di file
    // ini memakai goTo()/goBack(); satu-satunya navigasi yang sengaja
    // melewati gerbang adalah logout karena sesi kedaluwarsa (401).
    val navGate = remember { NavGate(windowMs = 400L) }
    val backGate = remember { NavGate(windowMs = 300L) }
    fun goTo(route: String, options: NavOptionsBuilder.() -> Unit = {}) {
        if (!navGate.allow(route, navController.currentDestination?.route)) return
        navController.navigate(route) { options() }
    }
    fun goBack() {
        if (!backGate.allow(null, navController.currentDestination?.route)) return
        navController.popBackStack()
    }

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
        startDestination = Screen.Splash.route,
        // Satu sistem gerak untuk SELURUH halaman (lihat PetHealTransitions.kt):
        // tab = fade-through, halaman detail = dorong ber-parallax, layar
        // transaksi = naik dari bawah seperti lembar modal. Dipilih dari route
        // tujuan, jadi tidak ada layar yang bisa lupa dianimasikan.
        enterTransition = {
            petHealEnterTransition(initialState.destination.route, targetState.destination.route)
        },
        exitTransition = {
            petHealExitTransition(initialState.destination.route, targetState.destination.route)
        },
        popEnterTransition = {
            petHealPopEnterTransition(initialState.destination.route, targetState.destination.route)
        },
        popExitTransition = {
            petHealPopExitTransition(initialState.destination.route, targetState.destination.route)
        }
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
                    goTo(Screen.Login.route) {
                        popUpTo(Screen.Splash.route) { inclusive = true }
                    }
                },
                onNavigateToHome = {
                    goTo(Screen.Home.route) {
                        popUpTo(Screen.Splash.route) { inclusive = true }
                    }
                },
                onNavigateToOnboarding = {
                    goTo(Screen.Onboarding.route) {
                        popUpTo(Screen.Splash.route) { inclusive = true }
                    }
                }
            )
        }

        // Onboarding Screen
        composable(Screen.Onboarding.route) {
            OnboardingScreen(
                onNavigateToLogin = {
                    goTo(Screen.Login.route) {
                        popUpTo(Screen.Onboarding.route) { inclusive = true }
                    }
                }
            )
        }

        // Main Screens
        composable(Screen.Login.route) {
            LoginScreen(
                onLoginSuccess = {
                    goTo(Screen.Home.route) {
                        popUpTo(Screen.Login.route) { inclusive = true }
                    }
                },
                onNavigateToCompleteSetup = {
                    goTo(Screen.CompleteSetup.route) {
                        popUpTo(Screen.Login.route) { inclusive = true }
                    }
                },
                onNavigateToRegister = {
                    goTo(Screen.Register.route)
                }
            )
        }

        composable(Screen.Register.route) {
            RegisterScreen(
                onRegisterSuccess = {
                    goTo(Screen.Home.route) {
                        popUpTo(Screen.Login.route) { inclusive = true }
                    }
                },
                onNavigateToCompleteSetup = {
                    goTo(Screen.CompleteSetup.route) {
                        popUpTo(Screen.Register.route) { inclusive = true }
                    }
                },
                onNavigateToOtpVerification = { email ->
                    goTo(Screen.VerifyOtp.createRoute(email))
                },
                onNavigateBack = {
                    goBack()
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
                    goTo(Screen.Home.route) {
                        popUpTo(Screen.Login.route) { inclusive = true }
                    }
                },
                onNavigateToCompleteSetup = {
                    goTo(Screen.CompleteSetup.route) {
                        popUpTo(Screen.Register.route) { inclusive = true }
                    }
                },
                onNavigateBack = {
                    goBack()
                }
            )
        }

        // PHASE 7: clinic-less accounts finish binding here, then Home.
        composable(Screen.CompleteSetup.route) {
            CompleteSetupScreen(
                onSetupComplete = {
                    goTo(Screen.Home.route) {
                        popUpTo(0) { inclusive = true }
                    }
                },
                onLoggedOut = {
                    goTo(Screen.Login.route) {
                        popUpTo(0) { inclusive = true }
                    }
                }
            )
        }

        // ── MULTI-CLINIC DISCOVERY ──────────────────────────────────────────
        composable(Screen.ClinicDiscovery.route) {
            ClinicDiscoveryScreen(
                onNavigateBack = { goBack() },
                onOpenClinic = { slug -> goTo(Screen.ClinicDetail.createRoute(slug)) },
                onComparePrices = {
                    goTo(Screen.PriceCompare.createRoute())
                }
            )
        }

        composable(
            route = Screen.ClinicDetail.route,
            arguments = listOf(navArgument("slug") { type = NavType.StringType })
        ) {
            ClinicDetailScreen(
                onNavigateBack = { goBack() },
                onCompareService = { serviceId ->
                    goTo(Screen.PriceCompare.createRoute(serviceId = serviceId))
                },
                onBookAtClinic = { clinicSlug, _ ->
                    // MULTI-CLINIC: klinik yang diklik user diteruskan ke alur
                    // booking dan langsung terpilih di langkah 2. User tidak
                    // perlu menemukan kliniknya lagi di daftar.
                    goTo(Screen.BookingStart.createRoute(clinicSlug))
                }
            )
        }

        composable(
            route = Screen.PriceCompare.route,
            arguments = listOf(
                navArgument("categorySlug") {
                    type = NavType.StringType
                    defaultValue = ""
                },
                navArgument("serviceId") {
                    type = NavType.IntType
                    defaultValue = 0
                }
            )
        ) {
            PriceCompareScreen(
                onNavigateBack = { goBack() },
                onOpenClinic = { slug -> goTo(Screen.ClinicDetail.createRoute(slug)) },
                onBookService = { clinicSlug, _ ->
                    // MULTI-CLINIC: klinik baris yang diklik langsung masuk
                    // alur booking dan terpilih di langkah 2. Tidak perlu lagi
                    // mampir ke detail klinik (yang dulu jadi jalan buntu).
                    // Klinik aktif hanya dipakai bila baris tidak menyebutnya.
                    goTo(
                        Screen.BookingStart.createRoute(
                            clinicSlug.ifBlank { activeClinicSlug }
                        )
                    )
                }
            )
        }

        // ── PET SHOP ─────────────────────────────────────────────────────────
        composable(Screen.PetShop.route) {
            PetShopScreen(
                onNavigateBack = { goBack() },
                onOpenProduct = { productId ->
                    goTo(Screen.PetShopProduct.createRoute(productId))
                },
                onOpenCart = { goTo(Screen.PetShopCheckout.route) },
                onOpenOrders = { goTo(Screen.PetShopOrders.route) }
            )
        }

        composable(
            route = Screen.PetShopProduct.route,
            arguments = listOf(navArgument("productId") { type = NavType.IntType })
        ) {
            PetShopProductScreen(
                onNavigateBack = { goBack() },
                onOpenCart = { goTo(Screen.PetShopCheckout.route) },
                onOpenOrders = { goTo(Screen.PetShopOrders.route) }
            )
        }

        composable(Screen.PetShopCheckout.route) {
            PetShopCheckoutScreen(
                onNavigateBack = { goBack() },
                onContinueShopping = {
                    navController.popBackStack(Screen.PetShop.route, inclusive = false)
                },
                onOrderCreated = { orderId ->
                    // Keranjang sudah dikosongkan repository; buang layar
                    // checkout agar tombol kembali tidak membuka keranjang kosong.
                    goTo(Screen.PetShopOrderDetail.createRoute(orderId)) {
                        popUpTo(Screen.PetShopCheckout.route) { inclusive = true }
                    }
                }
            )
        }

        composable(Screen.PetShopOrders.route) {
            PetShopOrdersScreen(
                onNavigateBack = { goBack() },
                onOpenOrder = { orderId ->
                    goTo(Screen.PetShopOrderDetail.createRoute(orderId))
                },
                onBrowseProducts = {
                    // Kembali ke katalog yang sudah ada di stack, bukan menumpuk
                    // layar Pet Shop kedua di atas daftar pesanan.
                    goTo(Screen.PetShop.route) {
                        popUpTo(Screen.PetShop.route) { inclusive = false }
                        launchSingleTop = true
                    }
                }
            )
        }

        composable(
            route = Screen.PetShopOrderDetail.route,
            arguments = listOf(navArgument("orderId") { type = NavType.IntType })
        ) {
            PetShopOrderDetailScreen(
                onNavigateBack = { goBack() },
                onOpenReceipt = { orderId ->
                    goTo(Screen.PetShopReceipt.createRoute(orderId))
                }
            )
        }

        composable(
            route = Screen.PetShopReceipt.route,
            arguments = listOf(navArgument("orderId") { type = NavType.IntType })
        ) {
            PetShopReceiptScreen(
                onNavigateBack = { goBack() }
            )
        }

        // Main Screens
        composable(Screen.Home.route) {
            HomeScreen(
                onNavigateToPets = { navigateTab(com.christopheraldoo.petheal.ui.components.PetHealTab.Pets) },
                onNavigateToDiscovery = { goTo(Screen.ClinicDiscovery.route) },
                onNavigateToDoctors = { goTo(Screen.Doctors.route) },
                onNavigateToBookingStart = {
                    goTo(Screen.BookingStart.createRoute())
                },
                onNavigateToDoctor = { doctorId, autoReview ->
                    goTo(Screen.DoctorDetail.createRoute(doctorId, autoReview))
                },
                // Baris dokter di Beranda membawa id kliniknya; detail dibuka
                // dengan scope klinik itu supaya tidak bergantung klinik sesi.
                onNavigateToDoctorInClinic = { doctorId, clinicId ->
                    goTo(Screen.DoctorDetail.createRoute(doctorId, clinicId = clinicId))
                },
                onNavigateToBookings = { navigateTab(com.christopheraldoo.petheal.ui.components.PetHealTab.Bookings) },
                onNavigateToBookingDetail = { bookingId ->
                    goTo(Screen.BookingDetail.createRoute(bookingId))
                },
                onNavigateToMedicalRecords = { navigateTab(com.christopheraldoo.petheal.ui.components.PetHealTab.Records) },
                onNavigateToProfile = { navigateTab(com.christopheraldoo.petheal.ui.components.PetHealTab.Profile) },
                onNavigateToNotifications = { goTo(Screen.Notifications.route) },
                onNavigateToPetShop = { goTo(Screen.PetShop.route) },
                onTabSelected = navigateTab
            )
        }

        // Pet Screens
        composable(Screen.Pets.route) {
            PetsScreen(
                onNavigateBack = { goBack() },
                onNavigateToPetDetail = { petId ->
                    goTo(Screen.PetDetail.createRoute(petId))
                },
                onNavigateToAddPet = { goTo(Screen.AddPet.route) },
                onTabSelected = navigateTab
            )
        }

        // "pets/add" must be before "pets/{petId}"
        composable(Screen.AddPet.route) {
            AddPetScreen(
                onNavigateBack = { goBack() },
                onPetAdded = { goBack() }
            )
        }

        composable(
            route = Screen.EditPet.route,
            arguments = listOf(navArgument("petId") { type = NavType.IntType })
        ) { backStackEntry ->
            val petId = backStackEntry.arguments?.getInt("petId") ?: return@composable
            EditPetScreen(
                petId = petId,
                onNavigateBack = { goBack() },
                onPetUpdated = { goBack() }
            )
        }

        composable(
            route = Screen.PetDetail.route,
            arguments = listOf(navArgument("petId") { type = NavType.IntType })
        ) { backStackEntry ->
            val petId = backStackEntry.arguments?.getInt("petId") ?: return@composable
            PetDetailScreen(
                petId = petId,
                onNavigateBack = { goBack() },
                onNavigateToEdit = { goTo(Screen.EditPet.createRoute(petId)) },
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
                onNavigateBack = { goBack() },
                onNavigateToDoctorDetail = { doctorId, clinicId ->
                    goTo(Screen.DoctorDetail.createRoute(doctorId, clinicId = clinicId))
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
                },
                navArgument("clinicId") {
                    type = NavType.IntType
                    defaultValue = 0
                }
            )
        ) { backStackEntry ->
            val doctorId = backStackEntry.arguments?.getInt("doctorId") ?: return@composable
            val autoReview = backStackEntry.arguments?.getBoolean("autoReview") ?: false
            val detailClinicId = backStackEntry.arguments?.getInt("clinicId")
                ?.takeIf { it > 0 }
            DoctorDetailScreen(
                doctorId = doctorId,
                clinicId = detailClinicId,
                autoOpenReview = autoReview,
                onNavigateBack = { goBack() }
            )
        }

        // Booking Screens
        composable(Screen.Bookings.route) {
            BookingsScreen(
                onNavigateBack = { goBack() },
                onNavigateToDoctors = { goTo(Screen.Doctors.route) },
                onNavigateToBookingStart = {
                    goTo(Screen.BookingStart.createRoute())
                },
                onNavigateToBookingDetail = { bookingId ->
                    goTo(Screen.BookingDetail.createRoute(bookingId))
                },
                onNavigateToPayment = { bookingId, isDp, totalAmount, isRemaining ->
                    goTo(Screen.Payment.createRoute(bookingId, isDp, totalAmount, isRemaining))
                },
                onTabSelected = navigateTab
            )
        }

        // "bookings/start" + "bookings/create/{doctorId}/{petId}" must be before "bookings/{bookingId}"
        composable(
            route = Screen.BookingStart.route,
            arguments = listOf(
                navArgument("clinicSlug") {
                    type = NavType.StringType
                    defaultValue = ""
                },
                navArgument("petId") {
                    type = NavType.IntType
                    defaultValue = 0
                },
                navArgument("medicalRecordId") {
                    type = NavType.IntType
                    defaultValue = 0
                }
            )
        ) { backStackEntry ->
            val sourceRecordId = backStackEntry.arguments?.getInt("medicalRecordId") ?: 0
            BookingStartScreen(
                onNavigateBack = { goBack() },
                onNavigateToAddPet = { goTo(Screen.AddPet.route) },
                onContinue = { doctorId, petId ->
                    goTo(
                        Screen.CreateBooking.createRoute(
                            doctorId = doctorId,
                            petId = petId,
                            medicalRecordId = sourceRecordId.takeIf { it > 0 }
                        )
                    )
                },
                initialClinicSlug = backStackEntry.arguments
                    ?.getString("clinicSlug")
                    ?.takeIf { it.isNotBlank() },
                initialPetId = backStackEntry.arguments?.getInt("petId")?.takeIf { it > 0 }
            )
        }

        composable(
            route = Screen.CreateBooking.route,
            arguments = listOf(
                navArgument("doctorId") { type = NavType.IntType },
                navArgument("petId") { type = NavType.IntType },
                navArgument("medicalRecordId") {
                    type = NavType.IntType
                    defaultValue = 0
                }
            )
        ) { backStackEntry ->
            val doctorId = backStackEntry.arguments?.getInt("doctorId") ?: return@composable
            val petId = backStackEntry.arguments?.getInt("petId") ?: return@composable
            val sourceRecordId = backStackEntry.arguments?.getInt("medicalRecordId") ?: 0
            CreateBookingScreen(
                doctorId = doctorId,
                petId = petId,
                onNavigateBack = { goBack() },
                onBookingCreated = { bookingId, isDp, amount, methodsCsv ->
                    // Navigate to payment screen with correct payment type and amount
                    goTo(Screen.Payment.createRoute(bookingId, isDp, amount, methodsCsv = methodsCsv)) {
                        popUpTo(Screen.CreateBooking.route) { inclusive = true }
                    }
                },
                medicalRecordId = sourceRecordId.takeIf { it > 0 },
                onRequestOtherService = { clinicSlug, requestPetId ->
                    goTo(
                        Screen.ServiceRequests.createRoute(
                            petId = requestPetId,
                            clinicSlug = clinicSlug
                        )
                    )
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
                onNavigateBack = { goBack() },
                onNavigateToPayment = { payBookingId, isDp, amount, isRemaining ->
                    goTo(Screen.Payment.createRoute(payBookingId, isDp, amount, isRemaining))
                },
                onNavigateToMedicalRecord = { recordId ->
                    goTo(Screen.MedicalRecordDetail.createRoute(recordId))
                },
                onNavigateToMedicalExtraPayment = { recordId, payBookingId, amount ->
                    goTo(Screen.MedicalExtraPayment.createRoute(payBookingId, recordId, amount))
                }
            )
        }

        // Medical Record Screens
        composable(Screen.MedicalRecords.route) {
            MedicalRecordsScreen(
                onNavigateBack = { goBack() },
                onNavigateToRecordDetail = { recordId ->
                    goTo(Screen.MedicalRecordDetail.createRoute(recordId))
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
                onNavigateBack = { goBack() },
                onNavigateToExtraPayment = { bookingId, payRecordId, amount ->
                    goTo(Screen.MedicalExtraPayment.createRoute(bookingId, payRecordId, amount))
                },
                onNavigateToDoctor = { doctorId ->
                    goTo(Screen.DoctorDetail.createRoute(doctorId, true))
                },
                onBookFollowUp = { followUpPetId, clinicSlug ->
                    goTo(
                        Screen.BookingStart.createRoute(
                            clinicSlug = clinicSlug,
                            petId = followUpPetId,
                            medicalRecordId = recordId
                        )
                    )
                }
            )
        }

        composable(
            route = Screen.ServiceRequests.route,
            arguments = listOf(
                navArgument("openId") {
                    type = NavType.IntType
                    defaultValue = 0
                },
                navArgument("petId") {
                    type = NavType.IntType
                    defaultValue = 0
                },
                navArgument("clinicSlug") {
                    type = NavType.StringType
                    defaultValue = ""
                },
                navArgument("medicalRecordId") {
                    type = NavType.IntType
                    defaultValue = 0
                }
            )
        ) { backStackEntry ->
            ServiceRequestsScreen(
                onNavigateBack = { goBack() },
                openId = backStackEntry.arguments?.getInt("openId")?.takeIf { it > 0 },
                petId = backStackEntry.arguments?.getInt("petId")?.takeIf { it > 0 },
                clinicSlug = backStackEntry.arguments
                    ?.getString("clinicSlug")
                    ?.takeIf { it.isNotBlank() },
                medicalRecordId = backStackEntry.arguments?.getInt("medicalRecordId")?.takeIf { it > 0 },
                onNavigateToBooking = { bookingId ->
                    goTo(Screen.BookingDetail.createRoute(bookingId))
                }
            )
        }

        // Profile Screens
        composable(Screen.Profile.route) {
            ProfileScreen(
                onNavigateBack = { goBack() },
                onNavigateToEdit = { goTo(Screen.EditProfile.route) },
                onLogout = {
                    goTo(Screen.Login.route) {
                        popUpTo(0) { inclusive = true }
                    }
                },
                onNavigateToNotifications = { goTo(Screen.Notifications.route) },
                onNavigateToServiceRequests = { goTo(Screen.ServiceRequests.createRoute()) },
                onNavigateToPrivacy = { goTo(Screen.PrivacySecurity.route) },
                onNavigateToHelp = { goTo(Screen.HelpSupport.route) },
                onNavigateToAbout = { goTo(Screen.About.route) },
                onTabSelected = navigateTab
            )
        }

        composable(Screen.EditProfile.route) {
            EditProfileScreen(
                onNavigateBack = { goBack() },
                onProfileUpdated = { goBack() }
            )
        }

        // Notifications Screen
        composable(Screen.Notifications.route) {
            NotificationsScreen(
                onNavigateBack = { goBack() },
                onOpenBooking = { bookingId -> goTo(Screen.BookingDetail.createRoute(bookingId)) },
                onOpenMedicalRecord = { recordId -> goTo(Screen.MedicalRecordDetail.createRoute(recordId)) },
                onOpenPet = { petId -> goTo(Screen.PetDetail.createRoute(petId)) },
                onOpenDoctor = { doctorId -> goTo(Screen.DoctorDetail.createRoute(doctorId)) },
                // Kelas bug yang sama dengan PetDetail: buka tab dari
                // sub-halaman wajib lewat mekanisme tab, bukan navigate().
                onOpenBookings = { navigateTab(com.christopheraldoo.petheal.ui.components.PetHealTab.Bookings) },
                onOpenPets = { navigateTab(com.christopheraldoo.petheal.ui.components.PetHealTab.Pets) }
            )
        }

        // Settings Screens
        composable(Screen.PrivacySecurity.route) {
            PrivacySecurityScreen(
                onNavigateBack = { goBack() }
            )
        }

        composable(Screen.HelpSupport.route) {
            HelpSupportScreen(
                onNavigateBack = { goBack() }
            )
        }

        composable(Screen.About.route) {
            AboutScreen(
                onNavigateBack = { goBack() }
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
            val paymentNavState by paymentNavViewModel.state.collectAsStateLifecycleAware()

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
                        onSecondaryActionClick = { goBack() }
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
                        // bookingId + recordId ikut dibawa supaya layar hasil
                        // mencetak BUKTI pembayaran biaya tambahan (sekualitas
                        // bukti booking) lengkap dengan rincian tindakan/obat.
                        onPaymentSuccess = { orderId, paymentType ->
                            goTo(
                                Screen.PaymentResult.createRoute(
                                    orderId, "success", "Pembayaran tambahan rekam medis berhasil!",
                                    bookingId = bookingId, recordId = recordId, method = paymentType
                                )
                            ) {
                                // popUpTo butuh POLA route destination, bukan
                                // route terisi — string terisi tidak match dan
                                // diam-diam tidak mem-pop apa pun.
                                popUpTo(Screen.MedicalExtraPayment.route) { inclusive = true }
                            }
                        },
                        onPaymentPending = { orderId ->
                            goTo(Screen.PaymentResult.createRoute(orderId, "pending", "Pembayaran tambahan menunggu penyelesaian.")) {
                                // popUpTo butuh POLA route destination, bukan
                                // route terisi — string terisi tidak match dan
                                // diam-diam tidak mem-pop apa pun.
                                popUpTo(Screen.MedicalExtraPayment.route) { inclusive = true }
                            }
                        },
                        onPaymentFailed = { errorMsg ->
                            goTo(Screen.PaymentResult.createRoute("medical-$recordId", "failed", errorMsg)) {
                                // popUpTo butuh POLA route destination, bukan
                                // route terisi — string terisi tidak match dan
                                // diam-diam tidak mem-pop apa pun.
                                popUpTo(Screen.MedicalExtraPayment.route) { inclusive = true }
                            }
                        },
                        // Kembali aman: pindah benar-benar; bila tumpukan
                        // kosong mendarat di Beranda (bukan tombol mati).
                        onNavigateBack = { navController.navigateBackOrHome() },
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
            val paymentNavState by paymentNavViewModel.state.collectAsStateLifecycleAware()

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
                        onSecondaryActionClick = { goBack() }
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
                        onPaymentSuccess = { orderId, _ ->
                            goTo(
                                Screen.PaymentResult.createRoute(orderId, "success", "Pembayaran berhasil!", bookingId)
                            ) {
                                // Pola route, bukan route terisi (lihat catatan
                                // popUpTo di PaymentScreen medical di atas).
                                popUpTo(Screen.Payment.route) { inclusive = true }
                            }
                        },
                        onPaymentPending = { orderId ->
                            goTo(
                                Screen.PaymentResult.createRoute(orderId, "pending", "Pembayaran menunggu. Segera selesaikan.", bookingId)
                            ) {
                                // Pola route, bukan route terisi (lihat catatan
                                // popUpTo di PaymentScreen medical di atas).
                                popUpTo(Screen.Payment.route) { inclusive = true }
                            }
                        },
                        onPaymentFailed = { errorMsg ->
                            goTo(
                                Screen.PaymentResult.createRoute("booking-$bookingId", "failed", errorMsg, bookingId)
                            ) {
                                // Pola route, bukan route terisi (lihat catatan
                                // popUpTo di PaymentScreen medical di atas).
                                popUpTo(Screen.Payment.route) { inclusive = true }
                            }
                        },
                        onNavigateBack = { navController.navigateBackOrHome() },
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
                },
                // >0 = alur biaya tambahan rekam medis.
                navArgument("recordId") {
                    type = NavType.IntType
                    defaultValue = 0
                },
                navArgument("method") {
                    type = NavType.StringType
                    defaultValue = ""
                }
            )
        ) { backStackEntry ->
            val orderId = backStackEntry.arguments?.getString("orderId") ?: ""
            val status = backStackEntry.arguments?.getString("status") ?: "failed"
            val message = backStackEntry.arguments?.getString("message") ?: ""
            val bookingId = backStackEntry.arguments?.getInt("bookingId") ?: 0
            val resultRecordId = backStackEntry.arguments?.getInt("recordId") ?: 0
            val resultMethod = backStackEntry.arguments?.getString("method")
                .orEmpty().takeIf { it.isNotBlank() }

            val paymentNavViewModel = hiltViewModel<PaymentNavViewModel>()
            val paymentNavState by paymentNavViewModel.state.collectAsStateLifecycleAware()

            LaunchedEffect(bookingId, resultRecordId) {
                if (bookingId > 0) paymentNavViewModel.loadBookingAndUser(bookingId)
                // Bukti biaya butuh data TERBARU record (status bayar & rincian).
                if (resultRecordId > 0) paymentNavViewModel.loadMedicalRecord(resultRecordId)
            }

            PaymentResultScreen(
                orderId = orderId,
                status = status,
                message = message,
                booking = paymentNavState.booking,
                // MULTI-CLINIC: kop bukti = klinik transaksi (booking /
                // rekam medis), bukan klinik aktif di preferensi.
                clinicName = paymentNavState.receiptClinicName,
                clinicAddress = paymentNavState.receiptClinicAddress,
                clinicColor = paymentNavState.receiptClinicColor,
                medicalRecord = paymentNavState.medicalRecord,
                paymentMethodLabel = resultMethod,
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
