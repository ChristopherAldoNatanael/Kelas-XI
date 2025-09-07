#!/bin/bash
# Navigation Test Helper Script

echo "======================================"
echo "WAVES OF FOOD - NAVIGATION TEST HELPER"
echo "======================================"
echo ""

# Function to show ADB commands
show_adb_commands() {
    echo "📱 ADB Commands untuk Testing:"
    echo "  Clear logcat: adb logcat -c"
    echo "  Monitor logs: adb logcat | grep -E '(MainFragment|ProfileFragment|Navigation)'"
    echo "  Filter specific: adb logcat | grep 'ProfileFragment'"
    echo ""
}

# Function to show test scenarios
show_test_scenarios() {
    echo "🧪 Test Scenarios:"
    echo "  1. Basic Profile Navigation:"
    echo "     - Login → Main → Click Profile Tab"
    echo "     - Expected: Smooth navigation"
    echo ""
    echo "  2. Order History Navigation:"
    echo "     - Profile → Click 'Order History'"
    echo "     - Expected: Navigate OR show dialog"
    echo ""
    echo "  3. Error Scenarios:"
    echo "     - Rapid clicking between tabs"
    echo "     - Network interruptions"
    echo "     - Expected: No crashes, helpful messages"
    echo ""
}

# Function to show what to look for
show_success_indicators() {
    echo "✅ Success Indicators:"
    echo "  - No app crashes"
    echo "  - Clear error messages if any"
    echo "  - Smooth animations"
    echo "  - Proper toast messages"
    echo ""
    echo "🔍 Log Messages to Watch:"
    echo "  - 'MainFragment: Profile navigation successful'"
    echo "  - 'ProfileFragment: navigateToOrderHistory called'"
    echo "  - 'ProfileFragment: Action navigation successful!'"
    echo ""
}

# Function to show troubleshooting
show_troubleshooting() {
    echo "🔧 Troubleshooting:"
    echo "  1. If still getting navigation errors:"
    echo "     - Check logcat for specific error messages"
    echo "     - Look for 'IllegalStateException' or 'IllegalArgumentException'"
    echo ""
    echo "  2. If profile doesn't load:"
    echo "     - Check if ProfileFragment class exists"
    echo "     - Verify navigation graph definitions"
    echo ""
    echo "  3. If order history fails:"
    echo "     - Should show fallback dialog"
    echo "     - Check OrdersFragment implementation"
    echo ""
}

# Main menu
main_menu() {
    echo "Select option:"
    echo "1) Show ADB Commands"
    echo "2) Show Test Scenarios"
    echo "3) Show Success Indicators"
    echo "4) Show Troubleshooting"
    echo "5) Show All"
    echo "0) Exit"
    echo ""
    read -p "Enter choice [0-5]: " choice
    
    case $choice in
        1) show_adb_commands ;;
        2) show_test_scenarios ;;
        3) show_success_indicators ;;
        4) show_troubleshooting ;;
        5) 
            show_adb_commands
            show_test_scenarios
            show_success_indicators
            show_troubleshooting
            ;;
        0) echo "Goodbye!" ; exit 0 ;;
        *) echo "Invalid option" ;;
    esac
    
    echo ""
    echo "Press Enter to continue..."
    read
    main_menu
}

# Start the script
main_menu
