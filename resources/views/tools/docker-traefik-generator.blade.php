<x-layouts.app
    title="Docker Compose & Traefik Config Generator - Daniel Petrica"
    description="Free online tool to generate production-ready Docker Compose and Traefik configurations. Supports Laravel Octane, FrankenPHP, SSL via Cloudflare or Let's Encrypt, Redis, MySQL, and more."
    :structuredData="$structuredData"
>
    <x-layouts.hero
        title="Docker & Traefik Config Generator"
        excerpt="Generate production-ready Docker Compose and Traefik configurations.
        Fill in the wizard and download your stack files instantly. No data sent to any server."
        section="Tools"
    />

    {{-- SEO copy: visible to crawlers, not rendered by Vue --}}
    <section class="py-12 bg-neutral-50 border-b border-neutral-100">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-3 gap-8">
                <div>
                    <h2 class="text-lg font-bold text-neutral-900 mb-2">What this tool generates</h2>
                    <p class="text-sm text-neutral-600 leading-relaxed">
                        A complete Docker-based production stack: a <strong>Traefik reverse proxy</strong> with automatic SSL,
                        and your application's <strong>Docker Compose</strong> file with all supporting services — database,
                        Redis, queue workers, schedulers, and optional backups.
                    </p>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-neutral-900 mb-2">Supported application types</h2>
                    <ul class="text-sm text-neutral-600 space-y-1 leading-relaxed">
                        <li>• <strong>Laravel Octane</strong> — FrankenPHP, Swoole, or RoadRunner</li>
                        <li>• <strong>Static websites</strong> — served via halverneus/static-file-server</li>
                        <li>• <strong>Generic services</strong> — any Docker image behind Traefik</li>
                    </ul>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-neutral-900 mb-2">SSL certificate options</h2>
                    <ul class="text-sm text-neutral-600 space-y-1 leading-relaxed">
                        <li>• <strong>Cloudflare DNS challenge</strong> — wildcard certs, no port 80 needed</li>
                        <li>• <strong>Let's Encrypt HTTP challenge</strong> — standard ACME, port 80 required</li>
                    </ul>
                </div>
            </div>

            <div class="mt-8 grid md:grid-cols-2 gap-8">
                <div>
                    <h2 class="text-lg font-bold text-neutral-900 mb-2">Output files</h2>
                    <ul class="text-sm text-neutral-600 space-y-1 leading-relaxed">
                        <li>• <code class="bg-neutral-100 px-1 rounded text-xs">traefik/compose.yml</code> — Traefik Docker Compose stack</li>
                        <li>• <code class="bg-neutral-100 px-1 rounded text-xs">traefik/traefik.yml</code> — Traefik static configuration</li>
                        <li>• <code class="bg-neutral-100 px-1 rounded text-xs">app/compose.yml</code> — Application Docker Compose stack</li>
                        <li>• <code class="bg-neutral-100 px-1 rounded text-xs">app/docker/Dockerfile</code> — Multi-stage Laravel build (if Laravel)</li>
                        <li>• <code class="bg-neutral-100 px-1 rounded text-xs">app/run.sh</code> — Zero-downtime deploy script (if Laravel)</li>
                        <li>• <code class="bg-neutral-100 px-1 rounded text-xs">app/.env</code> — Minimum required environment variables</li>
                    </ul>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-neutral-900 mb-2">Optional services</h2>
                    <ul class="text-sm text-neutral-600 space-y-1 leading-relaxed">
                        <li>• MySQL (8.0–9.x) or PostgreSQL (16–18) database with health checks</li>
                        <li>• Redis cache and queue backend</li>
                        <li>• Laravel Horizon or standard queue worker</li>
                        <li>• Laravel scheduler (<code class="bg-neutral-100 px-1 rounded text-xs">schedule:work</code>)</li>
                        <li>• Laravel Pulse (check + work services)</li>
                        <li>• Nightwatch agent for monitoring</li>
                        <li>• Automated DB backups via <code class="bg-neutral-100 px-1 rounded text-xs">tiredofit/db-backup</code></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div id="app">
                <!-- Vue app mounts here -->
                <div class="text-center text-neutral-400 py-12">Loading generator...</div>
            </div>
        </div>
    </section>

    {{-- FAQ section for SEO long-tail keywords --}}
    <section class="py-12 bg-neutral-50 border-t border-neutral-100">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-neutral-900 mb-8">Frequently Asked Questions</h2>
            <div class="grid md:grid-cols-2 gap-6" itemscope itemtype="https://schema.org/FAQPage">
                <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                    <h3 class="font-semibold text-neutral-900 mb-1" itemprop="name">Is this tool free to use?</h3>
                    <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                        <p class="text-sm text-neutral-600" itemprop="text">
                            Yes, completely free. The generator runs entirely in your browser — no account required,
                            no data is sent to any server. All configuration is generated client-side and downloaded directly to your machine.
                        </p>
                    </div>
                </div>
                <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                    <h3 class="font-semibold text-neutral-900 mb-1" itemprop="name">What is Traefik and why use it?</h3>
                    <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                        <p class="text-sm text-neutral-600" itemprop="text">
                            Traefik is a modern reverse proxy and load balancer built for containerised environments.
                            Unlike Nginx or Apache, it auto-discovers Docker containers via labels, provisions SSL certificates
                            automatically through Let's Encrypt or Cloudflare, and requires zero manual config file changes when deploying new services.
                        </p>
                    </div>
                </div>
                <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                    <h3 class="font-semibold text-neutral-900 mb-1" itemprop="name">Cloudflare DNS challenge vs Let's Encrypt HTTP challenge — which should I choose?</h3>
                    <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                        <p class="text-sm text-neutral-600" itemprop="text">
                            Use the <strong>Cloudflare DNS challenge</strong> if your domain is proxied through Cloudflare — it supports wildcard certificates
                            and does not require port 80 to be publicly accessible. Use the <strong>Let's Encrypt HTTP challenge</strong> if you manage DNS elsewhere
                            and port 80 is open on your server.
                        </p>
                    </div>
                </div>
                <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                    <h3 class="font-semibold text-neutral-900 mb-1" itemprop="name">Does this work with Laravel Octane?</h3>
                    <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                        <p class="text-sm text-neutral-600" itemprop="text">
                            Yes. The generator produces a multi-stage Dockerfile and Docker Compose configuration optimised for
                            Laravel Octane with your choice of <strong>FrankenPHP</strong>, <strong>Swoole</strong>, or <strong>RoadRunner</strong>.
                            It also generates separate worker and scheduler containers that share the same built image.
                        </p>
                    </div>
                </div>
                <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                    <h3 class="font-semibold text-neutral-900 mb-1" itemprop="name">Can I deploy a static website with this tool?</h3>
                    <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                        <p class="text-sm text-neutral-600" itemprop="text">
                            Yes. Select "Static Website" as the application type and the tool will generate a Docker Compose file
                            using <code>halverneus/static-file-server</code> with the correct Traefik labels for HTTPS routing.
                        </p>
                    </div>
                </div>
                <div itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                    <h3 class="font-semibold text-neutral-900 mb-1" itemprop="name">What do I need on my VPS to use these configs?</h3>
                    <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                        <p class="text-sm text-neutral-600" itemprop="text">
                            Only Docker Engine (with the Compose plugin) is required on the host. No PHP, Node.js, Nginx, or
                            other runtimes need to be installed directly on the server — everything runs inside containers.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @slot('scripts')
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <script src="{{ asset('js/tools/docker-traefik-generator.js') }}" defer></script>
    @endslot
</x-layouts.app>
