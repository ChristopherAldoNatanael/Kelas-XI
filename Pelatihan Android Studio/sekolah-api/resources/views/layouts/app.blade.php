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

    @yield('styles')

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
            /* Ultra-Modern Minimalist Light Mode */
            --primary: #000000;
            --primary-light: #1a1a1a;
            --primary-dark: #000000;
            --secondary: #6b7280;
            --accent: #3b82f6;
            --accent-light: #60a5fa;
            --accent-dark: #2563eb;

            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --bg-muted: #e2e8f0;

            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --text-inverse: #ffffff;

            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --border-dark: #cbd5e1;

            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);

            --radius-sm: 0.25rem;
            --radius: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;

            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

            /* Status Colors */
            --success: #10b981;
            --success-bg: #ecfdf5;
            --success-border: #d1fae5;
            --error: #ef4444;
            --error-bg: #fef2f2;
            --error-border: #fecaca;
            --warning: #f59e0b;
            --warning-bg: #fffbeb;
            --warning-border: #fef3c7;
            --info: #3b82f6;
            --info-bg: #eff6ff;
            --info-border: #dbeafe;
        }

        :root.dark-mode {
            /* Professional Minimalist Dark Mode Variables */
            --primary-gradient: linear-gradient(135deg, #334155 0%, #475569 50%, #64748b 100%);
            --primary-hover: linear-gradient(135deg, #1e293b 0%, #334155 50%, #475569 100%);
            --primary-dark: #334155;
            --primary-light: #64748b;
            --accent-gradient: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            --success-gradient: linear-gradient(135deg, #166534 0%, #22c55e 100%);
            --danger-gradient: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
            --warning-gradient: linear-gradient(135deg, #ca8a04 0%, #eab308 100%);
            --sidebar-bg: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            --sidebar-glass: rgba(255, 255, 255, 0.06);
            --sidebar-glass-hover: rgba(255, 255, 255, 0.12);
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e0;
            --text-muted: #94a3b8;
            --text-sidebar: #f8fafc;
            --border-color: rgba(71, 85, 105, 0.4);
            --hover-bg: rgba(51, 65, 85, 0.1);
            --accent-color: #334155;
            --bg-color: #0f172a;
            --bg-secondary: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            --bg-tertiary: #1e293b;
            --card-bg: rgba(30, 41, 59, 0.95);
            --card-border: rgba(71, 85, 105, 0.3);
            --shadow-color: rgba(0, 0, 0, 0.6);
            --shadow-hover: rgba(0, 0, 0, 0.8);
            --nav-bg: #1e293b;
            --nav-border: #334155;
            --input-bg: #334155;
            --input-border: #475569;
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
            background: var(--bg-secondary);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-secondary);
            z-index: -2;
        }

        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 20% 80%, rgba(102, 126, 234, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(118, 75, 162, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(240, 147, 251, 0.08) 0%, transparent 50%);
            z-index: -1;
            animation: morphing 20s ease-in-out infinite;
        }

        @keyframes morphing {
            0%, 100% {
                background:
                    radial-gradient(circle at 20% 80%, rgba(102, 126, 234, 0.1) 0%, transparent 50%),
                    radial-gradient(circle at 80% 20%, rgba(118, 75, 162, 0.1) 0%, transparent 50%),
                    radial-gradient(circle at 40% 40%, rgba(240, 147, 251, 0.08) 0%, transparent 50%);
            }
            33% {
                background:
                    radial-gradient(circle at 60% 30%, rgba(102, 126, 234, 0.12) 0%, transparent 50%),
                    radial-gradient(circle at 30% 70%, rgba(118, 75, 162, 0.12) 0%, transparent 50%),
                    radial-gradient(circle at 70% 60%, rgba(240, 147, 251, 0.1) 0%, transparent 50%);
            }
            66% {
                background:
                    radial-gradient(circle at 40% 60%, rgba(102, 126, 234, 0.08) 0%, transparent 50%),
                    radial-gradient(circle at 70% 40%, rgba(118, 75, 162, 0.08) 0%, transparent 50%),
                    radial-gradient(circle at 20% 30%, rgba(240, 147, 251, 0.12) 0%, transparent 50%);
            }
        }

        /* Minimalist Navigation & Sidebar */
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: var(--radius);
            transition: var(--transition);
            font-size: 14px;
            font-weight: 500;
            position: relative;
        }

        .nav-link:hover {
            color: var(--text-primary);
            background-color: var(--bg-muted);
        }

        .nav-link.active {
            color: var(--primary);
            background-color: rgba(0, 0, 0, 0.05);
            font-weight: 600;
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 20px;
            background-color: var(--primary);
            border-radius: 2px;
        }

        .nav-link i {
            width: 18px;
            text-align: center;
            flex-shrink: 0;
        }

        .content-area {
            margin-left: 280px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            width: calc(100% - 280px);
            max-width: calc(100% - 280px);
            overflow-x: hidden;
            position: relative;
            background: #fafbfc;
        }

        .content-area.sidebar-collapsed {
            margin-left: 80px;
            width: calc(100% - 80px);
            max-width: calc(100% - 80px);
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

        /* Ultra Modern Card Design */
        .card {
            margin-bottom: 32px;
            box-shadow:
                0 10px 40px rgba(102, 126, 234, 0.1),
                0 0 0 1px var(--card-border) inset;
            border-radius: 24px;
            overflow: hidden;
            border: none;
            background: var(--card-bg);
            color: var(--text-primary);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(20px);
            position: relative;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(
                90deg,
                transparent 0%,
                rgba(102, 126, 234, 0.3) 50%,
                transparent 100%
            );
        }

        .card:hover {
            box-shadow:
                0 20px 60px rgba(102, 126, 234, 0.15),
                0 0 0 1px rgba(102, 126, 234, 0.2) inset;
            transform: translateY(-8px) scale(1.02);
        }

        .card-header {
            background: linear-gradient(
                135deg,
                rgba(102, 126, 234, 0.08) 0%,
                rgba(118, 75, 162, 0.05) 100%
            );
            border-bottom: 1px solid rgba(102, 126, 234, 0.1);
            padding: 28px 32px;
            font-weight: 700;
            color: var(--text-primary);
            position: relative;
        }

        .card-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 32px;
            right: 32px;
            height: 2px;
            background: var(--primary-gradient);
            border-radius: 1px;
            opacity: 0.6;
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

        /* Modern Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 14px;
            transition: var(--transition);
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--text-inverse);
        }

        .btn-primary:hover {
            background-color: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        .btn-secondary {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            border-color: var(--border);
        }

        .btn-secondary:hover {
            background-color: var(--bg-muted);
        }

        /* Modern Card Styles */
        .card {
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }

        .card-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-light);
        }

        .card-body {
            padding: 24px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 14px;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Table Styles */
        .table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-primary);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .table th,
        .table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        .table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
        }

        .table tbody tr:hover {
            background: var(--bg-muted);
        }
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

        /* Enhanced Responsive Design for Professional Sidebar */
        @media (max-width: 768px) {
            .sidebar {
                width: 280px !important;
                transform: translateX(-100%);
                z-index: 50;
            }

            .content-area {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            .sidebar-nav-item {
                padding: 14px 18px;
                font-size: 15px;
            }

            .section-header {
                padding: 10px 18px 14px 18px;
                font-size: 12px;
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

        /* Custom Scrollbar for Sidebar */
        #sidebar::-webkit-scrollbar {
            width: 6px;
        }

        #sidebar::-webkit-scrollbar-track {
            background: rgba(148, 163, 184, 0.1);
            border-radius: 3px;
        }

        #sidebar::-webkit-scrollbar-thumb {
            background: rgba(59, 130, 246, 0.3);
            border-radius: 3px;
        }

        #sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(59, 130, 246, 0.5);
        }

        /* Dark mode scrollbar */
        :root.dark-mode #sidebar::-webkit-scrollbar-track {
            background: rgba(71, 85, 105, 0.2);
        }

        :root.dark-mode #sidebar::-webkit-scrollbar-thumb {
            background: rgba(59, 130, 246, 0.4);
        }

        :root.dark-mode #sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(59, 130, 246, 0.6);
        }

        @media (max-width: 640px) {
            .sidebar {
                width: 100% !important;
                max-width: 320px;
            }

            .sidebar-nav-item {
                padding: 16px 20px;
                margin-bottom: 4px;
            }

            .nav-icon {
                width: 24px;
                height: 24px;
            }

            .section-header {
                padding: 12px 20px 16px 20px;
            }
        }

        /* Enhanced Navbar Scroll Effects */
        .navbar-scrolled {
            background: rgba(255, 255, 255, 0.98) !important;
            backdrop-filter: blur(20px) !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12) !important;
            border-color: rgba(0, 0, 0, 0.08) !important;
            transform: translateY(0) !important;
            animation: navbarSlideDown 0.3s ease-out;
        }

        @keyframes navbarSlideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        :root.dark-mode .navbar-scrolled {
            background: rgba(15, 23, 42, 0.95) !important;
            border-color: rgba(71, 85, 105, 0.3) !important;
        }

        /* Enhanced Professional Sidebar Styles */
        .sidebar-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 2px;
            border: 1px solid transparent;
        }

        .sidebar-nav-item:hover {
            color: var(--text-primary);
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(147, 51, 234, 0.06) 100%);
            border-color: rgba(59, 130, 246, 0.2);
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        }

        .sidebar-nav-item.active {
            color: #3b82f6;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.12) 0%, rgba(147, 51, 234, 0.08) 100%);
            border-color: rgba(59, 130, 246, 0.3);
            font-weight: 600;
            box-shadow: inset 0 2px 4px rgba(59, 130, 246, 0.1), 0 4px 12px rgba(59, 130, 246, 0.15);
        }

        .sidebar-nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 24px;
            background: linear-gradient(180deg, #3b82f6 0%, #6366f1 100%);
            border-radius: 2px;
            box-shadow: 0 0 8px rgba(59, 130, 246, 0.5);
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .sidebar-nav-item:hover .nav-icon,
        .sidebar-nav-item.active .nav-icon {
            transform: scale(1.1);
        }

        .nav-text {
            flex: 1;
            transition: all 0.3s ease;
        }

        .nav-indicator {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.3);
            opacity: 0;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .sidebar-nav-item:hover .nav-indicator,
        .sidebar-nav-item.active .nav-indicator {
            opacity: 1;
            background: #3b82f6;
            box-shadow: 0 0 6px rgba(59, 130, 246, 0.6);
        }

        .sidebar-section {
            margin-bottom: 24px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px 12px 16px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            margin-bottom: 8px;
        }

        .section-content {
            space-y: 2px;
        }

        .quick-action {
            cursor: pointer;
            width: 100%;
            text-align: left;
        }

        .quick-action:hover {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(251, 191, 36, 0.06) 100%);
            border-color: rgba(245, 158, 11, 0.2);
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

        /* Dark Mode Support for Enhanced Sidebar */
        :root.dark-mode .sidebar {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            border-color: rgba(71, 85, 105, 0.3);
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.3);
        }

        :root.dark-mode .sidebar-nav-item {
            color: var(--text-sidebar);
        }

        :root.dark-mode .sidebar-nav-item:hover {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(147, 51, 234, 0.1) 100%);
            border-color: rgba(59, 130, 246, 0.3);
            color: #e2e8f0;
        }

        :root.dark-mode .sidebar-nav-item.active {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(147, 51, 234, 0.15) 100%);
            border-color: rgba(59, 130, 246, 0.4);
            color: #60a5fa;
        }

        :root.dark-mode .section-header {
            color: #94a3b8;
            border-color: rgba(71, 85, 105, 0.3);
        }

        :root.dark-mode .sidebar-section {
            border-bottom-color: rgba(71, 85, 105, 0.2);
        }

        :root.dark-mode .quick-action:hover {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(251, 191, 36, 0.1) 100%);
            border-color: rgba(245, 158, 11, 0.3);
        }
    </style>
</head>
<body class="font-sans" style="background: var(--bg-secondary);">
    <!-- Ultra-Modern Navigation -->
    <nav id="main-navbar" class="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-md border-b border-gray-200/50 shadow-lg transition-all duration-300">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center gap-6">
                    <!-- Sidebar toggle -->
                    @if(session('user'))
                        <button id="sidebar-toggle" class="md:hidden p-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition-colors duration-200">
                            <i class="fas fa-bars text-lg"></i>
                        </button>
                    @endif

                    <!-- Logo -->
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-black flex items-center justify-center">
                            <i class="fas fa-graduation-cap text-white text-sm"></i>
                        </div>
                        <div class="hidden md:block">
                            <h1 class="text-lg font-semibold text-gray-900">SchoolHub</h1>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <!-- Theme Toggle -->
                    <button id="theme-toggle" class="p-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition-colors duration-200">
                        <i class="fas fa-moon text-lg"></i>
                    </button>

                    @if(session('user'))
                        <!-- User Menu -->
                        <div class="flex items-center gap-3">
                            <div class="hidden md:block text-right">
                                <p class="text-sm font-medium text-gray-900">{{ session('user')['nama'] }}</p>
                                <p class="text-xs text-gray-500 capitalize">{{ session('user')['role'] }}</p>
                            </div>
                            <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center">
                                <i class="fas fa-user text-gray-600 text-sm"></i>
                            </div>
                        </div>

                        <!-- Logout -->
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg text-gray-700 hover:text-gray-900 hover:bg-gray-100 transition-colors duration-200">
                                <i class="fas fa-sign-out-alt mr-2"></i>
                                Logout
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-black hover:bg-gray-800 transition-colors duration-200">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Login
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="flex min-h-screen pt-16" style="background: var(--bg-secondary);">
        <!-- Enhanced Professional Sidebar -->
        @if(session('user'))
        <aside id="sidebar" class="fixed left-0 top-16 h-[calc(100vh-4rem)] w-72 bg-gradient-to-b from-slate-50 to-white border-r border-slate-200/60 shadow-xl backdrop-blur-sm transition-all duration-500 ease-in-out overflow-y-auto z-40">
            <!-- Sidebar Header -->
            <div class="relative p-6 border-b border-slate-200/50 bg-gradient-to-r from-blue-50/50 to-indigo-50/50">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg">
                        <i class="fas fa-school text-white text-sm"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-800 text-sm">SchoolHub Pro</h3>
                        <p class="text-xs text-slate-500">Management System</p>
                    </div>
                </div>

                <!-- User Status Indicator -->
                <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white/60 backdrop-blur-sm border border-slate-200/50">
                    <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
                    <span class="text-xs font-medium text-slate-600">{{ session('user')['nama'] }}</span>
                    <span class="text-xs text-slate-400 capitalize">({{ session('user')['role'] }})</span>
                </div>
            </div>

            <!-- Navigation Menu -->
            <div class="flex-1 overflow-y-auto scrollbar-thin scrollbar-thumb-slate-300 scrollbar-track-transparent">
                <nav class="p-4 space-y-2">
                    <!-- Dashboard -->
                    <div class="mb-6">
                        <a href="{{ route('dashboard') }}" class="sidebar-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <div class="nav-icon">
                                <i class="fas fa-home"></i>
                            </div>
                            <span class="nav-text">Dashboard</span>
                            <div class="nav-indicator"></div>
                        </a>
                    </div>

                    <!-- Management Section -->
                    <div class="sidebar-section">
                        <div class="section-header">
                            <i class="fas fa-cogs text-blue-500"></i>
                            <span>Management</span>
                        </div>

                        <div class="section-content">
                            <a href="{{ route('web-users.index') }}" class="sidebar-nav-item {{ request()->routeIs('web-users.*') ? 'active' : '' }}">
                                <div class="nav-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <span class="nav-text">Users</span>
                                <div class="nav-indicator"></div>
                            </a>

                            <a href="{{ route('web-teachers.index') }}" class="sidebar-nav-item {{ request()->routeIs('web-teachers.*') ? 'active' : '' }}">
                                <div class="nav-icon">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                                <span class="nav-text">Teachers</span>
                                <div class="nav-indicator"></div>
                            </a>

                            <a href="{{ route('web-subjects.index') }}" class="sidebar-nav-item {{ request()->routeIs('web-subjects.*') ? 'active' : '' }}">
                                <div class="nav-icon">
                                    <i class="fas fa-book-open"></i>
                                </div>
                                <span class="nav-text">Subjects</span>
                                <div class="nav-indicator"></div>
                            </a>

                            <a href="{{ route('web-classes.index') }}" class="sidebar-nav-item {{ request()->routeIs('web-classes.*') ? 'active' : '' }}">
                                <div class="nav-icon">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <span class="nav-text">Classes</span>
                                <div class="nav-indicator"></div>
                            </a>
                        </div>
                    </div>

                    <!-- Schedules Section -->
                    <div class="sidebar-section">
                        <div class="section-header">
                            <i class="fas fa-calendar-alt text-purple-500"></i>
                            <span>Schedules</span>
                        </div>

                        <div class="section-content">
                            <a href="{{ route('web-schedules.index') }}" class="sidebar-nav-item {{ request()->routeIs('web-schedules.index', 'web-schedules.show') ? 'active' : '' }}">
                                <div class="nav-icon">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <span class="nav-text">All Schedules</span>
                                <div class="nav-indicator"></div>
                            </a>

                            <a href="{{ route('web-schedules.create') }}" class="sidebar-nav-item {{ request()->routeIs('web-schedules.create') ? 'active' : '' }}">
                                <div class="nav-icon">
                                    <i class="fas fa-plus-circle"></i>
                                </div>
                                <span class="nav-text">Create Schedule</span>
                                <div class="nav-indicator"></div>
                            </a>
                        </div>
                    </div>

                    <!-- Attendance Section -->
                    <div class="sidebar-section">
                        <div class="section-header">
                            <i class="fas fa-user-check text-green-500"></i>
                            <span>Attendance</span>
                        </div>

                        <div class="section-content">
                            <a href="{{ route('teacher-attendance.index') }}" class="sidebar-nav-item {{ request()->routeIs('teacher-attendance.*') ? 'active' : '' }}">
                                <div class="nav-icon">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <span class="nav-text">Teacher Attendance</span>
                                <div class="nav-indicator"></div>
                            </a>

                            <a href="{{ route('teacher-leaves.index') }}" class="sidebar-nav-item {{ request()->routeIs('teacher-leaves.*') ? 'active' : '' }}">
                                <div class="nav-icon">
                                    <i class="fas fa-calendar-times"></i>
                                </div>
                                <span class="nav-text">Leave Management</span>
                                <div class="nav-indicator"></div>
                            </a>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="sidebar-section">
                        <div class="section-header">
                            <i class="fas fa-bolt text-amber-500"></i>
                            <span>Quick Actions</span>
                        </div>

                        <div class="section-content">
                            <button class="sidebar-nav-item quick-action" onclick="showQuickStats()">
                                <div class="nav-icon">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <span class="nav-text">Quick Stats</span>
                            </button>

                            <button class="sidebar-nav-item quick-action" onclick="showRecentActivity()">
                                <div class="nav-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <span class="nav-text">Recent Activity</span>
                            </button>
                        </div>
                    </div>
                </nav>
            </div>

            <!-- Sidebar Footer -->
            <div class="p-4 border-t border-slate-200/50 bg-gradient-to-r from-slate-50 to-white">
                <div class="text-center">
                    <p class="text-xs text-slate-500 mb-2">SchoolHub v2.0</p>
                    <div class="flex justify-center gap-2">
                        <button class="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" title="Settings">
                            <i class="fas fa-cog"></i>
                        </button>
                        <button class="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" title="Help">
                            <i class="fas fa-question-circle"></i>
                        </button>
                        <button class="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" title="Feedback">
                            <i class="fas fa-comment"></i>
                        </button>
                    </div>
                </div>
            </div>
        </aside>
        @endif

        <!-- Content Area -->
        <main class="flex-1 {{ session('user') ? 'ml-72' : '' }} transition-all duration-300">
            <!-- Page Header -->
            @hasSection('page-header')
                @yield('page-header')
            @endif

            <!-- Alert Messages -->
            <div class="px-6 py-4">
                @if(session('success'))
                    <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span>{{ session('success') }}</span>
                        </div>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <span>{{ session('error') }}</span>
                        </div>
                    </div>
                @endif

                @if(session('warning'))
                    <div class="mb-4 bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span>{{ session('warning') }}</span>
                        </div>
                    </div>
                @endif

                @if(session('info'))
                    <div class="mb-4 bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg">
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

    <!-- Professional Footer -->
    <footer class="bg-white border-t border-slate-200 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="text-sm text-slate-600">
                    © {{ date('Y') }} School Management System. All rights reserved.
                </div>
                <div class="mt-2 md:mt-0 text-sm text-slate-600">
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
                if (sidebar && contentArea && isCollapsed && window.innerWidth >= 768) {
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

            // Navbar scroll effect
            let lastScrollY = window.scrollY;
            const navbar = document.getElementById('main-navbar');

            window.addEventListener('scroll', () => {
                const currentScrollY = window.scrollY;

                if (currentScrollY > 10) {
                    navbar.classList.add('navbar-scrolled');
                } else {
                    navbar.classList.remove('navbar-scrolled');
                }

                lastScrollY = currentScrollY;
            });

            // Quick Actions Functions
            window.showQuickStats = function() {
                // Create a modal or toast with quick statistics
                const statsModal = document.createElement('div');
                statsModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
                statsModal.innerHTML = `
                    <div class="bg-white rounded-2xl p-6 max-w-md mx-4 shadow-2xl">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Quick Statistics</h3>
                            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                                <span class="text-sm text-gray-600">Total Users</span>
                                <span class="font-semibold text-blue-600">Loading...</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                                <span class="text-sm text-gray-600">Active Schedules</span>
                                <span class="font-semibold text-green-600">Loading...</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-purple-50 rounded-lg">
                                <span class="text-sm text-gray-600">Teachers</span>
                                <span class="font-semibold text-purple-600">Loading...</span>
                            </div>
                        </div>
                        <div class="mt-4 text-center">
                            <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                                Close
                            </button>
                        </div>
                    </div>
                `;
                document.body.appendChild(statsModal);
            };

            window.showRecentActivity = function() {
                // Create a modal with recent activity
                const activityModal = document.createElement('div');
                activityModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
                activityModal.innerHTML = `
                    <div class="bg-white rounded-2xl p-6 max-w-md mx-4 shadow-2xl">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Recent Activity</h3>
                            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user-plus text-blue-600 text-xs"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-800">New user registered</p>
                                    <p class="text-xs text-gray-500">2 minutes ago</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 p-3 bg-green-50 rounded-lg">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-calendar-plus text-green-600 text-xs"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-800">Schedule created</p>
                                    <p class="text-xs text-gray-500">15 minutes ago</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 p-3 bg-yellow-50 rounded-lg">
                                <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-edit text-yellow-600 text-xs"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-800">Teacher profile updated</p>
                                    <p class="text-xs text-gray-500">1 hour ago</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 text-center">
                            <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                                Close
                            </button>
                        </div>
                    </div>
                `;
                document.body.appendChild(activityModal);
            };
        });
    </script>
    @endif
</body>
</html>
