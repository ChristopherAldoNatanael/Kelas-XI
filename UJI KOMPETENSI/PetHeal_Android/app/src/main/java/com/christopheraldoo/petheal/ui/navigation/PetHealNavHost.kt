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
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.material3.Icon
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.christopheraldoo.petheal.ui.screens.auth.LoginScreen
import com.christopheraldoo.petheal.ui.screens.auth.OnboardingScreen
import com.christopheraldoo.petheal.ui.screens.auth.RegisterScreen
import com.christopheraldoo.petheal.ui.screens.auth.SplashScreen
import com.christopheraldoo.petheal.ui.screens.booking.BookingDetailScreen
import com.christopheraldoo.petheal.ui.screens.booking.BookingsScreen
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

@Composable
fun PetHealNavHost(
    navController: NavHostController = rememberNavController(),
    viewModel: NavigationViewModel = hiltViewModel()
) {
    val isLoggedIn by viewModel.isLoggedIn.collectAsState(initial = false)

    NavHost(
        navController = navController,
        startDestination = Screen.Splash.route
    ) {
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
                onNavigateBack = {
                    navController.popBackStack()
                }
            )
        }

        // Main Screens
        composable(Screen.Home.route) {
            HomeScreen(
                onNavigateToPets = { navController.navigate(Screen.Pets.route) },
                onNavigateToDoctors = { navController.navigate(Screen.Doctors.route) },
                onNavigateToBookings = { navController.navigate(Screen.Bookings.route) },
                onNavigateToMedicalRecords = { navController.navigate(Screen.MedicalRecords.route) },
                onNavigateToProfile = { navController.navigate(Screen.Profile.route) },
                onNavigateToNotifications = { navController.navigate(Screen.Notifications.route) }
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
                onNavigateToBookings = { navController.navigate(Screen.Bookings.route) },
                onNavigateToProfile = { navController.navigate(Screen.Profile.route) }
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
                onNavigateToMedicalRecords = { navController.navigate(Screen.MedicalRecords.route) }
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
            arguments = listOf(navArgument("doctorId") { type = NavType.IntType })
        ) { backStackEntry ->
            val doctorId = backStackEntry.arguments?.getInt("doctorId") ?: return@composable
            DoctorDetailScreen(
                doctorId = doctorId,
                onNavigateBack = { navController.popBackStack() },
                onNavigateToBooking = { petId ->
                    navController.navigate(Screen.CreateBooking.createRoute(doctorId, petId))
                }
            )
        }

        // Booking Screens
        composable(Screen.Bookings.route) {
            BookingsScreen(
                onNavigateBack = { navController.popBackStack() },
                onNavigateToBookingDetail = { bookingId ->
                    navController.navigate(Screen.BookingDetail.createRoute(bookingId))
                },
                onNavigateToPayment = { bookingId, isDp, totalAmount, isRemaining ->
                    navController.navigate(Screen.Payment.createRoute(bookingId, isDp, totalAmount, isRemaining))
                }
            )
        }

        // "bookings/create/{doctorId}/{petId}" must be before "bookings/{bookingId}"
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
                onBookingCreated = { bookingId, isDp, amount ->
                    // Navigate to payment screen with correct payment type and amount
                    navController.navigate(Screen.Payment.createRoute(bookingId, isDp, amount)) {
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
                onNavigateToHome = {
                    navController.navigate(Screen.Home.route) {
                        popUpTo(Screen.Home.route) { inclusive = true }
                    }
                },
                onNavigateToBookings = { navController.navigate(Screen.Bookings.route) },
                onNavigateToProfile = { navController.navigate(Screen.Profile.route) }
            )
        }

        composable(
            route = Screen.MedicalRecordDetail.route,
            arguments = listOf(navArgument("recordId") { type = NavType.IntType })
        ) { backStackEntry ->
            val recordId = backStackEntry.arguments?.getInt("recordId") ?: return@composable
            MedicalRecordDetailScreen(
                recordId = recordId,
                onNavigateBack = { navController.popBackStack() }
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
                onNavigateToAbout = { navController.navigate(Screen.About.route) }
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
                onNavigateBack = { navController.popBackStack() }
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
            route = Screen.Payment.route,
            arguments = listOf(
                navArgument("bookingId") { type = NavType.IntType },
                navArgument("isDp") { type = NavType.BoolType },
                navArgument("totalAmount") { type = NavType.FloatType },
                navArgument("isRemaining") { 
                    type = NavType.BoolType
                    defaultValue = false
                }
            )
        ) { backStackEntry ->
            val bookingId = backStackEntry.arguments?.getInt("bookingId") ?: return@composable
            val isDp = backStackEntry.arguments?.getBoolean("isDp") ?: false
            val totalAmount = backStackEntry.arguments?.getFloat("totalAmount")?.toDouble() ?: 0.0
            val isRemaining = backStackEntry.arguments?.getBoolean("isRemaining") ?: false

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
                            color = Color(0xFF2BEE6C)
                        )
                    }
                }
                paymentNavState.error != null -> {
                    Column(
                        Modifier.fillMaxSize().padding(24.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center
                    ) {
                        Text(
                            "Error: ${paymentNavState.error}",
                            color = Color(0xFFEF4444),
                            textAlign = TextAlign.Center
                        )
                        Spacer(Modifier.height(16.dp))
                        Button(onClick = { navController.popBackStack() }) {
                            Text("Go Back")
                        }
                    }
                }
                paymentNavState.booking != null -> {
                    PaymentScreen(
                        booking = paymentNavState.booking!!,
                        user = paymentNavState.user,
                        isDpPayment = isDp,
                        totalAmount = totalAmount,
                        isRemainingPayment = isRemaining, // NEW: Pass isRemaining flag
                        onPaymentSuccess = { orderId ->
                            navController.navigate(
                                Screen.PaymentResult.createRoute(orderId, "success", "Payment successful!")
                            ) {
                                popUpTo(Screen.Payment.createRoute(bookingId, isDp, totalAmount, isRemaining)) { inclusive = true }
                            }
                        },
                        onPaymentPending = { orderId ->
                            navController.navigate(
                                Screen.PaymentResult.createRoute(orderId, "pending", "Payment pending. Please complete soon.")
                            ) {
                                popUpTo(Screen.Payment.createRoute(bookingId, isDp, totalAmount, isRemaining)) { inclusive = true }
                            }
                        },
                        onPaymentFailed = { errorMsg ->
                            navController.navigate(
                                Screen.PaymentResult.createRoute("booking-$bookingId", "failed", errorMsg)
                            ) {
                                popUpTo(Screen.Payment.createRoute(bookingId, isDp, totalAmount, isRemaining)) { inclusive = true }
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

        // Payment Result Screen
        composable(
            route = Screen.PaymentResult.route,
            arguments = listOf(
                navArgument("orderId") { type = NavType.StringType },
                navArgument("status") { type = NavType.StringType },
                navArgument("message") { type = NavType.StringType }
            )
        ) { backStackEntry ->
            val orderId = backStackEntry.arguments?.getString("orderId") ?: ""
            val status = backStackEntry.arguments?.getString("status") ?: "failed"
            val message = backStackEntry.arguments?.getString("message") ?: ""

            PaymentResultScreen(
                orderId = orderId,
                status = status,
                message = message,
                onNavigateToBookings = {
                    navController.navigate(Screen.Bookings.route) {
                        popUpTo(Screen.PaymentResult.route) { inclusive = true }
                    }
                },
                onNavigateBack = {
                    navController.navigate(Screen.Home.route) {
                        popUpTo(Screen.PaymentResult.route) { inclusive = true }
                    }
                }
            )
        }
    }
}
