<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'School Management System')</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        secondary: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                        },
                        success: {
                            50: '#f0fdf4',
                            500: '#22c55e',
                            600: '#16a34a',
                        },
                        warning: {
                            50: '#fffbeb',
                            500: '#f59e0b',
                            600: '#d97706',
                        },
                        danger: {
                            50: '#fef2f2',
                            500: '#ef4444',
                            600: '#dc2626',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-in': 'slideIn 0.3s ease-out',
                        'bounce-in': 'bounceIn 0.6s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideIn: {
                            '0%': { transform: 'translateX(-100%)' },
                            '100%': { transform: 'translateX(0)' },
                        },
                        bounceIn: {
                            '0%': { transform: 'scale(0.3)', opacity: '0' },
                            '50%': { transform: 'scale(1.05)', opacity: '0.8' },
                            '100%': { transform: 'scale(1)', opacity: '1' },
                        }
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            /* Light Mode Variables */
            --primary-gradient: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            --primary-dark: #3b82f6;
            --primary-light: #8b5cf6;
            --sidebar-bg: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --border-color: #e5e7eb;
            --hover-bg: #f3f4f6;
            --accent-color: #3b82f6;
            --bg-color: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --card-bg: #ffffff;
            --card-border: #e5e7eb;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --shadow-hover: rgba(0, 0, 0, 0.15);
            --nav-bg: #ffffff;
            --nav-border: #e5e7eb;
            --input-bg: #ffffff;
            --input-border: #d1d5db;
            --success-bg: #f0fdf4;
            --success-text: #166534;
            --success-border: #bbf7d0;
            --error-bg: #fef2f2;
            --error-text: #dc2626;
            --error-border: #fecaca;
            --warning-bg: #fffbeb;
            --warning-text: #d97706;
            --warning-border: #fed7aa;
            --info-bg: #eff6ff;
            --info-text: #1d4ed8;
            --info-border: #bfdbfe;
        }

        :root.dark-mode {
            /* Dark Mode Variables */
            --primary-gradient: linear-gradient(135deg, #60a5fa 0%, #a78bfa 100%);
            --primary-dark: #60a5fa;
            --primary-light: #a78bfa;
            --sidebar-bg: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            --text-primary: #f9fafb;
            --text-secondary: #d1d5db;
            --text-muted: #9ca3af;
            --border-color: #374151;
            --hover-bg: #374151;
            --accent-color: #60a5fa;
            --bg-color: #111827;
            --bg-secondary: #1f2937;
            --bg-tertiary: #374151;
            --card-bg: #1f2937;
            --card-border: #374151;
            --shadow-color: rgba(0, 0, 0, 0.5);
            --shadow-hover: rgba(0, 0, 0, 0.7);
            --nav-bg: #1f2937;
            --nav-border: #374151;
            --input-bg: #374151;
            --input-border: #4b5563;
            --success-bg: rgba(34, 197, 94, 0.1);
            --success-text: #4ade80;
            --success-border: rgba(34, 197, 94, 0.3);
            --error-bg: rgba(239, 68, 68, 0.1);
            --error-text: #f87171;
            --error-border: rgba(239, 68, 68, 0.3);
            --warning-bg: rgba(245, 158, 11, 0.1);
            --warning-text: #fbbf24;
            --warning-border: rgba(245, 158, 11, 0.3);
            --info-bg: rgba(59, 130, 246, 0.1);
            --info-text: #60a5fa;
            --info-border: rgba(59, 130, 246, 0.3);
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            width: 100%;
            max-width: 100%;
            background: var(--bg-color);
            color: var(--text-primary);
            transition: all 0.3s ease;
            font-family: 'Inter', system-ui, sans-serif;
        }

        body {
            background: var(--bg-color);
            background-image: linear-gradient(135deg, var(--bg-color) 0%, var(--bg-secondary) 100%);
            min-height: 100vh;
        }

        /* Navigation Styling */
        nav {
            background: var(--nav-bg) !important;
            border-bottom: 1px solid var(--nav-border) !important;
            box-shadow: 0 4px 12px var(--shadow-color);
            backdrop-filter: blur(10px);
        }

        nav .text-gray-900 {
            color: var(--text-primary) !important;
        }

        nav .text-gray-500 {
            color: var(--text-secondary) !important;
        }

        nav .text-indigo-600 {
            color: var(--primary-dark) !important;
        }

        nav .bg-indigo-100 {
            background-color: rgba(59, 130, 246, 0.1) !important;
        }

        nav .border-indigo-300 {
            border-color: rgba(59, 130, 246, 0.3) !important;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 80px;
            height: calc(100vh - 80px);
            width: 280px;
            background: var(--sidebar-bg);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
            border-right: 1px solid var(--border-color);
            box-shadow: 4px 0 20px var(--shadow-color);
            backdrop-filter: blur(12px);
            padding: 0;
        }

        .sidebar > div {
            padding: 16px;
            padding-top: 8px;
        }

        .sidebar.collapsed {
            width: 85px;
            box-shadow: 4px 0 16px var(--shadow-color);
        }

        .sidebar.collapsed .sidebar-text,
        .sidebar.collapsed .section-title {
            display: none;
        }

        .sidebar.collapsed .nav-item {
            justify-content: center;
        }

        .sidebar.collapsed .nav-item i {
            margin-right: 0 !important;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(59, 130, 246, 0.05);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: var(--primary-gradient);
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, var(--primary-light) 0%, var(--primary-dark) 100%);
        }

        .sidebar-section {
            transition: all 0.3s ease;
            padding: 8px 0;
            border-bottom: 1px solid rgba(59, 130, 246, 0.1);
        }

        .sidebar-section:first-child {
            padding-top: 0;
        }

        .sidebar-section:last-child {
            border-bottom: none;
        }

        .section-title {
            padding: 8px 16px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            text-transform: uppercase;
            transition: all 0.3s ease;
            margin-bottom: 4px;
            opacity: 0.8;
        }

        .sidebar.collapsed .section-title {
            display: none;
        }

        /* Enhanced section title visibility */
        :root.dark-mode .section-title {
            color: var(--text-secondary);
            opacity: 0.9;
        }

        :root .section-title {
            color: var(--text-muted);
            opacity: 0.7;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            margin: 3px 8px;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 14px;
            font-weight: 500;
            position: relative;
            overflow: hidden;
            border: 1px solid transparent;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--primary-gradient);
            opacity: 0.1;
            transition: left 0.4s ease;
            z-index: -1;
        }

        .nav-item:hover::before {
            left: 0;
        }

        .nav-item:hover {
            color: var(--primary-dark);
            background: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.2);
            transform: translateX(2px);
            box-shadow: 0 4px 12px var(--shadow-color);
        }

        .nav-item.active {
            background: rgba(59, 130, 246, 0.15);
            color: var(--primary-dark);
            border-color: var(--primary-dark);
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
        }

        .nav-item i {
            margin-right: 12px;
            width: 18px;
            text-align: center;
            transition: all 0.3s ease;
            font-size: 16px;
            color: var(--primary-dark);
            flex-shrink: 0;
        }

        .sidebar.collapsed .nav-item:hover {
            border: 1px solid rgba(59, 130, 246, 0.3);
            background: rgba(59, 130, 246, 0.15);
            border-radius: 12px;
        }

        .content-area {
            margin-left: 280px;
            transition: margin-left 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            width: calc(100% - 280px);
            max-width: calc(100% - 280px);
            overflow-x: hidden;
            position: relative;
            background: var(--bg-secondary);
        }

        .content-area.sidebar-collapsed {
            margin-left: 85px;
            width: calc(100% - 85px);
            max-width: calc(100% - 85px);
        }

        .main-content {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
            box-sizing: border-box;
            padding: 0;
            background: var(--bg-secondary);
        }

        .glass-effect {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            box-shadow: 0 4px 16px var(--shadow-color);
        }

        .gradient-bg {
            background: var(--primary-gradient);
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px var(--shadow-hover);
        }

        .btn-primary {
            background: var(--primary-gradient);
            transition: all 0.3s ease;
            color: white;
            border: none;
            font-weight: 600;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
            filter: brightness(1.1);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .loading-spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Table responsive fixes */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-responsive table {
            min-width: 100%;
            table-layout: fixed;
        }

        .table-responsive th,
        .table-responsive td {
            white-space: nowrap;
            padding: 12px 8px;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 100%;
            }

            .content-area {
                margin-left: 0;
                width: 100%;
                max-width: 100%;
            }

            .table-responsive {
                font-size: 14px;
            }

            .table-responsive th,
            .table-responsive td {
                padding: 8px 4px;
                font-size: 12px;
            }
        }

        /* Prevent horizontal overflow */
        .container, .container-fluid {
            max-width: 100% !important;
            width: 100% !important;
            padding-left: 15px;
            padding-right: 15px;
            margin-left: 0 !important;
            margin-right: 0 !important;
            overflow-x: hidden !important;
        }

        .row {
            margin-left: -15px;
            margin-right: -15px;
            width: 100% !important;
            max-width: 100% !important;
        }

        .col-md-12, .col-lg-12 {
            padding-left: 15px;
            padding-right: 15px;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }

        /* Table container fixes */
        .table-container {
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: auto !important;
        }

        .table {
            width: 100% !important;
            max-width: 100% !important;
            table-layout: fixed !important;
        }

        .table th,
        .table td {
            word-wrap: break-word !important;
            white-space: nowrap !important;
            max-width: 150px !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }

        /* Content wrapper */
        .content-wrapper {
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: hidden !important;
            box-sizing: border-box !important;
        }

        /* Card improvements */
        .card {
            margin-bottom: 24px;
            box-shadow: 0 4px 20px var(--shadow-color);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--card-border);
            background: var(--card-bg);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 30px var(--shadow-hover);
            transform: translateY(-2px);
        }

        .card-header {
            background: rgba(59, 130, 246, 0.05);
            border-bottom: 1px solid var(--card-border);
            padding: 20px 24px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .card-body {
            padding: 24px;
            color: var(--text-primary);
        }

        /* Form improvements */
        .form-group {
            margin-bottom: 24px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--input-border);
            border-radius: 10px;
            transition: all 0.3s ease;
            background: var(--input-bg);
            color: var(--text-primary);
            font-size: 14px;
        }

        .form-control:focus {
            border-color: var(--primary-dark);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
            outline: none;
            background: var(--card-bg);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        /* Button improvements */
        .btn {
            border-radius: 10px;
            font-weight: 600;
            padding: 12px 24px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.2);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* Badge & Alert styles */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid transparent;
        }

        .badge-primary {
            background: rgba(59, 130, 246, 0.15);
            color: var(--primary-dark);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .badge-success {
            background: var(--success-bg);
            color: var(--success-text);
            border-color: var(--success-border);
        }

        .badge-warning {
            background: var(--warning-bg);
            color: var(--warning-text);
            border-color: var(--warning-border);
        }

        .badge-danger {
            background: var(--error-bg);
            color: var(--error-text);
            border-color: var(--error-border);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }

        .animate-slide-in {
            animation: slideIn 0.3s ease-out;
        }

        /* Dark mode enhancements */
        :root.dark-mode .sidebar::-webkit-scrollbar-track {
            background: rgba(102, 126, 234, 0.1);
        }

        :root.dark-mode .sidebar-section {
            border-bottom-color: rgba(102, 126, 234, 0.15);
        }

        :root.dark-mode .nav-item.active {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.2) 0%, rgba(102, 126, 234, 0.1) 100%);
            box-shadow: inset -2px 0 4px rgba(102, 126, 234, 0.2);
        }

        /* Theme toggle button styling */
        .theme-toggle {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: #e5e7eb;
            border: 2px solid #d1d5db;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: #4b5563;
        }

        :root.dark-mode .theme-toggle {
            background: #374151;
            border-color: #4b5563;
            color: #9ca3af;
        }

        .theme-toggle:hover {
            background: #d1d5db;
            border-color: #9ca3af;
            transform: scale(1.05);
        }

        :root.dark-mode .theme-toggle:hover {
            background: #4b5563;
            border-color: #6b7280;
        }

        .theme-toggle i {
            font-size: 18px;
            transition: all 0.3s ease;
        }

        @keyframes rotateMoon {
            0% { transform: rotate(0deg) scale(1); }
            50% { transform: rotate(180deg) scale(0.8); }
            100% { transform: rotate(360deg) scale(1); }
        }

        /* Navigation styling adjustments for dark mode */
        nav {
            background: var(--nav-bg) !important;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        /* Fix navigation text colors to use CSS variables */
        nav .text-gray-900 {
            color: var(--text-primary) !important;
        }

        nav .text-gray-500 {
            color: var(--text-secondary) !important;
        }

        nav .text-gray-600 {
            color: var(--text-secondary) !important;
        }

        nav .text-gray-400 {
            color: var(--text-muted) !important;
        }

        nav .bg-white {
            background: var(--card-bg) !important;
        }

        nav .bg-gray-800 {
            background: var(--card-bg) !important;
        }

        nav .border-gray-300 {
            border-color: var(--border-color) !important;
        }

        nav .border-gray-600 {
            border-color: var(--border-color) !important;
        }

        /* User info styling */
        .user-info-container {
            background: var(--card-bg) !important;
            border: 1px solid var(--border-color) !important;
            color: var(--text-primary) !important;
        }

        .user-name {
            color: var(--text-primary) !important;
        }

        .user-role {
            color: var(--text-secondary) !important;
        }

        /* Theme toggle improvements */
        #theme-toggle {
            background: rgba(59, 130, 246, 0.1) !important;
            border-color: rgba(59, 130, 246, 0.2) !important;
            color: var(--primary-dark) !important;
        }

        #theme-toggle:hover {
            background: rgba(59, 130, 246, 0.15) !important;
            border-color: rgba(59, 130, 246, 0.3) !important;
        }

        /* Footer dark mode */
        footer {
            background: var(--card-bg) !important;
            border-top-color: var(--border-color) !important;
            transition: all 0.3s ease;
            box-shadow: 0 -4px 12px var(--shadow-color);
        }

        footer .text-gray-500 {
            color: var(--text-secondary) !important;
        }

        footer .text-gray-400 {
            color: var(--text-muted) !important;
        }

        footer .text-red-500 {
            color: #ef4444 !important;
        }
        nav.bg-white {
            background: var(--card-bg) !important;
            border-bottom-color: var(--border-color);
            box-shadow: 0 4px 12px var(--shadow-color);
        }

        /* Alert improvements with dark mode support */
        .bg-green-50 {
            background-color: #f0fdf4;
            color: #166534;
            transition: all 0.3s ease;
        }

        :root.dark-mode .bg-green-50 {
            background-color: rgba(34, 197, 94, 0.15);
        }

        .text-green-800 {
            color: #22c55e;
        }

        .bg-red-50 {
            background-color: #fef2f2;
            transition: all 0.3s ease;
        }

        :root.dark-mode .bg-red-50 {
            background-color: rgba(239, 68, 68, 0.15);
        }

        .text-red-800 {
            color: #ef4444;
        }

        .bg-yellow-50 {
            background-color: #fffbeb;
            transition: all 0.3s ease;
        }

        :root.dark-mode .bg-yellow-50 {
            background-color: rgba(245, 158, 11, 0.15);
        }

        .text-yellow-800 {
            color: #f59e0b;
        }

        .bg-blue-50 {
            background-color: #eff6ff;
            transition: all 0.3s ease;
        }

        :root.dark-mode .bg-blue-50 {
            background-color: rgba(59, 130, 246, 0.15);
        }

        .text-blue-800 {
            color: #3b82f6;
        }

        /* Table styling for dark mode */
        .table {
            color: var(--text-primary);
        }

        .table th {
            background-color: rgba(102, 126, 234, 0.05);
            border-color: var(--border-color);
            color: var(--text-primary);
            font-weight: 600;
        }

        .table td {
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .table tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }

        /* Footer styling */
        footer {
            background: var(--card-bg);
            border-top-color: var(--border-color);
            color: var(--text-secondary);
        }

        /* Improved Alert styling with better contrast */
        .alert-success,
        .bg-green-50 {
            background-color: var(--success-bg) !important;
            color: var(--success-text) !important;
            border-color: var(--success-border) !important;
            transition: all 0.3s ease;
        }

        .text-green-800 {
            color: var(--success-text) !important;
        }

        .alert-error,
        .bg-red-50 {
            background-color: var(--error-bg) !important;
            color: var(--error-text) !important;
            border-color: var(--error-border) !important;
            transition: all 0.3s ease;
        }

        .text-red-800 {
            color: var(--error-text) !important;
        }

        .alert-warning,
        .bg-yellow-50 {
            background-color: var(--warning-bg) !important;
            color: var(--warning-text) !important;
            border-color: var(--warning-border) !important;
            transition: all 0.3s ease;
        }

        .text-yellow-800 {
            color: var(--warning-text) !important;
        }

        .alert-info,
        .bg-blue-50 {
            background-color: var(--info-bg) !important;
            color: var(--info-text) !important;
            border-color: var(--info-border) !important;
            transition: all 0.3s ease;
        }

        .text-blue-800 {
            color: var(--info-text) !important;
        }

        /* Improved Table styling for both modes */
        .table {
            color: var(--text-primary);
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px var(--shadow-color);
        }

        .table th {
            background-color: rgba(59, 130, 246, 0.05);
            border-color: var(--border-color);
            color: var(--text-primary);
            font-weight: 600;
            padding: 16px 12px;
        }

        .table td {
            border-color: var(--border-color);
            color: var(--text-primary);
            padding: 14px 12px;
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: rgba(59, 130, 246, 0.05);
            transform: scale(1.01);
        }

        /* Enhanced theme toggle with better animation */
        .theme-toggle {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(59, 130, 246, 0.1);
            border: 2px solid rgba(59, 130, 246, 0.2);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: var(--primary-dark);
        }

        .theme-toggle:hover {
            background: rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.3);
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }

        .theme-toggle i {
            font-size: 18px;
            transition: all 0.3s ease;
        }

        /* Footer improvements */
        footer {
            background: var(--card-bg) !important;
            border-top-color: var(--border-color) !important;
            color: var(--text-secondary) !important;
            box-shadow: 0 -4px 12px var(--shadow-color);
        }

        /* Input improvements for better contrast */
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        select,
        textarea {
            background: var(--input-bg) !important;
            border-color: var(--input-border) !important;
            color: var(--text-primary) !important;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="number"]:focus,
        select:focus,
        textarea:focus {
            border-color: var(--primary-dark) !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15) !important;
            background: var(--card-bg) !important;
        }

        /* Modal and dropdown improvements */
        .dropdown-menu,
        .modal-content {
            background: var(--card-bg) !important;
            border: 1px solid var(--card-border) !important;
            box-shadow: 0 8px 32px var(--shadow-color) !important;
            color: var(--text-primary) !important;
        }

        /* Button variants with better contrast */
        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--hover-bg);
            border-color: var(--primary-dark);
        }

        .btn-success {
            background: var(--success-text);
            color: white;
        }

        .btn-danger {
            background: var(--error-text);
            color: white;
        }

        .btn-warning {
            background: var(--warning-text);
            color: white;
        }

        .btn-info {
            background: var(--info-text);
            color: white;
        }

        /* Loading states and animations */
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: .5;
            }
        }

        /* Scrollbar improvements */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-gradient);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, var(--primary-light) 0%, var(--primary-dark) 100%);
        }

        /* Responsive improvements */
        @media (max-width: 640px) {
            .sidebar {
                width: 100% !important;
                transform: translateX(-100%);
            }

            .content-area {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            .card {
                margin: 8px;
                border-radius: 12px;
            }

            .table-responsive {
                border-radius: 8px;
                overflow: hidden;
            }
        }

        /* Enhanced navigation and sidebar text styling */
        .navigation-text {
            color: var(--text-primary) !important;
        }

        .navigation-text-secondary {
            color: var(--text-secondary) !important;
        }

        .navigation-text-muted {
            color: var(--text-muted) !important;
        }

        /* Override any remaining Tailwind classes */
        nav * {
            transition: all 0.3s ease;
        }

        nav .text-white {
            color: white !important;
        }

        /* Ensure buttons maintain proper styling */
        .btn-custom-primary {
            background: var(--primary-gradient);
            color: white;
            border: none;
        }

        .btn-custom-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        /* Mobile overlay improvements */
        .sidebar-overlay {
            background: rgba(0, 0, 0, 0.5);
        }

        :root.dark-mode .sidebar-overlay {
            background: rgba(0, 0, 0, 0.7);
        }

        /* Enhanced mobile sidebar */
        @media (max-width: 768px) {
            .sidebar {
                background: var(--sidebar-bg);
                border-right: none;
                box-shadow: 4px 0 20px var(--shadow-color);
            }
        }
    </style>
</head>
<body class="font-sans" style="background: var(--bg-secondary);">
    <!-- Modern Navigation -->
    <nav class="bg-white dark:bg-gray-900 shadow-lg border-b border-gray-200 dark:border-gray-700 sticky top-0 z-50 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-3">
                    <!-- Sidebar toggle button -->
                    @if(session('user'))
                        <button id="sidebar-toggle" class="p-2.5 rounded-lg text-white bg-linear-to-r from-indigo-500 via-purple-500 to-pink-500 hover:from-indigo-600 hover:via-purple-600 hover:to-pink-600 transform hover:scale-110 transition-all duration-300 shadow-md hover:shadow-lg md:hidden flex items-center justify-center" title="Toggle Sidebar">
                            <i class="fas fa-bars text-lg"></i>
                        </button>
                    @endif

                    <!-- Logo -->
                    <div class="flex items-center">
                        <div class="shrink-0">
                            <i class="fas fa-graduation-cap text-2xl mr-2" style="color: var(--primary-dark);"></i>
                        </div>
                        <div class="hidden md:block">
                            <h1 class="text-lg font-bold" style="color: var(--text-primary);">School Management</h1>
                            <p class="text-xs" style="color: var(--text-secondary);">System Administration</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Theme Toggle Button -->
                    <button id="theme-toggle" class="p-2.5 rounded-lg bg-indigo-100 dark:bg-gray-700 text-indigo-600 dark:text-yellow-400 hover:bg-indigo-200 dark:hover:bg-gray-600 transition-all duration-300 shadow-md hover:shadow-lg border border-indigo-300 dark:border-gray-600 font-semibold" title="Toggle Dark Mode">
                        <i class="fas fa-moon dark:hidden"></i>
                        <i class="fas fa-sun hidden dark:inline"></i>
                    </button>

                    @if(session('user'))
                        <!-- User info -->
                        <div class="hidden md:flex items-center gap-3 px-4 py-2 rounded-lg user-info-container shadow-sm">
                            <div class="text-right">
                                <p class="text-sm font-bold user-name leading-tight">{{ session('user')['nama'] }}</p>
                                <p class="text-xs user-role leading-tight">{{ session('user')['role'] }}</p>
                            </div>
                            <div class="h-8 w-8 rounded-full bg-linear-to-br from-indigo-500 to-purple-600 flex items-center justify-center shrink-0 shadow-md">
                                <i class="fas fa-user text-white text-xs"></i>
                            </div>
                        </div>

                        <!-- Logout button -->
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-lg text-white bg-red-500 hover:bg-red-600 dark:bg-red-600 dark:hover:bg-red-700 focus:outline-none transition ease-in-out duration-150 shadow-sm hover:shadow-md">
                                <i class="fas fa-sign-out-alt mr-2"></i>
                                Logout
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 focus:outline-none transition ease-in-out duration-150 shadow-sm hover:shadow-md">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Login
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="flex min-h-screen" style="background: var(--bg-secondary);">
        <!-- Modern Sidebar -->
        @if(session('user'))
        <aside id="sidebar" class="sidebar">
            <div class="p-4 space-y-6">
                <!-- Sidebar Toggle Button -->
                <div class="flex justify-end pb-2">
                    <button id="sidebar-collapse-btn" class="p-2.5 rounded-lg text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-all duration-300 font-semibold" title="Toggle Sidebar">
                        <i class="fas fa-chevron-left text-base"></i>
                    </button>
                </div>

                <!-- Navigation Menu -->
                <nav class="space-y-2">
                    <!-- Dashboard -->
                    <div class="sidebar-section">
                        <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="fas fa-tachometer-alt"></i>
                            <span class="sidebar-text">Dashboard</span>
                        </a>
                    </div>

                    <!-- Schedule Management -->
                    <div class="sidebar-section">
                        <h3 class="section-title">Schedule</h3>
                        <a href="{{ route('web-schedules.index') }}" class="nav-item {{ request()->routeIs('web-schedules.index') ? 'active' : '' }}">
                            <i class="fas fa-calendar-alt"></i>
                            <span class="sidebar-text">All Schedules</span>
                        </a>
                        <a href="{{ route('web-schedules.create') }}" class="nav-item">
                            <i class="fas fa-plus-circle"></i>
                            <span class="sidebar-text">Create Schedule</span>
                        </a>
                    </div>

                    <!-- User Management -->
                    <div class="sidebar-section">
                        <h3 class="section-title">Users</h3>
                        <a href="{{ route('web-users.index') }}" class="nav-item {{ request()->routeIs('web-users.*') ? 'active' : '' }}">
                            <i class="fas fa-users"></i>
                            <span class="sidebar-text">Users</span>
                        </a>
                        <a href="{{ route('web-teachers.index') }}" class="nav-item {{ request()->routeIs('web-teachers.*') ? 'active' : '' }}">
                            <i class="fas fa-chalkboard-user"></i>
                            <span class="sidebar-text">Teachers</span>
                        </a>
                    </div>

                    <!-- Academic Management -->
                    <div class="sidebar-section">
                        <h3 class="section-title">Academic</h3>
                        <a href="{{ route('web-subjects.index') }}" class="nav-item {{ request()->routeIs('web-subjects.*') ? 'active' : '' }}">
                            <i class="fas fa-book"></i>
                            <span class="sidebar-text">Subjects</span>
                        </a>
                        <a href="{{ route('web-classes.index') }}" class="nav-item {{ request()->routeIs('web-classes.*') ? 'active' : '' }}">
                            <i class="fas fa-graduation-cap"></i>
                            <span class="sidebar-text">Classes</span>
                        </a>
                    </div>
                </nav>
            </div>
        </aside>
        @endif

        <!-- Content Area -->
        <main class="flex-1 content-area {{ session('user') ? 'md:ml-0' : '' }}">
            <!-- Mobile sidebar overlay -->
            @if(session('user'))
                <div id="sidebar-overlay" class="fixed inset-0 bg-gray-600 bg-opacity-75 z-40 md:hidden hidden">
                    <div class="fixed inset-y-0 left-0 flex w-full max-w-xs flex-col">
                        <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-white px-6 pb-4">
                            <!-- Mobile sidebar content would go here -->
                        </div>
                    </div>
                </div>
            @endif

            <!-- Page Header -->
            @hasSection('page-header')
                @yield('page-header')
            @endif

            <!-- Alert Messages -->
            <div class="px-6 py-4">
                @if(session('success'))
                    <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg animate-fade-in">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span>{{ session('success') }}</span>
                        </div>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg animate-fade-in">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <span>{{ session('error') }}</span>
                        </div>
                    </div>
                @endif

                @if(session('warning'))
                    <div class="mb-4 bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg animate-fade-in">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span>{{ session('warning') }}</span>
                        </div>
                    </div>
                @endif

                @if(session('info'))
                    <div class="mb-4 bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg animate-fade-in">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span>{{ session('info') }}</span>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Page Content -->
            <div class="px-6 pb-8">
                @yield('content')
            </div>
        </main>
    </div>

    <!-- Modern Footer -->
    <footer class="border-t transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="text-sm" style="color: var(--text-secondary);">
                    © {{ date('Y') }} School Management System. All rights reserved.
                </div>
                <div class="mt-2 md:mt-0 text-sm" style="color: var(--text-secondary);">
                    <span>Built with </span>
                    <i class="fas fa-heart text-red-500 mx-1"></i>
                    <span>using Laravel & Tailwind CSS</span>
                </div>
            </div>
        </div>
    </footer>

    @yield('scripts')

    <!-- Mobile Sidebar Toggle Script -->
    @if(session('user'))
    <script>
        // Dark Mode Toggle Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('theme-toggle');
            const html = document.documentElement;

            // Load saved theme preference
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                html.classList.add('dark-mode');
                updateThemeIcon(true);
            }

            // Theme toggle button click handler
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    html.classList.toggle('dark-mode');
                    const isDarkMode = html.classList.contains('dark-mode');
                    localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
                    updateThemeIcon(isDarkMode);
                });
            }

            function updateThemeIcon(isDarkMode) {
                const moonIcon = themeToggle.querySelector('.fa-moon');
                const sunIcon = themeToggle.querySelector('.fa-sun');
                if (isDarkMode) {
                    if (moonIcon) moonIcon.classList.add('hidden', 'dark:inline');
                    if (sunIcon) sunIcon.classList.remove('hidden', 'dark:inline');
                } else {
                    if (moonIcon) moonIcon.classList.remove('hidden', 'dark:inline');
                    if (sunIcon) sunIcon.classList.add('hidden', 'dark:inline');
                }
            }

            // Sidebar toggle functionality
            const sidebarCollapseBtn = document.getElementById('sidebar-collapse-btn');
            const sidebar = document.getElementById('sidebar');
            const contentArea = document.querySelector('.content-area');
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            const toggleIcon = sidebarCollapseBtn?.querySelector('i');

            let isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

            // Initialize sidebar state
            function initSidebarState() {
                if (isCollapsed && window.innerWidth >= 768) {
                    sidebar.classList.add('collapsed');
                    contentArea.classList.add('sidebar-collapsed');
                    if (toggleIcon) {
                        toggleIcon.classList.remove('fa-chevron-left');
                        toggleIcon.classList.add('fa-chevron-right');
                    }
                }
            }

            // Toggle sidebar collapse
            if (sidebarCollapseBtn && sidebar && contentArea) {
                sidebarCollapseBtn.addEventListener('click', function() {
                    isCollapsed = !isCollapsed;
                    localStorage.setItem('sidebarCollapsed', isCollapsed);

                    if (isCollapsed) {
                        sidebar.classList.add('collapsed');
                        contentArea.classList.add('sidebar-collapsed');
                        if (toggleIcon) {
                            toggleIcon.classList.remove('fa-chevron-left');
                            toggleIcon.classList.add('fa-chevron-right');
                        }
                    } else {
                        sidebar.classList.remove('collapsed');
                        contentArea.classList.remove('sidebar-collapsed');
                        if (toggleIcon) {
                            toggleIcon.classList.remove('fa-chevron-right');
                            toggleIcon.classList.add('fa-chevron-left');
                        }
                    }
                });
            }

            // Mobile sidebar toggle
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('-translate-x-full');
                    if (sidebarOverlay) {
                        sidebarOverlay.classList.toggle('hidden');
                    }
                });

                // Close sidebar when clicking overlay
                if (sidebarOverlay) {
                    sidebarOverlay.addEventListener('click', function() {
                        sidebar.classList.add('-translate-x-full');
                        sidebarOverlay.classList.add('hidden');
                    });
                }

                // Close sidebar when clicking a nav link on mobile
                const navItems = sidebar.querySelectorAll('.nav-item');
                navItems.forEach(item => {
                    item.addEventListener('click', function() {
                        if (window.innerWidth < 768) {
                            sidebar.classList.add('-translate-x-full');
                            if (sidebarOverlay) {
                                sidebarOverlay.classList.add('hidden');
                            }
                        }
                    });
                });

                // Handle window resize
                window.addEventListener('resize', function() {
                    if (window.innerWidth >= 768) {
                        sidebar.classList.remove('-translate-x-full');
                        if (sidebarOverlay) {
                            sidebarOverlay.classList.add('hidden');
                        }
                    }
                });
            }

            // Initialize on page load
            initSidebarState();
        });
    </script>
    @endif
</body>
</html>
