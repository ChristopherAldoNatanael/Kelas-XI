<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance — PetHeal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'], display: ['"Plus Jakarta Sans"', 'Inter', 'system-ui', 'sans-serif'] },
                    colors: { brand: { 50: '#ecfdf5', 100: '#d1fae5', 400: '#34d399', 500: '#10b981', 600: '#059669', 700: '#047857', 800: '#065f46', 900: '#064e3b' } }
                }
            }
        }
    </script>
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1a2a3a 40%, #064e3b 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .grid-pattern { background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 60px 60px; }
    </style>
</head>
<body class="font-sans antialiased">
    <div class="grid-pattern absolute inset-0 opacity-40"></div>
    <div class="relative text-center px-6">
        <div class="w-20 h-20 rounded-2xl bg-brand-500/10 border border-brand-500/20 flex items-center justify-center mx-auto mb-8">
            <svg class="w-10 h-10 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        </div>
        <h1 class="text-4xl sm:text-5xl font-display font-extrabold text-white tracking-tight">Sedang Dalam Pemeliharaan</h1>
        <p class="mt-4 text-lg text-slate-300/80 max-w-md mx-auto leading-relaxed">Kami sedang melakukan peningkatan sistem. Silakan kembali lagi nanti.</p>
        <div class="mt-8 inline-flex items-center gap-3 px-5 py-2.5 rounded-xl bg-white/5 border border-white/10">
            <span class="w-2 h-2 rounded-full bg-brand-400 animate-pulse"></span>
            <span class="text-sm text-slate-300">Perkiraan selesai: dalam beberapa saat</span>
        </div>
    </div>
</body>
</html>
