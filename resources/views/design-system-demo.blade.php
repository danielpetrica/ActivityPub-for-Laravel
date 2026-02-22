<x-layouts.app title="Design System Demo">
    <x-layouts.hero
        featured
        title="Extracting a <span class='text-primary-600'>Modern</span> Design System"
        excerpt="How I turned static HTML files into a reusable Blade component library for my Laravel project."
        date="2026-01-24"
        readTime="5 min read"
        section="Laravel"
        url="#"
        image="https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&q=80&w=2072"
    />

    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-12 flex justify-between items-end">
                <div>
                    <h2 class="text-3xl font-extrabold text-neutral-900 mb-4">Core Components</h2>
                    <p class="text-neutral-500">A showcase of the extracted UI elements.</p>
                </div>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- UI Buttons -->
                <x-ui.card class="p-6">
                    <h3 class="font-bold mb-4">Buttons</h3>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button>Primary</x-ui.button>
                        <x-ui.button variant="secondary">Secondary</x-ui.button>
                        <x-ui.button variant="dark">Dark</x-ui.button>
                        <x-ui.button variant="ghost">Ghost</x-ui.button>
                    </div>
                </x-ui.card>

                <!-- Badges -->
                <x-ui.card class="p-6">
                    <h3 class="font-bold mb-4">Badges</h3>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.badge>Laravel</x-ui.badge>
                        <x-ui.badge variant="secondary">PHP</x-ui.badge>
                        <x-ui.badge variant="success">Active</x-ui.badge>
                        <x-ui.badge variant="danger">Deleted</x-ui.badge>
                        <x-ui.badge variant="warning">Pending</x-ui.badge>
                    </div>
                </x-ui.card>

                <!-- Alerts -->
                <x-ui.card class="p-6 lg:col-span-1">
                    <h3 class="font-bold mb-4">Alerts</h3>
                    <div class="space-y-4">
                        <x-ui.alert variant="success">Successfully saved changes!</x-ui.alert>
                        <x-ui.alert variant="danger">Something went wrong.</x-ui.alert>
                    </div>
                </x-ui.card>

                <!-- Forms & Input -->
                <x-ui.card class="p-6">
                    <h3 class="font-bold mb-4">Form Elements</h3>
                    <div class="space-y-4">
                        <x-forms.input placeholder="Standard Input" />
                        <x-forms.select :options="['laravel' => 'Laravel', 'tailwind' => 'Tailwind']" />
                        <x-forms.checkbox label="Accept terms and conditions" />
                    </div>
                </x-ui.card>

                <!-- Modal Demo -->
                <x-ui.card class="p-6">
                    <h3 class="font-bold mb-4">Modals</h3>
                    <x-ui.button onclick="document.getElementById('demo-modal').showModal()" class="w-full">
                        Open Demo Modal
                    </x-ui.button>

                    <x-ui.modal id="demo-modal" title="Demo Modal Header">
                        <p class="text-neutral-600">This is a native HTML dialog modal component. It's accessible and performant.</p>
                        <x-slot name="footer">
                            <x-ui.button variant="secondary" onclick="document.getElementById('demo-modal').close()">Cancel</x-ui.button>
                            <x-ui.button onclick="alert('Confirmed!')">Confirm</x-ui.button>
                        </x-slot>
                    </x-ui.modal>
                </x-ui.card>

                <!-- Author Widget -->
                <x-blog.author-widget />
            </div>

            <!-- Table Demo -->
            <div class="mt-12">
                <h3 class="font-bold mb-6">Data Tables</h3>
                <x-ui.table :headers="['Project', 'Status', 'Date']">
                    <tr>
                        <td class="px-6 py-4 text-sm text-neutral-900">Portfolio Website</td>
                        <td class="px-6 py-4 text-sm"><x-ui.badge variant="success">Live</x-ui.badge></td>
                        <td class="px-6 py-4 text-sm text-neutral-500">2026-01-24</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-4 text-sm text-neutral-900">Internal Dashboard</td>
                        <td class="px-6 py-4 text-sm"><x-ui.badge variant="warning">In Progress</x-ui.badge></td>
                        <td class="px-6 py-4 text-sm text-neutral-500">2026-01-20</td>
                    </tr>
                </x-ui.table>
            </div>
        </div>
    </section>

    <section class="py-20 bg-neutral-50 border-t border-neutral-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-extrabold text-neutral-900 mb-12">Article Cards</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
                <x-blog.article-card
                    title="Setting up Traefik with Docker"
                    excerpt="Learn how to configure Traefik as a reverse proxy for your Docker containers."
                    date="2026-01-20"
                    readTime="8 min"
                    section="Docker"
                    image="https://images.unsplash.com/photo-1605745341112-85968b193ef5?auto=format&fit=crop&q=80&w=2071"
                />
                <x-blog.article-card
                    title="Laravel 12 New Features"
                    excerpt="An overview of the most exciting updates in the latest Laravel release."
                    date="2026-01-15"
                    readTime="6 min"
                    section="Laravel"
                    image="https://images.unsplash.com/photo-1544197150-b99a580bb7a8?auto=format&fit=crop&q=80&w=2070"
                />
                <x-blog.article-card
                    title="Freelancing in 2026"
                    excerpt="Tips and tricks for staying productive and finding high-quality clients."
                    date="2026-01-10"
                    readTime="10 min"
                    section="Freelance"
                    image="https://images.unsplash.com/photo-1499750310107-5fef28a66643?auto=format&fit=crop&q=80&w=2070"
                />
            </div>
        </div>
    </section>

    <section class="py-12 bg-primary-50">
        <div class="max-w-4xl mx-auto px-4">
            <x-forms.newsletter layout="inline" />
        </div>
    </section>
</x-layouts.app>
