<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Design Style Previews - danielpetrica.com</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400&family=Space+Grotesk:wght@400;700;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        body {
            background-color: #0a0a0a;
            font-family: 'Space Grotesk', sans-serif;
        }

        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }

        .card {
            background-color: #111;
            border: 1px solid #222;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        }

        .card:hover {
            transform: scale(1.03);
            box-shadow: 0 0 40px rgba(255, 255, 255, 0.04);
            border-color: #333;
        }

        .card--brutalist:hover {
            box-shadow: 0 0 40px rgba(220, 38, 38, 0.12);
            border-color: rgba(220, 38, 38, 0.4);
        }

        .card--editorial:hover {
            box-shadow: 0 0 40px rgba(251, 191, 36, 0.08);
            border-color: rgba(251, 191, 36, 0.3);
        }

        .card--glass:hover {
            box-shadow: 0 0 40px rgba(59, 130, 246, 0.12);
            border-color: rgba(59, 130, 246, 0.4);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center text-gray-200 antialiased">

    <main class="w-full max-w-5xl mx-auto px-6 py-20">

        <div class="text-center mb-16">
            <h1 class="text-5xl md:text-6xl font-black tracking-tight text-white mb-4">
                Design Style Previews
            </h1>
            <p class="text-lg text-gray-500 font-light">
                3 alternative directions for danielpetrica.com
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">

            {{-- Card 01: Brutalist --}}
            <a href="{{ route('preview.brutalist') }}"
               class="card card--brutalist rounded-xl p-8 block group">
                <span class="font-mono text-5xl font-bold text-red-500/30 group-hover:text-red-500/60 transition-colors duration-300 block mb-6">
                    01
                </span>
                <h2 class="text-xl font-bold text-white mb-2">
                    Brutalist Brutal
                </h2>
                <p class="text-sm text-gray-500 leading-relaxed">
                    Dark, raw, industrial aesthetic with sharp edges and a red accent palette.
                </p>
            </a>

            {{-- Card 02: Editorial --}}
            <a href="{{ route('preview.editorial') }}"
               class="card card--editorial rounded-xl p-8 block group">
                <span class="font-mono text-5xl font-bold text-amber-400/20 group-hover:text-amber-400/50 transition-colors duration-300 block mb-6">
                    02
                </span>
                <h2 class="text-xl font-bold text-white mb-2">
                    Editorial Minimal
                </h2>
                <p class="text-sm text-gray-500 leading-relaxed">
                    Warm light, spacious layout, serene and refined typographic composition.
                </p>
            </a>

            {{-- Card 03: Glass --}}
            <a href="{{ route('preview.glass') }}"
               class="card card--glass rounded-xl p-8 block group">
                <span class="font-mono text-5xl font-bold text-blue-500/25 group-hover:text-blue-500/55 transition-colors duration-300 block mb-6">
                    03
                </span>
                <h2 class="text-xl font-bold text-white mb-2">
                    Glassmorphism Dark
                </h2>
                <p class="text-sm text-gray-500 leading-relaxed">
                    Vibrant frosted glass panels over deep dark with an electric blue accent.
                </p>
            </a>

        </div>

        <div class="text-center">
            <a href="/"
               class="inline-block text-sm text-gray-600 hover:text-gray-300 transition-colors duration-200 font-mono tracking-wide">
                &larr; Back to live site
            </a>
        </div>

    </main>

</body>
</html>
