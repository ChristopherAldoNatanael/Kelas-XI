# Weather Pro App

A modern weather application built with Jetpack Compose that provides real-time weather information with a beautiful UI.

## Features

- 📱 **Current Weather**: Get up-to-date weather information for your location or any city
- 🌦️ **5-Day Forecast**: View detailed weather forecasts for the upcoming days
- ⚠️ **Weather Alerts**: Stay informed about severe weather conditions
- 🌍 **Multilingual Support**: App available in English, Spanish, and Indonesian
- 🎨 **Customizable Themes**: Choose between light, dark, system, or time-based themes
- 📏 **Unit Preferences**: Switch between metric and imperial units
- 🔔 **Notifications**: Receive alerts for important weather updates
- 🏠 **Home Screen Widget**: Quick weather information right on your home screen

## Screenshots

(Screenshots would be added here)

## Technical Details

### Architecture

The app follows MVVM (Model-View-ViewModel) architecture and utilizes the following components:

- **UI Layer**: Jetpack Compose for building the user interface
- **ViewModel Layer**: Manages UI state and business logic
- **Repository Layer**: Abstracts data sources and provides a clean API
- **Data Layer**: Handles remote data fetching and local storage

### Technologies Used

- **Jetpack Compose**: Modern Android UI toolkit
- **Kotlin Coroutines & Flow**: Asynchronous programming
- **DataStore**: Storing user preferences
- **Navigation Component**: In-app navigation
- **Retrofit & OkHttp**: Network requests
- **Coil**: Image loading
- **Glance Widgets**: App widget implementation

## Setup Instructions

1. Clone this repository
2. Open in Android Studio
3. Add your OpenWeatherMap API key in `WeatherApi.kt`
4. Build and run the app

## Widget Implementation

The app includes a home screen widget that displays:

- Current temperature
- Weather condition
- Location
- Last update time

Widget features:

- Automatic updates every 30 minutes
- Manual refresh button
- Launches the app when clicked
- Configurable city selection

## Future Improvements

- More detailed weather data
- Additional widget sizes and designs
- Weather maps integration
- Historical weather data
- More languages

## License

(License information would go here)
