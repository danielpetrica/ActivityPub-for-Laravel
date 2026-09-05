<a href="/"
   {{ $attributes->class(['flex-shrink-0 flex items-center gap-3 group focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-lg p-1']) }} aria-label="Daniel Petrica Home">
    <div
        class="h-15 w-15 rounded-full bg-primary-100 border-2 border-primary-500 flex items-center justify-center
            overflow-hidden group-hover:scale-105 transition-transform object-center"
    >
        <figure role="img" aria-label="Daniel Petrica logo" class="m-0 w-full h-full flex items-center justify-center">
            <img
                src="{{ asset('image-logo.png') }}"
                alt="Daniel Petrica logo"
                width="40"
                height="40"
                class="w-full h-full object-contain max-w-[40px]"
                loading="eager"
            />
        </figure>
    </div>
    <span
        class="font-bold text-xl tracking-tight text-neutral-900 group-hover:text-primary-600 transition-colors"
    >
        {{config('app.name')}}
    </span>
</a>
