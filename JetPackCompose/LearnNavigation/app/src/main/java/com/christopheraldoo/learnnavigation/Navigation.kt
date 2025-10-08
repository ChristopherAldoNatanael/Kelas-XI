package com.christopheraldoo.learnnavigation

import androidx.compose.runtime.Composable
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.christopheraldoo.learnnavigation.ui.screen.AboutScreen
import com.christopheraldoo.learnnavigation.ui.screen.DetailScreen
import com.christopheraldoo.learnnavigation.ui.screen.HomeScreen

@Composable
fun AppNavigation() {
    val navController: NavHostController = rememberNavController()

    NavHost(navController = navController, startDestination = "home") {
        composable("home") {
            HomeScreen(
                onNavigateToDetail = { navController.navigate("detail/Christopher") },
                onNavigateToAbout = { navController.navigate("about") }
            )
        }
        composable(
            route = "detail/{name}",
            arguments = listOf(navArgument("name") { type = NavType.StringType })
        ) {
            val name = it.arguments?.getString("name")
            DetailScreen(
                name = name,
                onNavigateUp = { navController.navigateUp() }
            )
        }
        composable("about") {
            AboutScreen(
                onNavigateUp = { navController.navigateUp() }
            )
        }
    }
}