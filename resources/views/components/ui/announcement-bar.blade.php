@props([
    'announcements' => []
])

@if(count($announcements) > 0)
    <div class="bg-primary-600 text-white">
        <div class="max-w-7xl mx-auto py-2 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-2">
                @foreach($announcements as $announcement)
                    <div class="text-center text-sm font-medium announcement-item [&_a]:text-yellow-300 [&_a]:underline hover:[&_a]:text-yellow-200 transition-colors">
                        {!! $announcement->text !!}
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
