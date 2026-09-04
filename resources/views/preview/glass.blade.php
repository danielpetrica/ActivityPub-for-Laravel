<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daniel Petrica — Glassmorphism Preview</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500&family=Outfit:wght@300;600;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            color: #e8eaff;
            background: #050510;
        }

        .font-outfit { font-family: 'Outfit', ui-sans-serif, system-ui, sans-serif; }
        .font-body { font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif; }

        .hero-glow {
            position: absolute;
            width: 28rem;
            height: 28rem;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.25) 0%, transparent 70%);
            filter: blur(60px);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
        }

        @keyframes float1 {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(40px, -30px); }
        }
        @keyframes float2 {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(-30px, 40px); }
        }
        @keyframes float3 {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(20px, 20px); }
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            transition: border-color 0.4s ease, box-shadow 0.4s ease;
        }
        .glass-card:hover {
            border-color: rgba(59, 130, 246, 0.3);
            box-shadow: 0 0 32px rgba(59, 130, 246, 0.1);
        }

        .glass-card img {
            transition: transform 0.6s ease;
        }
        .glass-card:hover img {
            transform: scale(1.05);
        }

        .bento-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-auto-flow: dense;
            gap: 1.5rem;
        }

        @media (max-width: 768px) {
            .bento-grid {
                grid-template-columns: 1fr;
            }
            .bento-grid .glass-card {
                grid-column: span 1 !important;
                grid-row: span 1 !important;
            }
        }

        .accent-glow {
            box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.4), 0 0 24px rgba(59, 130, 246, 0.15);
        }

        input[type="email"] {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #e8eaff;
            outline: none;
            transition: border-color 0.3s ease;
        }
        input[type="email"]:focus {
            border-color: rgba(59, 130, 246, 0.5);
        }
        input[type="email"]::placeholder {
            color: rgba(232, 234, 255, 0.35);
        }
    </style>
</head>
<body>
    <main class="overflow-x-hidden w-full max-w-full min-h-screen" style="background: #050510;">

        {{-- ========== NAVIGATION ========== --}}
        <nav class="fixed top-6 left-1/2 -translate-x-1/2 z-50 rounded-full bg-white/5 backdrop-blur-2xl border border-white/10 shadow-2xl px-8 py-3 flex items-center gap-8">
            <a href="{{ route('welcome') }}" class="font-outfit text-xl font-extrabold" style="color: #3b82f6; text-shadow: 0 0 20px rgba(59, 130, 246, 0.5);">
                DP
            </a>
            <a href="{{ route('posts.index') }}"
               class="font-body text-sm transition-colors duration-300"
               style="color: #e8eaff;"
               onmouseover="this.style.color='#3b82f6'"
               onmouseout="this.style.color='#e8eaff'">
                Blog
            </a>
            <a href="{{ route('tools.docker-traefik-generator') }}"
               class="font-body text-sm transition-colors duration-300"
               style="color: #e8eaff;"
               onmouseover="this.style.color='#3b82f6'"
               onmouseout="this.style.color='#e8eaff'">
                Strumenti
            </a>
            <a href="{{ route('search') }}"
               class="font-body text-sm transition-colors duration-300"
               style="color: #e8eaff;"
               onmouseover="this.style.color='#3b82f6'"
               onmouseout="this.style.color='#e8eaff'">
                Cerca
            </a>
        </nav>

        {{-- ========== HERO (Attention) ========== --}}
        <section class="hero relative flex flex-col items-center justify-center min-h-screen px-6 overflow-hidden">
            {{-- Animated gradient orbs --}}
            <div class="absolute top-1/4 left-1/4 w-96 h-96 rounded-full bg-blue-500/20 blur-[128px]" style="animation: float1 8s ease-in-out infinite;"></div>
            <div class="absolute bottom-1/4 right-1/4 w-80 h-80 rounded-full bg-purple-500/15 blur-[128px]" style="animation: float2 10s ease-in-out infinite;"></div>
            <div class="absolute top-1/2 left-1/2 w-64 h-64 rounded-full bg-cyan-500/10 blur-[128px]" style="animation: float3 12s ease-in-out infinite;"></div>

            {{-- Glow behind heading --}}
            <div class="hero-glow"></div>

            {{-- Heading --}}
            <h1 class="font-outfit font-extrabold text-center max-w-5xl mx-auto relative z-10" style="font-size: clamp(3rem, 5vw, 6rem); color: #e8eaff; line-height: 1.1;">
                Il PHP raccontato in italiano.
            </h1>
            <p class="font-body text-xl text-center mt-6 max-w-2xl relative z-10" style="color: rgba(232, 234, 255, 0.7);">
                Tecnica, opinioni e guide per sviluppatori Laravel.
            </p>

            {{-- CTAs --}}
            <div class="flex flex-wrap items-center justify-center gap-4 mt-10 relative z-10">
                <a href="{{ route('posts.index') }}"
                   class="inline-block px-8 py-3 rounded-full text-sm font-medium transition-all duration-300"
                   style="background: #3b82f6; color: #fff; box-shadow: 0 0 24px rgba(59, 130, 246, 0.3);"
                   onmouseover="this.style.boxShadow='0 0 40px rgba(59, 130, 246, 0.5)'"
                   onmouseout="this.style.boxShadow='0 0 24px rgba(59, 130, 246, 0.3)'">
                    Leggi gli articoli
                </a>
                <a href="{{ route('tools.docker-traefik-generator') }}"
                   class="inline-block px-8 py-3 rounded-full text-sm font-medium border transition-all duration-300"
                   style="border-color: rgba(255, 255, 255, 0.15); color: #e8eaff; background: rgba(255, 255, 255, 0.03);"
                   onmouseover="this.style.borderColor='rgba(59, 130, 246, 0.4)'"
                   onmouseout="this.style.borderColor='rgba(255, 255, 255, 0.15)'">
                    Strumenti
                </a>
            </div>
        </section>

        {{-- ========== GLASS BENTO GRID (Interest) ========== --}}
        <section class="py-32 md:py-48 px-6 max-w-7xl mx-auto">
            <div class="bento-grid">

                {{-- Card 1: Guide Laravel (col-span-2, row-span-2) --}}
                <a href="{{ route('posts.index') }}" class="glass-card group relative overflow-hidden col-span-2 row-span-2 min-h-[320px] md:min-h-[460px]">
                    <div class="absolute inset-0 overflow-hidden">
                        <img src="https://picsum.photos/seed/laravel-guide/900/600"
                             alt="Guide Laravel"
                             class="w-full h-full object-cover opacity-80 grayscale group-hover:opacity-100"
                             loading="lazy">
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-t from-[#050510] via-[#050510]/40 to-transparent"></div>
                    <div class="relative z-10 flex flex-col justify-end p-8 h-full">
                        <span class="font-body text-xs uppercase tracking-widest mb-2" style="color: #3b82f6;">Guide</span>
                        <h3 class="font-outfit font-semibold text-2xl md:text-3xl" style="color: #e8eaff;">Guide Laravel</h3>
                        <p class="font-body text-sm mt-2" style="color: rgba(232, 234, 255, 0.6);">Approfondimenti, best practice e architetture per il tuo prossimo progetto.</p>
                    </div>
                </a>

                {{-- Card 2: Docker & Traefik --}}
                <a href="{{ route('tools.docker-traefik-generator') }}" class="glass-card group p-6 flex flex-col justify-between min-h-[200px]">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4" style="background: rgba(59, 130, 246, 0.15);">
                        <svg class="w-6 h-6" style="color: #3b82f6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-outfit font-semibold text-lg" style="color: #e8eaff;">Docker &amp; Traefik</h3>
                        <p class="font-body text-sm mt-1" style="color: rgba(232, 234, 255, 0.5);">Generatore di configurazioni per il deploy.</p>
                    </div>
                </a>

                {{-- Card 3: PHP 8.5 (accent glow border) --}}
                <a href="{{ route('posts.index') }}" class="glass-card accent-glow group p-6 flex flex-col justify-between min-h-[200px]">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4" style="background: rgba(59, 130, 246, 0.15);">
                        <svg class="w-6 h-6" style="color: #3b82f6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-outfit font-semibold text-lg" style="color: #e8eaff;">PHP 8.5</h3>
                        <p class="font-body text-sm mt-1" style="color: rgba(232, 234, 255, 0.5);">Novit&agrave;, sfide e prerogative.</p>
                    </div>
                </a>

                {{-- Card 4: Strumenti Developer (col-span-2) --}}
                <a href="{{ route('tools.docker-traefik-generator') }}" class="glass-card group p-6 col-span-2 flex items-center gap-6 min-h-[160px]">
                    <div class="w-14 h-14 rounded-xl flex items-center justify-center shrink-0" style="background: rgba(59, 130, 246, 0.15);">
                        <svg class="w-7 h-7" style="color: #3b82f6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.42 15.17l-5.384 3.15A1 1 0 014 17.38V5.62a1 1 0 012.036-.82l5.384 3.15M11.42 15.17l5.384 3.15A1 1 0 0018.86 17.38V5.62a1 1 0 00-2.036-.82l-5.384 3.15M11.42 15.17V21"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-outfit font-semibold text-lg" style="color: #e8eaff;">Strumenti Developer</h3>
                        <p class="font-body text-sm mt-1" style="color: rgba(232, 234, 255, 0.5);">Utilit&agrave; open source per il workflow quotidiano.</p>
                    </div>
                </a>

                {{-- Card 5: Freelance --}}
                <a href="{{ route('welcome') }}" class="glass-card group p-6 flex flex-col justify-between min-h-[160px]">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4" style="background: rgba(59, 130, 246, 0.15);">
                        <svg class="w-6 h-6" style="color: #3b82f6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-outfit font-semibold text-lg" style="color: #e8eaff;">Freelance</h3>
                        <p class="font-body text-sm mt-1" style="color: rgba(232, 234, 255, 0.5);">Consulenze e collaborazioni.</p>
                    </div>
                </a>

            </div>
        </section>

        {{-- ========== GSAP PARALLAX SECTION (Desire) ========== --}}
        <section class="parallax-section relative py-32 md:py-48 px-6 overflow-hidden" style="min-height: 100vh;">
            <div class="relative z-10 max-w-7xl mx-auto">
                <h2 class="font-outfit font-semibold text-center max-w-4xl mx-auto mb-16" style="font-size: clamp(2rem, 4vw, 3.5rem); color: #e8eaff;">
                    Costruire software, un post alla volta.
                </h2>
            </div>

            {{-- Floating parallax glass cards --}}
            <div class="relative max-w-7xl mx-auto" style="height: 600px;">
                <div class="parallax-card glass-card absolute top-0 left-[5%] w-72 p-6 z-10">
                    <span class="font-body text-xs uppercase tracking-widest" style="color: #3b82f6;">Performance</span>
                    <h3 class="font-outfit font-semibold text-lg mt-2" style="color: #e8eaff;">Laravel Octane</h3>
                    <p class="font-body text-sm mt-2" style="color: rgba(232, 234, 255, 0.5);">Server persistenti per un PHP che non si ferma mai.</p>
                </div>

                <div class="parallax-card glass-card absolute top-32 right-[10%] w-72 p-6 z-20">
                    <span class="font-body text-xs uppercase tracking-widest" style="color: #3b82f6;">Infrastruttura</span>
                    <h3 class="font-outfit font-semibold text-lg mt-2" style="color: #e8eaff;">Docker Swarm</h3>
                    <p class="font-body text-sm mt-2" style="color: rgba(232, 234, 255, 0.5);">Orchestrazione semplice per deploy riproducibili.</p>
                </div>

                <div class="parallax-card glass-card absolute bottom-10 left-[25%] w-72 p-6 z-30">
                    <span class="font-body text-xs uppercase tracking-widest" style="color: #3b82f6;">Concurrency</span>
                    <h3 class="font-outfit font-semibold text-lg mt-2" style="color: #e8eaff;">PHP Fibers</h3>
                    <p class="font-body text-sm mt-2" style="color: rgba(232, 234, 255, 0.5);">Concorrenza cooperativa senza async/await.</p>
                </div>
            </div>
        </section>

        {{-- ========== NEWSLETTER CTA (Action) ========== --}}
        <section class="py-32 md:py-48 bg-white/5 backdrop-blur-xl border-y border-white/10">
            <div class="max-w-2xl mx-auto px-6 text-center">
                <h2 class="font-outfit font-semibold mb-4" style="font-size: clamp(1.75rem, 3vw, 2.5rem); color: #e8eaff;">
                    Resta aggiornato.
                </h2>
                <p class="font-body text-sm mb-8" style="color: rgba(232, 234, 255, 0.5);">
                    Un'email al mese. Niente spam, solo contenuti utili.
                </p>
                <form action="{{ route('newsletter.subscribe') }}" method="POST" class="flex flex-col sm:flex-row items-center gap-3 max-w-md mx-auto">
                    @csrf
                    <input type="email"
                           name="email"
                           required
                           placeholder="La tua email"
                           class="flex-1 w-full px-5 py-3 rounded-full text-sm font-body">
                    <button type="submit"
                            class="shrink-0 px-7 py-3 rounded-full text-sm font-medium transition-all duration-300"
                            style="background: #3b82f6; color: #fff; box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);"
                            onmouseover="this.style.boxShadow='0 0 32px rgba(59, 130, 246, 0.5)'"
                            onmouseout="this.style.boxShadow='0 0 20px rgba(59, 130, 246, 0.3)'">
                        Iscriviti
                    </button>
                </form>
            </div>
        </section>

        {{-- ========== FOOTER ========== --}}
        <footer class="relative py-16 px-6" style="background: #030308;">
            {{-- Blue accent line --}}
            <div class="absolute top-0 left-0 right-0 h-px" style="background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.4), transparent);"></div>

            <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-6">
                <div>
                    <span class="font-outfit font-semibold text-lg" style="color: #e8eaff;">Daniel Petrica</span>
                    <p class="font-body text-xs mt-1" style="color: rgba(232, 234, 255, 0.35);">Costruito con Laravel</p>
                </div>

                <div class="flex items-center gap-6">
                    <a href="{{ route('posts.index') }}"
                       class="font-body text-xs transition-colors duration-300"
                       style="color: rgba(232, 234, 255, 0.4);"
                       onmouseover="this.style.color='#3b82f6'"
                       onmouseout="this.style.color='rgba(232, 234, 255, 0.4)'">
                        Blog
                    </a>
                    <a href="{{ route('tools.docker-traefik-generator') }}"
                       class="font-body text-xs transition-colors duration-300"
                       style="color: rgba(232, 234, 255, 0.4);"
                       onmouseover="this.style.color='#3b82f6'"
                       onmouseout="this.style.color='rgba(232, 234, 255, 0.4)'">
                        Strumenti
                    </a>
                    <a href="{{ route('search') }}"
                       class="font-body text-xs transition-colors duration-300"
                       style="color: rgba(232, 234, 255, 0.4);"
                       onmouseover="this.style.color='#3b82f6'"
                       onmouseout="this.style.color='rgba(232, 234, 255, 0.4)'">
                        Cerca
                    </a>
                </div>
            </div>
        </footer>

    </main>

    {{-- ========== GSAP SCRIPTS ========== --}}
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
    <script>
        gsap.registerPlugin(ScrollTrigger);

        // Bento cards stagger in
        gsap.from('.glass-card', {
            y: 60,
            opacity: 0,
            duration: 0.8,
            stagger: 0.12,
            scrollTrigger: { trigger: '.bento-grid', start: 'top 80%' }
        });

        // Parallax floating cards
        document.querySelectorAll('.parallax-card').forEach((card, i) => {
            gsap.to(card, {
                y: -100 * (i + 1),
                scrollTrigger: {
                    trigger: '.parallax-section',
                    start: 'top bottom',
                    end: 'bottom top',
                    scrub: true
                }
            });
        });

        // Hero heading glow pulse on scroll
        gsap.to('.hero-glow', {
            opacity: 0.8,
            scale: 1.1,
            scrollTrigger: {
                trigger: '.hero',
                start: 'top top',
                end: 'bottom top',
                scrub: true
            }
        });
    </script>
</body>
</html>
