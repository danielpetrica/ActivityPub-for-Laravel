@props([
    'layout' => 'default', // 'default', 'footer', 'inline'
    'slug' => null,
])

@php
    $formModel = $slug ? \App\Models\NewsletterForm::where('slug', $slug)->where('is_active', true)->first() : null;

    $formClasses = match($layout) {
        'footer' => 'space-y-2',
        'inline' => 'flex flex-col md:flex-row gap-6 items-center',
        default => 'flex flex-col sm:flex-row gap-3 max-w-md mx-auto',
    };

    $buttonText = $formModel?->button_text ?? 'Subscribe';
    $successMessage = $formModel?->success_message ?? 'Subscribed!';
@endphp

@if(request()->query('subscribed') === '1')
    <div role="status" aria-live="polite" class="mb-4 text-sm text-green-900 bg-green-100 border border-green-200 rounded-lg px-3 py-2">
        {{ $successMessage }}
    </div>
@endif

<form {{ $attributes->merge(['class' => $formClasses]) }} action="{{ route('newsletter.subscribe') }}" method="POST" novalidate>
    <input type="hidden" name="slug" value="{{ $slug }}" />

    @if($layout === 'inline')
        <div class="flex-1">
            <h3 class="font-bold text-xl mb-2">{{ $formModel?->title ?? 'Get my updates in your inbox' }}</h3>
            <p class="text-sm opacity-80">{{ $formModel?->description ?? 'Register to be the first to receive my new articles on Laravel, DevOps, and more.' }}</p>
        </div>
        <div class="flex gap-2 w-full md:w-auto">
            <x-forms.input name="email" type="email" placeholder="Email address" required class="flex-1 bg-white text-black" />
            <x-ui.button type="submit" variant="dark">{{ $buttonText }}</x-ui.button>
        </div>
    @elseif($layout === 'footer')
        <x-forms.input name="email" type="email" id="footer-email" placeholder="Email address" required class="bg-white text-black" />
        <x-ui.button type="submit" class="w-full" size="sm">{{ $buttonText }}</x-ui.button>
    @else
        <x-forms.input name="email" type="email" placeholder="daniel@example.com" required class="flex-1 bg-white text-black" />
        <x-ui.button type="submit" variant="dark" class="px-8 py-3 shadow-lg hover:shadow-primary-500/25">
            {{ $buttonText }}
        </x-ui.button>
    @endif
</form>
