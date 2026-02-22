@php
    $redirectTo = request()->fullUrl();
@endphp

<dialog id="subscribe-dialog" class="backdrop:bg-neutral-900/50 backdrop:backdrop-blur-sm p-0 rounded-2xl border-0 shadow-2xl w-full max-w-md bg-white overflow-hidden focus:outline-none">
    <form method="POST" action="{{ route('newsletter.subscribe') }}" class="p-6 space-y-4" novalidate>
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-neutral-900">Subscribe to the newsletter</h2>
                <p class="mt-1 text-sm text-neutral-600">One email when new articles are published. No spam, unsubscribe anytime.</p>
            </div>
            <button type="button" id="subscribe-close-btn" class="p-2 text-neutral-500 hover:text-neutral-800 rounded-md focus:outline-none" aria-label="Close subscribe dialog">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">

        <div class="space-y-2">
            <label for="subscribe-email" class="block text-sm font-medium text-neutral-800">Email address</label>
            <input id="subscribe-email" name="email" type="email" required autocomplete="email"
                   placeholder="you@example.com"
                   class="w-full rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 text-base text-neutral-900 placeholder-neutral-400 focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary-500/10">
        </div>

        <div class="pt-2">
            <x-ui.button type="submit" class="w-full">Subscribe</x-ui.button>
        </div>

        <p class="text-xs text-neutral-500">Protected by Mailcoach. Double opt-in may apply.</p>
    </form>
</dialog>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const dialog = document.getElementById('subscribe-dialog');
        const closeBtn = document.getElementById('subscribe-close-btn');
        const emailInput = document.getElementById('subscribe-email');
        const openers = document.querySelectorAll('[data-subscribe-open]');

        const open = () => {
            if (! dialog.open) {
                const originalShow = dialog.showModal.bind(dialog);
                originalShow();
                setTimeout(() => emailInput?.focus(), 100);
            }
        };

        openers.forEach(btn => btn.addEventListener('click', open));
        closeBtn?.addEventListener('click', () => dialog.close());

        // Click outside to close
        dialog.addEventListener('click', (e) => {
            const rect = dialog.getBoundingClientRect();
            if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) {
                dialog.close();
            }
        });

        // Support ESC to close via native dialog behaviour
    });
</script>
