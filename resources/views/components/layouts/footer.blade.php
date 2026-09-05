<footer class="bg-white border-t border-neutral-200 pt-16 pb-8" itemscope itemtype="http://schema.org/WPFooter">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-16">

            <!-- Brand & Bio -->
            <div class="lg:col-span-1">
                <x-header-logo/>
                <p class="text-neutral-500 text-sm leading-relaxed mb-6" itemprop="description">
                    Documenting my journey through Laravel, Docker, and the freelance world. Helping you build better software.
                </p>
               <x-social-list/>
            </div>

            <!-- Footer Menu 1 -->
            <div>
                <h4 class="font-bold text-neutral-900 mb-6">Explore</h4>
                @php
                    $footerLinks = \App\Classes\Business\LinkBusiness::getLinksForPosition(\App\Enums\LinkPosition::Footer);
                @endphp
                <ul class="space-y-3 text-sm text-neutral-500">
                    @foreach($footerLinks as $link)
                        <li>
                            <a href="{{ $link->url }}"
                               @if($link->is_external) target="_blank" rel="noopener noreferrer" @endif
                               class="hover:text-primary-600 transition-colors focus:outline-none focus:text-primary-600">
                                {{ $link->label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <!-- Footer Menu 2 – Tools -->
            <div>
                <h4 class="font-bold text-neutral-900 mb-6">Tools</h4>
                <ul class="space-y-3 text-sm text-neutral-500">
{{--                    <li>--}}
{{--                        <a href="{{ route('tools.docker-traefik-generator') }}" class="hover:text-primary-600 transition-colors focus:outline-none focus:text-primary-600">--}}
{{--                            Docker &amp; Traefik Generator--}}
{{--                        </a>--}}
{{--                    </li>--}}
                    <li>
                        <a
                            href="https://random.danielpetrica.com"
                            target="_blank"
                            rel="me"
                            class="hover:text-primary-600 transition-colors focus:outline-none focus:text-primary-600"
                        >
                            Random Images
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Footer Newsletter -->
            <div>
                <h4 class="font-bold text-neutral-900 mb-6">Stay Updated</h4>
                <x-forms.newsletter layout="footer" />
                <p class="text-xs text-neutral-400 mt-3">Or subscribe via <a href="{{ route('rss.index') }}" class="text-primary-600 hover:underline">RSS</a>.</p>
            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="border-t border-neutral-100 pt-8 flex flex-col md:flex-row justify-between items-center gap-4 text-xs text-neutral-400">
            <p
                itemscope
                itemtype="http://schema.org/Person"
                itemprop="author"
            >&copy; {{ date('Y') }}
                <a
                    href="https://danielpetrica.com"
                    itemprop="homepage"
                >
                    <span itemprop="name">Daniel Petrica</span>.
                </a>
                All rights reserved.
            </p>
            <div class="flex items-center gap-6">
{{--                <a href="#" class="hover:text-neutral-600 focus:outline-none focus:underline">Privacy Policy</a>--}}
{{--                <a href="" class="hover:text-neutral-600 focus:outline-none focus:underline">Terms of Service</a>--}}
                <a href="{{ route('rss.index') }}" class="hover:text-neutral-600 focus:outline-none focus:underline">RSS</a>
            </div>
            <p class="flex items-center gap-1">
                Site built by me <x-ui.icon name="coffee" class="w-3 h-3" /> Daniel Petrica
            </p>
        </div>
    </div>
</footer>
