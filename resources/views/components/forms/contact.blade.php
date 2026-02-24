@props(['title' => 'Contattami'])
@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
@endphp

<div id="contact" class="w-full">
    @if(session('contact_success'))
        <div role="status" aria-live="polite" class="mb-6 text-sm text-green-900 bg-green-100 border border-green-200 rounded-lg px-4 py-3">
            Messaggio inviato con successo! Ti risponderò il prima possibile.
        </div>
    @endif

    <form action="{{ route('contact.submit') }}" method="POST" class="space-y-5" novalidate>
        @if(Session::isStarted())
            @csrf
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="contact-name" class="block text-sm font-medium text-neutral-700 mb-1">Nome *</label>
                <input
                    id="contact-name"
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    autocomplete="name"
                    placeholder="Il tuo nome"
                    class="w-full px-4 py-2 border border-neutral-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none transition-all @error('name') border-red-400 @enderror"
                >
                @error('name')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="contact-email" class="block text-sm font-medium text-neutral-700 mb-1">Email *</label>
                <input
                    id="contact-email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autocomplete="email"
                    placeholder="la-tua@email.com"
                    class="w-full px-4 py-2 border border-neutral-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none transition-all @error('email') border-red-400 @enderror"
                >
                @error('email')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="contact-message" class="block text-sm font-medium text-neutral-700 mb-1">Messaggio *</label>
            <textarea
                id="contact-message"
                name="message"
                rows="5"
                required
                placeholder="Descrivi brevemente il tuo progetto o la tua esigenza..."
                class="w-full px-4 py-2 border border-neutral-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none transition-all resize-y @error('message') border-red-400 @enderror"
            >{{ old('message') }}</textarea>
            @error('message')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <x-ui.button type="submit" size="lg">
                Invia messaggio
            </x-ui.button>
        </div>
    </form>
</div>
