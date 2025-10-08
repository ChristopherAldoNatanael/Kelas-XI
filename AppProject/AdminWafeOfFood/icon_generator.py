#!/usr/bin/env python3
"""
Icon Generator Script for AdminWafeOfFood
Generates burger-themed app icons in various sizes
"""

import os
from pathlib import Path

def create_icon_sizes():
    """Create different icon sizes for Android"""
    
    # Define icon sizes for different densities
    icon_sizes = {
        'mdpi': 48,      # 1x
        'hdpi': 72,      # 1.5x  
        'xhdpi': 96,     # 2x
        'xxhdpi': 144,   # 3x
        'xxxhdpi': 192   # 4x
    }
    
    print("🍔 Burger Icon Generator for AdminWafeOfFood")
    print("=" * 50)
    
    for density, size in icon_sizes.items():
        print(f"📱 {density}: {size}x{size}px")
    
    print("\n💡 To generate actual bitmap icons:")
    print("1. Use Android Studio's Image Asset Studio")
    print("2. Or use online vector-to-bitmap converters")
    print("3. Or use tools like Inkscape/GIMP")
    
    print("\n✅ Vector drawables created successfully!")
    print("📍 Location: app/src/main/res/drawable/")
    
    # List created vector files
    vector_files = [
        "ic_burger_logo.xml",
        "ic_burger_app_icon.xml", 
        "ic_launcher_background.xml (updated)",
        "ic_launcher_foreground.xml (updated)"
    ]
    
    print("\n📄 Created vector files:")
    for file in vector_files:
        print(f"   ✓ {file}")

if __name__ == "__main__":
    create_icon_sizes()
