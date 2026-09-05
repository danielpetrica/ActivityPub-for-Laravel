<x-layouts.app
    title="100 Container in un VPS: Guida e Risorse | Daniel Petrica"
    description="Risorse, link e feedback per il talk '100 Container in un VPS' di Daniel Petrica."
>
    <div class="py-12 md:py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Header del Talk --}}
            <div class="text-center mb-16">
                <div class="inline-flex items-center justify-center p-3 bg-primary-100 rounded-2xl mb-6">
                    <x-ui.icon name="container" class="h-10 w-10 text-primary-600" />
                </div>
                <h1 class="text-4xl md:text-5xl font-extrabold text-neutral-900 tracking-tight mb-4">
                    100 Container in un VPS
                </h1>
                <p class="text-xl text-neutral-600 max-w-2xl mx-auto">
                    Grazie per aver partecipato al mio talk! Qui trovi tutte le risorse menzionate, i link utili e il modulo per lasciare un feedback.
                </p>
                <div class="mt-8">
                    <x-ui.button href="https://talks.danielpetrica.com/100container/" external size="lg" class="rounded-full">
                        <x-ui.icon name="external-link" class="mr-2 h-5 w-5" />
                        Guarda le Slide del Talk
                    </x-ui.button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 mb-20">
                {{-- Risorse Esterne --}}
                <div class="space-y-6">
                    <h2 class="text-2xl font-bold text-neutral-900 flex items-center gap-2">
                        <x-ui.icon name="library" class="h-6 w-6 text-primary-500" />
                        Risorse Utili
                    </h2>
                    <ul class="space-y-4">
                        <li>
                            <a href="https://danielpetrica.com" target="_blank" rel="noopener noreferrer" class="group flex items-start p-4 bg-white border border-neutral-200 rounded-xl hover:border-primary-300 hover:shadow-sm transition-all">
                                <div class="mr-4 mt-1">
                                    <x-ui.icon name="globe" class="h-5 w-5 text-neutral-400 group-hover:text-primary-500" />
                                </div>
                                <div>
                                    <span class="block font-semibold text-neutral-900">DanielPetrica.com</span>
                                    <span class="text-sm text-neutral-500">Il mio blog dove parlo di tech, DevOps e altro.</span>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="https://doc.traefik.io/traefik/getting-started/install-traefik/" target="_blank" rel="noopener noreferrer" class="group flex items-start p-4 bg-white border border-neutral-200 rounded-xl hover:border-primary-300 hover:shadow-sm transition-all">
                                <div class="mr-4 mt-1">
                                    <x-ui.icon name="book-open" class="h-5 w-5 text-neutral-400 group-hover:text-primary-500" />
                                </div>
                                <div>
                                    <span class="block font-semibold text-neutral-900">Traefik Docs</span>
                                    <span class="text-sm text-neutral-500">La documentazione ufficiale di Traefik Proxy.</span>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="https://docs.docker.com/compose/" target="_blank" rel="noopener noreferrer" class="group flex items-start p-4 bg-white border border-neutral-200 rounded-xl hover:border-primary-300 hover:shadow-sm transition-all">
                                <div class="mr-4 mt-1">
                                    <x-ui.icon name="box" class="h-5 w-5 text-neutral-400 group-hover:text-primary-500" />
                                </div>
                                <div>
                                    <span class="block font-semibold text-neutral-900">Docker Compose</span>
                                    <span class="text-sm text-neutral-500">Guida all'orchestrazione locale con Docker Compose.</span>
                                </div>
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- Social --}}
                <div class="space-y-6">
                    <h2 class="text-2xl font-bold text-neutral-900 flex items-center gap-2">
                        <x-ui.icon name="share-2" class="h-6 w-6 text-primary-500" />
                        Rimaniamo in Contatto
                    </h2>
                    <div class="grid grid-cols-1 gap-4">
                        <a href="https://www.linkedin.com/in/petricadaniel/" target="_blank" rel="noopener noreferrer" class="flex items-center p-4 bg-[#0077b5]/5 border border-[#0077b5]/20 rounded-xl hover:bg-[#0077b5]/10 transition-colors">
                            <svg class="h-6 w-6 text-[#0077b5] mr-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                            <span class="font-medium text-neutral-900">LinkedIn</span>
                        </a>
                        <a href="https://infosec.exchange/@danielpetrica" target="_blank" rel="noopener noreferrer" class="flex items-center p-4 bg-[#6364ff]/5 border border-[#6364ff]/20 rounded-xl hover:bg-[#6364ff]/10 transition-colors">
                            <x-ui.icon name="user" class="h-6 w-6 text-[#6364ff] mr-4" />
                            <span class="font-medium text-neutral-900">Mastodon</span>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Form di Feedback --}}
            <div class="bg-white border border-neutral-200 rounded-3xl p-8 md:p-12 shadow-sm">
                <div class="max-w-2xl mx-auto">
                    <div class="text-center mb-10">
                        <h2 class="text-3xl font-bold text-neutral-900 mb-4">Lasciami un Feedback</h2>
                        <p class="text-neutral-600">
                            Cosa ne pensi del talk? Hai domande o suggerimenti? Scrivimi pure qui sotto!
                        </p>
                    </div>

{{--                    <x-forms.contact title="Feedback Talk" />--}}
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
