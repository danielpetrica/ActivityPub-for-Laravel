<x-layouts.app
    :title="$metaTitle"
    :description="$metaDescription"
    :structuredData="$structuredData"
>
    <x-layouts.hero
        :schemaType="'https://schema.org/WebPage'"
        :title="$heroTitle"
        :excerpt="Str::limit(strip_tags($introContent), 160)"
        image="https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&q=80&w=2069"
    />

    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="prose prose-lg prose-primary mx-auto mb-16">
                {!! $introContent !!}
            </div>

            <div class="prose prose-lg prose-primary mx-auto">
                {!! $mainContent !!}
            </div>
        </div>
    </section>

    @if($caseStudies->isNotEmpty())
        <section class="py-20 bg-neutral-50 border-y border-neutral-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-12">
                    <h2 class="text-3xl font-extrabold text-neutral-900 mb-4">Esempi di progetti</h2>
                    <p class="text-neutral-500">Alcuni dei lavori realizzati correlati a {{ $service->name }}.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                    @foreach($caseStudies as $caseStudy)
                        <x-services.case-study :caseStudy="$caseStudy" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-10">
                <h2 class="text-3xl font-extrabold text-neutral-900 mb-4">Contattami</h2>
                <p class="text-lg text-neutral-600">
                    Hai un progetto a {{ $city->name }} o in zona? Compila il form e ti rispondo entro 24 ore.
                </p>
            </div>
            <x-forms.contact />
        </div>
    </section>
</x-layouts.app>
