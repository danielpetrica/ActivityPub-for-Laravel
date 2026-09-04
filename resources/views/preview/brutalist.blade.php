<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daniel Petrica — Brutalist Preview</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;700;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        body {
            font-family: 'Space Grotesk', sans-serif;
            background: #0a0a0a;
            color: #fafafa;
            -webkit-font-smoothing: antialiased;
        }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        .font-grotesk { font-family: 'Space Grotesk', sans-serif; }
    </style>
</head>
<body>
    <main class="overflow-x-hidden w-full max-w-full bg-[#0a0a0a] min-h-screen">

        {{-- ========== NAVIGATION ========== --}}
        <nav class="fixed top-0 left-0 w-full z-50 bg-[#0a0a0a] border-b-2 border-[#ff2d2d]">
            <div class="max-w-7xl mx-auto px-6 md:px-12 flex items-center justify-between h-16">
                <a href="{{ route('home') }}" class="font-mono font-bold text-[#ff2d2d] text-xl tracking-tight">
                    DP
                </a>
                <div class="flex items-center gap-8">
                    <a href="{{ route('allposts') }}" class="font-mono text-sm text-[#fafafa]/70 hover:text-[#fafafa] transition-colors">
                        Blog
                    </a>
                    <a href="{{ route('tools.docker-traefik-generator') }}" class="font-mono text-sm text-[#fafafa]/70 hover:text-[#fafafa] transition-colors">
                        Strumenti
                    </a>
                    <a href="{{ route('search') }}" class="font-mono text-sm text-[#fafafa]/70 hover:text-[#fafafa] transition-colors">
                        Cerca
                    </a>
                </div>
            </div>
        </nav>

        {{-- ========== HERO (Attention) ========== --}}
        <section class="pt-40 pb-32 md:py-48">
            <div class="max-w-6xl mx-auto px-6 md:px-12">
                <h1 class="font-grotesk font-black text-[clamp(3rem,6vw,7rem)] leading-[0.95] tracking-tight mb-8">
                    Il PHP raccontato<br>in italiano.
                </h1>
                <p class="text-xl md:text-2xl text-[#fafafa]/60 max-w-2xl mb-12 leading-relaxed">
                    Tecnica, opinioni e guide per sviluppatori Laravel.
                </p>
                <div class="flex flex-wrap items-center gap-4">
                    <a href="{{ route('allposts') }}"
                       class="inline-flex items-center px-8 py-4 bg-[#ff2d2d] text-[#0a0a0a] font-grotesk font-bold text-lg hover:bg-[#ff2d2d]/90 transition-colors">
                        Leggi gli articoli
                    </a>
                    <a href="{{ route('tools') }}"
                       class="inline-flex items-center px-8 py-4 border-2 border-[#fafafa]/30 text-[#fafafa] font-grotesk font-bold text-lg hover:border-[#fafafa]/60 transition-colors">
                        Strumenti
                    </a>
                </div>
            </div>
        </section>

        {{-- ========== BENTO GRID (Interest) ========== --}}
        <section class="pb-32 md:py-48">
            <div class="max-w-7xl mx-auto px-6 md:px-12">
                <div class="bento-grid grid grid-cols-2 md:grid-cols-3 auto-rows-[200px] md:auto-rows-[240px] gap-4" style="grid-auto-flow: dense;">

                    {{-- Card 1: Large (col-span-2, row-span-2) --}}
                    <a href="{{ route('tags.show', 'laravel') }}"
                       class="bento-card group relative col-span-2 row-span-2 bg-[#111] border border-[#222] overflow-hidden flex flex-col justify-end hover:border-[#ff2d2d]/40 transition-colors">
                        <div class="absolute inset-0 overflow-hidden">
                            <img src="https://picsum.photos/seed/laravel/800/600"
                                 alt="Laravel guide"
                                 class="w-full h-full object-cover grayscale group-hover:scale-105 transition-transform duration-700 opacity-40 group-hover:opacity-60">
                        </div>
                        <div class="absolute top-6 left-6">
                            <span class="font-mono text-6xl md:text-8xl font-bold text-[#ff2d2d]/30">01</span>
                        </div>
                        <div class="relative z-10 p-6 md:p-8">
                            <h2 class="font-grotesk font-bold text-2xl md:text-3xl mb-2">Guide Laravel</h2>
                            <p class="text-[#fafafa]/50 text-sm md:text-base max-w-md">
                                Approfondimenti pratici su architetture, performance e best practice del framework PHP più amato.
                            </p>
                        </div>
                    </a>

                    {{-- Card 2: Small --}}
                    <a href="{{ route('tags.show', 'docker') }}"
                       class="bento-card group relative col-span-1 row-span-1 bg-[#111] border border-[#222] overflow-hidden flex flex-col justify-end hover:border-[#ff2d2d]/40 transition-colors">
                        <div class="absolute inset-0 overflow-hidden">
                            <img src="https://picsum.photos/seed/docker/400/300"
                                 alt="Docker and Traefik"
                                 class="w-full h-full object-cover grayscale group-hover:scale-105 transition-transform duration-700 opacity-30 group-hover:opacity-50">
                        </div>
                        <div class="relative z-10 p-5">
                            <h2 class="font-grotesk font-bold text-lg">Docker & Traefik</h2>
                        </div>
                    </a>

                    {{-- Card 3: Small --}}
                    <a href="{{ route('tags.show', 'php') }}"
                       class="bento-card group relative col-span-1 row-span-1 bg-[#111] border-l-2 border-l-[#ff2d2d] border border-[#222] overflow-hidden flex flex-col justify-end hover:border-[#ff2d2d]/60 transition-colors">
                        <div class="relative z-10 p-5">
                            <h2 class="font-grotesk font-bold text-lg">PHP 8.5</h2>
                            <p class="text-[#fafafa]/40 text-xs mt-1 font-mono">Novità e features</p>
                        </div>
                    </a>

                    {{-- Card 4: Medium (col-span-2, row-span-1) --}}
                    <a href="{{ route('tools.docker-traefik-generator') }}"
                       class="bento-card group relative col-span-2 row-span-1 bg-[#111] border border-[#222] overflow-hidden flex items-center hover:border-[#ff2d2d]/40 transition-colors">
                        <div class="p-6 flex items-center gap-6 w-full">
                            <div class="font-mono text-[#ff2d2d] text-3xl font-bold shrink-0">04</div>
                            <div>
                                <h2 class="font-grotesk font-bold text-xl">Strumenti Developer</h2>
                                <p class="text-[#fafafa]/40 text-sm mt-1">Generatore Docker Compose, encode HTML e altri utiliti.</p>
                            </div>
                        </div>
                    </a>

                    {{-- Card 5: Small --}}
                    <a href="{{ route('tags.show', 'freelance') }}"
                       class="bento-card group relative col-span-1 row-span-1 bg-[#111] border border-[#222] overflow-hidden flex flex-col justify-end hover:border-[#ff2d2d]/40 transition-colors">
                        <div class="absolute inset-0 overflow-hidden">
                            <img src="https://picsum.photos/seed/freelance/400/300"
                                 alt="Freelance"
                                 class="w-full h-full object-cover grayscale group-hover:scale-105 transition-transform duration-700 opacity-30 group-hover:opacity-50">
                        </div>
                        <div class="relative z-10 p-5">
                            <h2 class="font-grotesk font-bold text-lg">Freelance</h2>
                        </div>
                    </a>

                </div>
            </div>
        </section>

        {{-- ========== GSAP SCROLL SECTION (Desire) ========== --}}
        <section class="pinned-section relative pb-32 md:py-48" style="min-height: 300vh;">
            <div class="max-w-7xl mx-auto px-6 md:px-12 relative">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-12 md:gap-24">

                    {{-- Pinned left text --}}
                    <div class="relative" style="height: 100%;">
                        <div class="pin-target sticky top-48">
                            <h2 class="reveal-text font-grotesk font-black text-[clamp(2rem,4vw,4rem)] leading-[1.05] tracking-tight opacity-30">
                                Costruire software,<br>un post alla volta.
                            </h2>
                        </div>
                    </div>

                    {{-- Scrolling right cards --}}
                    <div class="flex flex-col gap-6 pt-24 md:pt-48 pb-[50vh]">
                        @foreach([
                            ['title' => 'Ottimizzare le query Eloquent', 'excerpt' => 'N+1 queries, eager loading e strategie per applicazioni performanti.', 'tag' => 'laravel', 'seed' => 'article1'],
                            ['title' => 'Docker per sviluppatori PHP', 'excerpt' => 'Da zero a produzione: containerizzazione di un\'app Laravel.', 'tag' => 'docker', 'seed' => 'article2'],
                            ['title' => 'Filament: pannelli admin in pochi minuti', 'excerpt' => 'Configurare un admin panel completo senza_scrivere JavaScript.', 'tag' => 'laravel', 'seed' => 'article3'],
                        ] as $post)
                            <a href="{{ route('tags.show', $post['tag']) }}"
                               class="group block bg-[#111] border border-[#222] p-6 md:p-8 hover:border-[#ff2d2d]/40 transition-colors">
                                <div class="flex items-start gap-4">
                                    <div class="shrink-0 w-12 h-12 overflow-hidden bg-[#1a1a1a]">
                                        <img src="https://picsum.photos/seed/{{ $post['seed'] }}/96/96"
                                             alt=""
                                             class="w-full h-full object-cover grayscale opacity-60 group-hover:scale-105 transition-transform duration-500">
                                    </div>
                                    <div>
                                        <h3 class="font-grotesk font-bold text-lg mb-1">{{ $post['title'] }}</h3>
                                        <p class="text-[#fafafa]/40 text-sm">{{ $post['excerpt'] }}</p>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                </div>
            </div>
        </section>

        {{-- ========== NEWSLETTER CTA (Action) ========== --}}
        <section class="bg-[#ff2d2d] py-32 md:py-48">
            <div class="max-w-3xl mx-auto px-6 md:px-12 text-center">
                <h2 class="font-grotesk font-black text-[clamp(2rem,5vw,4rem)] leading-tight tracking-tight text-[#0a0a0a] mb-6">
                    Resta aggiornato.
                </h2>
                <p class="text-[#0a0a0a]/60 text-lg mb-10 max-w-lg mx-auto">
                    Guide, tutorial e opinioni direttamente nella tua casella di posta. Niente spam.
                </p>
                <form action="{{ route('newsletter.subscribe') }}" method="POST" class="flex flex-col sm:flex-row gap-3 max-w-xl mx-auto">
                    @csrf
                    <input type="email"
                           name="email"
                           required
                           placeholder="la-tua@email.com"
                           class="flex-1 px-6 py-4 bg-[#0a0a0a] text-[#fafafa] font-mono text-sm placeholder:text-[#fafafa]/30 border-2 border-[#0a0a0a] focus:border-[#0a0a0a] focus:outline-none focus:ring-2 focus:ring-[#0a0a0a]/20 transition-all">
                    <button type="submit"
                            class="px-8 py-4 bg-[#0a0a0a] text-[#fafafa] font-grotesk font-bold text-sm hover:bg-[#1a1a1a] transition-colors whitespace-nowrap">
                        Iscriviti
                    </button>
                </form>
            </div>
        </section>

        {{-- ========== FOOTER ========== --}}
        <footer class="border-t-2 border-[#ff2d2d] py-12 md:py-16">
            <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row items-center justify-between gap-4">
                <span class="font-mono text-sm text-[#fafafa]/50">Daniel Petrica</span>
                <span class="font-mono text-xs text-[#fafafa]/30">Built with Laravel</span>
            </div>
        </footer>

    </main>

    {{-- GSAP CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>

    {{-- GSAP Init Script --}}
    <script>
        gsap.registerPlugin(ScrollTrigger);

        // Text reveal on pinned section
        gsap.to('.reveal-text', {
            opacity: 1,
            scrollTrigger: {
                trigger: '.pinned-section',
                start: 'top top',
                end: 'bottom bottom',
                scrub: true,
                pin: '.pin-target',
            }
        });

        // Bento cards fade in
        gsap.from('.bento-card', {
            y: 60,
            opacity: 0,
            duration: 0.8,
            stagger: 0.15,
            scrollTrigger: {
                trigger: '.bento-grid',
                start: 'top 80%',
            }
        });
    </script>

</body>
</html>
