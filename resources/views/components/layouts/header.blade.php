<nav class="sticky top-0 z-50 w-full bg-white/80 backdrop-blur-md" aria-label="Main Navigation">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">

            <!-- Logo Area -->
            <x-header-logo/>

            <!-- Desktop Menu -->
            @php
                $headerLinks = \App\Classes\Business\LinkBusiness::getLinksForPosition(\App\Enums\LinkPosition::Header);

                $categoryLinks = [
                    ['label' => 'Freelance', 'slug' => 'freelance'],
                    ['label' => 'LaraPlugins.io', 'slug' => 'laraplugins-io'],
                    ['label' => 'Traefik', 'slug' => 'traefik'],
                    ['label' => 'Laravel', 'slug' => 'laravel'],
                ];
            @endphp
            <div class="hidden md:flex items-center space-x-8" role="menubar">
                @foreach($headerLinks as $link)
                    <a href="{{ $link->url }}"
                       @if($link->is_external) target="_blank" rel="noopener noreferrer" @endif
                       class="text-sm font-medium text-neutral-600 hover:text-primary-600 transition-colors focus:outline-none focus:text-primary-600"
                       role="menuitem">{{ $link->label }}</a>
                @endforeach

                <span class="w-px h-4 bg-neutral-200" aria-hidden="true"></span>

                @foreach($categoryLinks as $cat)
                    <a href="{{ route('tags.show', $cat['slug']) }}"
                       class="text-sm font-medium text-primary-600 hover:text-primary-700 transition-colors focus:outline-none focus:text-primary-700"
                       role="menuitem">{{ $cat['label'] }}</a>
                @endforeach
            </div>

            <!-- Right Actions -->
            <div class="hidden md:flex items-center gap-4">
                <button id="search-open-btn" class="p-2 text-neutral-500 hover:text-primary-600 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-full" aria-label="Search articles">
                    <i data-lucide="search" class="h-5 w-5"></i>
                </button>
                <x-ui.button type="button" size="sm" class="rounded-full shadow-lg shadow-primary-500/20" data-subscribe-open>
                    Subscribe
                </x-ui.button>
            </div>

            <!-- Mobile Menu Button -->
            <div class="md:hidden flex items-center">
                <button id="mobile-menu-btn" class="text-neutral-500 hover:text-primary-600 p-2 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-md" aria-label="Open menu" aria-expanded="false">
                    <i data-lucide="menu" class="h-6 w-6"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Panel -->
    <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-neutral-100 absolute w-full left-0 top-16 shadow-lg z-50">
        <div class="px-4 pt-2 pb-6 space-y-2">
            @foreach($headerLinks as $link)
                <a href="{{ $link->url }}"
                   @if($link->is_external) target="_blank" rel="noopener noreferrer" @endif
                   class="block px-3 py-2 rounded-md text-base font-medium text-neutral-900 hover:bg-primary-50 hover:text-primary-600">{{ $link->label }}</a>
            @endforeach

            <div class="pt-4 border-t border-neutral-100 mt-2">
                <p class="px-3 py-1 text-xs font-bold text-neutral-400 uppercase tracking-wider">Categories</p>
                @foreach($categoryLinks as $cat)
                    <a href="{{ route('tags.show', $cat['slug']) }}"
                       class="block px-3 py-2 rounded-md text-base font-medium text-primary-600 hover:bg-primary-50 hover:text-primary-700">{{ $cat['label'] }}</a>
                @endforeach
            </div>

            <div class="pt-4 border-t border-neutral-100 mt-2">
                <button id="mobile-search-open-btn" class="flex items-center gap-3 w-full px-3 py-2 text-base font-medium text-neutral-900 hover:bg-primary-50 hover:text-primary-600 rounded-md transition-colors">
                    <i data-lucide="search" class="h-5 w-5"></i>
                    <span>Search articles</span>
                </button>
                <x-ui.button type="button" class="w-full mt-3" data-subscribe-open>Subscribe</x-ui.button>
            </div>
        </div>
    </div>
</nav>

<dialog id="search-dialog" class="backdrop:bg-neutral-900/50 backdrop:backdrop-blur-sm p-0 rounded-2xl border-0 shadow-2xl w-full max-w-2xl bg-white overflow-hidden focus:outline-none">
    <div class="p-4 md:p-6">
        <form action="/search" method="GET" class="relative group">
            <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-neutral-400 group-focus-within:text-primary-500 transition-colors"></i>
            <input type="search" name="q" id="search-input" placeholder="Search articles, topics, tutorials..." class="w-full pl-12 pr-4 py-3 text-lg bg-neutral-100 rounded-xl border border-transparent focus:border-primary-500 focus:bg-white focus:ring-4 focus:ring-primary-500/10 outline-none transition-all" autocomplete="off">
            <kbd class="absolute right-4 top-1/2 -translate-y-1/2 hidden md:inline-flex items-center px-2 py-1 text-xs font-semibold text-neutral-400 bg-white border border-neutral-200 rounded-md shadow-sm pointer-events-none">ESC</kbd>
        </form>

        <div class="mt-6 flex items-center justify-between text-xs text-neutral-400 uppercase tracking-widest font-semibold px-1">
            <span>Quick search</span>
            <button id="search-close-btn" class="p-1 hover:text-neutral-600 transition-colors focus:outline-none rounded">Close</button>
        </div>
    </div>
</dialog>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('mobile-menu-btn');
        const menu = document.getElementById('mobile-menu');
        if (btn && menu) {
            btn.addEventListener('click', () => {
                const isExpanded = btn.getAttribute('aria-expanded') === 'true';
                btn.setAttribute('aria-expanded', !isExpanded);
                menu.classList.toggle('hidden');
            });
        }

        const searchDialog = document.getElementById('search-dialog');
        const searchOpenBtns = [
            document.getElementById('search-open-btn'),
            document.getElementById('mobile-search-open-btn')
        ];
        const searchCloseBtn = document.getElementById('search-close-btn');
        const searchInput = document.getElementById('search-input');

        searchOpenBtns.forEach(btn => {
            if (btn) {
                btn.addEventListener('click', () => {
                    searchDialog.showModal();
                    // Close mobile menu if open
                    if (menu && !menu.classList.contains('hidden')) {
                        menu.classList.add('hidden');
                        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
                        if (mobileMenuBtn) mobileMenuBtn.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        });

        if (searchCloseBtn) {
            searchCloseBtn.addEventListener('click', () => searchDialog.close());
        }

        searchDialog.addEventListener('click', (e) => {
            const rect = searchDialog.getBoundingClientRect();
            if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) {
                searchDialog.close();
            }
        });

        // Focus input on open
        searchDialog.addEventListener('close', () => {
            searchInput.value = '';
        });

        searchDialog.addEventListener('show', () => {
            // Native dialog doesn't have a 'show' event that is reliably useful here,
            // but we can focus when it opens via showModal()
        });

        // Use mutation observer or just focus after showModal
        const originalShowModal = searchDialog.showModal;
        searchDialog.showModal = function() {
            originalShowModal.apply(searchDialog);
            setTimeout(() => searchInput.focus(), 100);
        };
    });
</script>
