@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="text-2xl font-bold text-gray-900 mb-6">Timeline</h2>

    @if ($activities->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">Your timeline is empty. Follow some accounts from the <a href="{{ route('fediverse.discover') }}" class="text-indigo-600 hover:underline">Discover</a> page to see their posts here.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($activities as $activity)
                @php
                    $object = $activity->payload['object'] ?? [];
                    $objContent = is_array($object) ? ($object['content'] ?? null) : null;
                    $objName = is_array($object) ? ($object['name'] ?? null) : null;
                    $objUrl = is_array($object) ? ($object['url'] ?? $object['id'] ?? null) : null;
                    $objPublished = is_array($object) ? ($object['published'] ?? null) : null;
                    $attachments = is_array($object) ? ($object['attachment'] ?? []) : [];
                    $mediaAttachments = array_filter($attachments, fn ($a) => ($a['type'] ?? null) === 'Image' || ($a['mediaType'] ?? null) === 'image/png' || ($a['mediaType'] ?? null) === 'image/jpeg');
                @endphp

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <div class="flex items-start gap-3 mb-3">
                        @if ($activity->remoteActor && $activity->remoteActor->icon_url)
                            <img src="{{ $activity->remoteActor->icon_url }}" alt="" class="w-10 h-10 rounded-full flex-shrink-0">
                        @else
                            <div class="w-10 h-10 rounded-full bg-gray-200 flex-shrink-0 flex items-center justify-center text-gray-500 text-sm font-medium">
                                {{ strtoupper(substr($activity->remoteActor->username ?? '?', 0, 1)) }}
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-gray-900">{{ $activity->remoteActor->name ?? $activity->remoteActor->username }}</span>
                                <span class="text-xs text-gray-400">@ {{ $activity->remoteActor->username }}@{{ $activity->remoteActor->domain }}</span>
                            </div>
                            <p class="text-xs text-gray-400">
                                @if ($objPublished)
                                    {{ \Carbon\Carbon::parse($objPublished)->diffForHumans() }}
                                @else
                                    {{ $activity->created_at->diffForHumans() }}
                                @endif
                            </p>
                        </div>

                        @if ($objUrl)
                            <a href="{{ $objUrl }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-gray-600 flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        @endif
                    </div>

                    @if ($objName)
                        <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ $objName }}</h3>
                    @endif

                    @if ($objContent)
                        <div class="text-sm text-gray-700 leading-relaxed prose prose-sm max-w-none">{{ $objContent }}</div>
                    @endif

                    @if (!empty($mediaAttachments))
                        <div class="mt-3 grid grid-cols-{{ min(count($mediaAttachments), 2) }} gap-2">
                            @foreach ($mediaAttachments as $media)
                                @php $mediaUrl = $media['url'] ?? $media['href'] ?? null; @endphp
                                @if ($mediaUrl)
                                    <img src="{{ $mediaUrl }}" alt="" class="rounded-lg w-full h-48 object-cover" loading="lazy">
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $activities->links() }}
        </div>
    @endif
@endsection
