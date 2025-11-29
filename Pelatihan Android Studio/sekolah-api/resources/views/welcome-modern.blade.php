<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }} - Ultra Modern School Management</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            --secondary-gradient: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            --success-gradient: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
        }

        * {
            font-family: 'Inter', system-ui, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
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
            background:
                radial-gradient(circle at 20% 80%, rgba(102, 126, 234, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(118, 75, 162, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(240, 147, 251, 0.06) 0%, transparent 50%);
            z-index: -1;
            animation: morphing 20s ease-in-out infinite;
        }

        @keyframes morphing {
            0%, 100% {
                background:
                    radial-gradient(circle at 20% 80%, rgba(102, 126, 234, 0.08) 0%, transparent 50%),
                    radial-gradient(circle at 80% 20%, rgba(118, 75, 162, 0.08) 0%, transparent 50%),
                    radial-gradient(circle at 40% 40%, rgba(240, 147, 251, 0.06) 0%, transparent 50%);
            }
            33% {
                background:
                    radial-gradient(circle at 60% 30%, rgba(102, 126, 234, 0.1) 0%, transparent 50%),
                    radial-gradient(circle at 30% 70%, rgba(118, 75, 162, 0.1) 0%, transparent 50%),
                    radial-gradient(circle at 70% 60%, rgba(240, 147, 251, 0.08) 0%, transparent 50%);
            }
            66% {
                background:
                    radial-gradient(circle at 40% 60%, rgba(102, 126, 234, 0.06) 0%, transparent 50%),
                    radial-gradient(circle at 70% 40%, rgba(118, 75, 162, 0.06) 0%, transparent 50%),
                    radial-gradient(circle at 20% 30%, rgba(240, 147, 251, 0.1) 0%, transparent 50%);
            }
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .hero-gradient {
            background: var(--primary-gradient);
        }

        .text-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .floating {
            animation: floating 6s ease-in-out infinite;
        }

        @keyframes floating {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .feature-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .feature-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 25px 50px rgba(102, 126, 234, 0.15);
        }

        .btn-modern {
            background: var(--primary-gradient);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-modern:hover::before {
            left: 100%;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Navigation -->
    <nav class="fixed top-0 w-full z-50 glass-effect">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo -->
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-2xl hero-gradient flex items-center justify-center shadow-lg">
                        <i class="fas fa-graduation-cap text-xl text-white"></i>
                    </div>
                    <div class="ml-4">
                        <h1 class="text-xl font-black text-gradient">School Management</h1>
                        <p class="text-sm text-gray-600 font-medium">Ultra Modern Platform</p>
                    </div>
                </div>

                <!-- Auth Links -->
                <div class="flex items-center gap-4">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="btn-modern px-6 py-3 text-white font-semibold rounded-2xl text-sm">
                                <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="px-6 py-3 text-gray-700 font-semibold rounded-2xl text-sm hover:text-gray-900 transition-colors">
                                <i class="fas fa-sign-in-alt mr-2"></i>Login
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="btn-modern px-6 py-3 text-white font-semibold rounded-2xl text-sm">
                                    <i class="fas fa-user-plus mr-2"></i>Register
                                </a>
                            @endif
                        @endauth
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="pt-32 pb-20 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="text-center">
                <div class="floating mb-8">
                    <div class="w-32 h-32 mx-auto rounded-3xl hero-gradient flex items-center justify-center shadow-2xl">
                        <i class="fas fa-school text-5xl text-white"></i>
                    </div>
                </div>

                <h1 class="text-6xl md:text-7xl font-black mb-6">
                    <span class="text-gradient">Ultra Modern</span><br>
                    <span class="text-gray-800">School Management</span>
                </h1>

                <p class="text-xl md:text-2xl text-gray-600 mb-12 max-w-3xl mx-auto font-medium">
                    Experience the future of educational administration with our cutting-edge,
                    AI-powered school management platform designed for the modern era.
                </p>

                <div class="flex flex-col sm:flex-row gap-6 justify-center items-center">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="btn-modern px-8 py-4 text-white font-bold rounded-2xl text-lg shadow-xl">
                            <i class="fas fa-rocket mr-3"></i>Launch Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn-modern px-8 py-4 text-white font-bold rounded-2xl text-lg shadow-xl">
                            <i class="fas fa-sign-in-alt mr-3"></i>Get Started
                        </a>
                    @endauth
                    <a href="#features" class="px-8 py-4 glass-effect text-gray-700 font-bold rounded-2xl text-lg hover:shadow-xl transition-all">
                        <i class="fas fa-info-circle mr-3"></i>Learn More
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-5xl font-black text-gradient mb-6">Powerful Features</h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Everything you need to manage your educational institution efficiently and effectively.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="feature-card glass-effect rounded-3xl p-8 text-center">
                    <div class="w-16 h-16 mx-auto mb-6 rounded-2xl hero-gradient flex items-center justify-center">
                        <i class="fas fa-users text-2xl text-white"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">User Management</h3>
                    <p class="text-gray-600">Advanced role-based user management system with granular permissions and security controls.</p>
                </div>

                <!-- Feature 2 -->
                <div class="feature-card glass-effect rounded-3xl p-8 text-center">
                    <div class="w-16 h-16 mx-auto mb-6 rounded-2xl hero-gradient flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-2xl text-white"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Smart Scheduling</h3>
                    <p class="text-gray-600">AI-powered scheduling system that automatically optimizes class timetables and resource allocation.</p>
                </div>

                <!-- Feature 3 -->
                <div class="feature-card glass-effect rounded-3xl p-8 text-center">
                    <div class="w-16 h-16 mx-auto mb-6 rounded-2xl hero-gradient flex items-center justify-center">
                        <i class="fas fa-chart-line text-2xl text-white"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Analytics Dashboard</h3>
                    <p class="text-gray-600">Real-time analytics and insights with beautiful visualizations and comprehensive reporting.</p>
                </div>

                <!-- Feature 4 -->
                <div class="feature-card glass-effect rounded-3xl p-8 text-center">
                    <div class="w-16 h-16 mx-auto mb-6 rounded-2xl hero-gradient flex items-center justify-center">
                        <i class="fas fa-book text-2xl text-white"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Subject Management</h3>
                    <p class="text-gray-600">Comprehensive subject and curriculum management with progress tracking and assessments.</p>
                </div>

                <!-- Feature 5 -->
                <div class="feature-card glass-effect rounded-3xl p-8 text-center">
                    <div class="w-16 h-16 mx-auto mb-6 rounded-2xl hero-gradient flex items-center justify-center">
                        <i class="fas fa-chalkboard-user text-2xl text-white"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Teacher Portal</h3>
                    <p class="text-gray-600">Dedicated teacher interface with attendance tracking, grade management, and communication tools.</p>
                </div>

                <!-- Feature 6 -->
                <div class="feature-card glass-effect rounded-3xl p-8 text-center">
                    <div class="w-16 h-16 mx-auto mb-6 rounded-2xl hero-gradient flex items-center justify-center">
                        <i class="fas fa-mobile-alt text-2xl text-white"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Mobile Ready</h3>
                    <p class="text-gray-600">Fully responsive design with native mobile app integration for seamless access anywhere.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-20 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="glass-effect rounded-3xl p-12">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8 text-center">
                    <div>
                        <div class="text-4xl font-black text-gradient mb-2">500+</div>
                        <div class="text-gray-600 font-semibold">Schools Using</div>
                    </div>
                    <div>
                        <div class="text-4xl font-black text-gradient mb-2">50K+</div>
                        <div class="text-gray-600 font-semibold">Students Managed</div>
                    </div>
                    <div>
                        <div class="text-4xl font-black text-gradient mb-2">99.9%</div>
                        <div class="text-gray-600 font-semibold">Uptime</div>
                    </div>
                    <div>
                        <div class="text-4xl font-black text-gradient mb-2">24/7</div>
                        <div class="text-gray-600 font-semibold">Support</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <div class="flex items-center justify-center mb-6">
                    <div class="w-12 h-12 rounded-2xl hero-gradient flex items-center justify-center shadow-lg mr-4">
                        <i class="fas fa-graduation-cap text-xl text-white"></i>
                    </div>
                    <h3 class="text-2xl font-black">School Management System</h3>
                </div>
                <p class="text-gray-400 mb-8 max-w-2xl mx-auto">
                    Built with ❤️ using Laravel, Tailwind CSS, and modern web technologies.
                    Empowering education through innovation.
                </p>
                <div class="text-sm text-gray-500">
                    © {{ date('Y') }} School Management System. All rights reserved.
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
