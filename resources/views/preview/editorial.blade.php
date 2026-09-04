<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daniel Petrica — Editorial Preview</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        body {
            font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .font-serif { font-family: 'DM Serif Display', Georgia, serif; }
        .font-body { font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif; }
        .gallery-scroll::-webkit-scrollbar { display: none; }
        .gallery-scroll { -ms-overflow-style: none; scrollbar-width: none; }
        .reveal-word {
            display: inline;
            opacity: 0.1;
        }
    </style>
</head>
<body>
    <main class="overflow-x-hidden w-full max-w-full min-h-screen" style="background: #fdfbf7;">

        {{-- ========== NAVIGATION ========== --}}
        <nav class="fixed top-8 left-1/2 -translate-x-1/2 z-50 rounded-full bg-white/80 backdrop-blur-md shadow-lg border border-neutral-200/50 px-8 py-3 flex items-center gap-8">
            <a href="{{ route('welcome') }}" class="font-serif text-xl" style="color: #b8860b;">
                DP
            </a>
            <a href="{{ route('posts.index') }}"
               class="font-body text-sm font-medium uppercase tracking-widest transition-colors"
               style="font-variant: small-caps; color: #8a8070;"
               onmouseover="this.style.color='#b8860b'"
               onmouseout="this.style.color='#8a8070'">
                Blog
            </a>
            <a href="{{ route('tools.docker-traefik-generator') }}"
               class="font-body text-sm font-medium uppercase tracking-widest transition-colors"
               style="font-variant: small-caps; color: #8a8070;"
               onmouseover="this.style.color='#b8860b'"
               onmouseout="this.style.color='#8a8070'">
                Strumenti
            </a>
            <a href="{{ route('search') }}"
               class="font-body text-sm font-medium uppercase tracking-widest transition-colors"
               style="font-variant: small-caps; color: #8a8070;"
               onmouseover="this.style.color='#b8860b'"
               onmouseout="this.style.color='#8a8070'">
                Cerca
            </a>
        </nav>

        {{-- ========== HERO (Attention) ========== --}}
        <section class="relative pt-48 pb-32 md:py-48">
            {{-- Subtle radial gradient behind heading --}}
            <div class="absolute inset-0 pointer-events-none" style="background: radial-gradient(ellipse at center, rgba(184,134,11,0.05) 0%, transparent 70%);"></div>

            <div class="relative max-w-4xl mx-auto px-6 md:px-12 text-center">
                <p class="font-body uppercase tracking-[0.25em] text-xs mb-6" style="color: #8a8070;">
                    Tecnica, opinioni e guide
                </p>

                <h1 class="font-serif leading-tight mb-6" style="font-size: clamp(2.5rem, 5vw, 5rem); color: #1a1a1a;">
                    Il PHP raccontato in italiano.
                </h1>

                <p class="font-body text-lg max-w-2xl mx-auto mb-12" style="color: #8a8070;">
                    Costruire software, un post alla volta.
                </p>

                <div class="flex flex-wrap items-center justify-center gap-4">
                    <a href="{{ route('posts.index') }}"
                       class="inline-flex items-center px-8 py-4 rounded-lg font-body font-medium text-sm transition-colors"
                       style="background: #b8860b; color: #fff;"
                       onmouseover="this.style.opacity='0.9'"
                       onmouseout="this.style.opacity='1'">
                        Leggi gli articoli
                    </a>
                    <a href="{{ route('tools.docker-traefik-generator') }}"
                       class="inline-flex items-center px-8 py-4 rounded-lg font-body font-medium text-sm transition-colors"
                       style="background: transparent; border: 1px solid #b8860b; color: #b8860b;"
                       onmouseover="this.style.background='rgba(184,134,11,0.05)'"
                       onmouseout="this.style.background='transparent'">
                        Scopri gli strumenti
                    </a>
                </div>
            </div>
        </section>

        {{-- ========== HORIZONTAL SCROLL GALLERY (Interest) ========== --}}
        <section class="gallery-section pb-32 md:py-48">
            <div class="max-w-7xl mx-auto px-6 md:px-12">
                <h2 class="font-serif text-4xl md:text-5xl mb-12" style="color: #1a1a1a;">
                    Ultimi articoli
                </h2>

                <div class="gallery-scroll flex gap-6 overflow-x-auto snap-x snap-mandatory pb-4">
                    @php
                        $galleryPosts = [
                            ['title' => 'Guide Laravel 11', 'slug' => 'guide-laravel-11', 'seed' => 'laravel11'],
                            ['title' => 'Docker in Produzione', 'slug' => 'docker-in-produzione', 'seed' => 'dockerprod'],
                            ['title' => 'PHP 8.5 Novità', 'slug' => 'php-85-novita', 'seed' => 'php85'],
                            ['title' => 'Freelance Tips', 'slug' => 'freelance-tips', 'seed' => 'freelancetips'],
                        ];
                    @endphp

                    @foreach ($galleryPosts as $post)
                        <a href="{{ route('posts.show', $post['slug']) }}"
                           class="gallery-card snap-start shrink-0 w-64 md:w-72 bg-white rounded-2xl shadow-sm overflow-hidden transition-shadow hover:shadow-md"
                           style="border: 1px solid #e8e0d0;">
                            <div class="aspect-[4/5] overflow-hidden">
                                <img src="https://picsum.photos/seed/{{ $post['seed'] }}/600/800"
                                     alt="{{ $post['title'] }}"
                                     class="w-full h-full object-cover transition-transform duration-500 hover:scale-105"
                                     style="filter: sepia(20%);">
                            </div>
                            <div class="p-5">
                                <h3 class="font-serif text-lg mb-1" style="color: #1a1a1a;">
                                    {{ $post['title'] }}
                                </h3>
                                <p class="font-body text-xs" style="color: #8a8070;">
                                    2025
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ========== GSAP TEXT REVEAL (Desire) ========== --}}
        <section class="py-32 md:py-48" style="min-height: 100vh;">
            <div class="max-w-4xl mx-auto px-6 md:px-12">
                <p class="font-serif leading-relaxed" style="font-size: clamp(1.5rem, 3vw, 2.5rem); color: #1a1a1a;">
                    @php
                        $revealText = "Ogni articolo nasce dall'esperienza diretta. Dalla configurazione di un server VPS alla scrittura di codice che scala. Condivido ciò che immodo, nel modo più semplice possibile.";
                        $words = explode(' ', $revealText);
                    @endphp
                    @foreach ($words as $index => $word)
                        <span class="reveal-word">{{ $word }}</span>
                        @if ($index < count($words) - 1)
                            <span>&nbsp;</span>
                        @endif
                    @endforeach
                </p>
            </div>
        </section>

        {{-- ========== NEWSLETTER CTA (Action) ========== --}}
        <section style="background: #f5f0e8;" class="py-32 md:py-48">
            <div class="max-w-2xl mx-auto px-6 md:px-12 text-center">
                <h2 class="font-serif text-3xl md:text-4xl mb-4" style="color: #1a1a1a;">
                    Resta aggiornato, senza rumore.
                </h2>
                <p class="font-body text-base mb-10" style="color: #8a8070;">
                    Un'email al mese. Solo articoli utili.
                </p>
                <form action="{{ route('newsletter.subscribe') }}" method="POST" class="flex flex-col sm:flex-row gap-3 max-w-md mx-auto">
                    @csrf
                    <input type="email"
                           name="email"
                           required
                           placeholder="la-tua@email.com"
                           class="flex-1 px-5 py-3.5 rounded-lg font-body text-sm outline-none transition-colors"
                           style="background: #fff; border: 1px solid #e8e0d0; color: #1a1a1a;"
                           onfocus="this.style.borderColor='#b8860b'"
                           onblur="this.style.borderColor='#e8e0d0'">
                    <button type="submit"
                            class="px-8 py-3.5 rounded-lg font-body font-medium text-sm transition-colors"
                            style="background: #b8860b; color: #fff;"
                            onmouseover="this.style.opacity='0.9'"
                            onmouseout="this.style.opacity='1'">
                        Iscriviti
                    </button>
                </form>
            </div>
        </section>

        {{-- ========== FOOTER ========== --}}
        <footer class="py-12 md:py-16 text-center" style="background: #fdfbf7; border-top: 1px solid #e8e0d0;">
            <div class="max-w-7xl mx-auto px-6 md:px-12">
                <p class="font-serif text-lg mb-1" style="color: #1a1a1a;">
                    Daniel Petrica
                </p>
                <p class="font-body text-xs" style="color: #8a8070;">
                    Costruito con Laravel
                </p>
            </div>
        </footer>

    </main>

    {{-- GSAP CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>

    {{-- GSAP Init Script --}}
    <script>
        gsap.registerPlugin(ScrollTrigger);

        // Word-by-word reveal
        const words = document.querySelectorAll('.reveal-word');
        words.forEach((word, i) => {
            gsap.to(word, {
                opacity: 1,
                scrollTrigger: {
                    trigger: word,
                    start: 'top 85%',
                    end: 'top 60%',
                    scrub: true,
                }
            });
        });

        // Cards slide in
        gsap.from('.gallery-card', {
            y: 40,
            opacity: 0,
            duration: 0.6,
            stagger: 0.1,
            scrollTrigger: {
                trigger: '.gallery-section',
                start: 'top 80%',
            }
        });
    </script>

</body>
</html>
