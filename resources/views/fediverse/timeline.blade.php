@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="h2 mb-6">Timeline</h2>

    @if ($activities->isEmpty())
        <div class="card p-8 text-center">
            <p class="text-muted">Your timeline is empty. Follow some accounts from the <a href="{{ route('fediverse.discover') }}">Discover</a> page to see their posts here.</p>
        </div>
    @else
        <div class="flex flex-col gap-4">
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

                <div class="card p-5">
                    <div class="flex items-center gap-3 mb-3">
                        @if ($activity->remoteActor && $activity->remoteActor->icon_url)
                            <img src="{{ $activity->remoteActor->icon_url }}" alt="" style="width:2.5rem;height:2.5rem;border-radius:9999px;flex-shrink:0;">
                        @else
                            <div style="width:2.5rem;height:2.5rem;border-radius:9999px;background:var(--color-gray-200);flex-shrink:0;display:flex;align-items:center;justify-content:center;color:var(--color-gray-500);font-size:0.875rem;font-weight:500;">
                                {{ strtoupper(substr($activity->remoteActor->username ?? '?', 0, 1)) }}
                            </div>
                        @endif

                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm">{{ $activity->remoteActor->name ?? $activity->remoteActor->username }}</span>
                                <span class="text-xs text-muted">@ {{ $activity->remoteActor->username }}@{{ $activity->remoteActor->domain }}</span>
                            </div>
                            <p class="text-xs text-muted">
                                @if ($objPublished)
                                    {{ \Carbon\Carbon::parse($objPublished)->diffForHumans() }}
                                @else
                                    {{ $activity->created_at->diffForHumans() }}
                                @endif
                            </p>
                        </div>

                        @if ($objUrl)
                            <a href="{{ $objUrl }}" target="_blank" rel="noopener noreferrer" class="text-muted" style="flex-shrink:0;">
                                <svg style="width:1rem;height:1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        @endif
                    </div>

                    @if ($objName)
                        <h3 class="h4 mb-1">{{ $objName }}</h3>
                    @endif

                    @if ($objContent)
                        <div class="text-sm post-content">{!! \DanielPetrica\LaravelActivityPub\Helpers\ActivityPubHelper::sanitizeContent($objContent) !!}</div>
                    @endif

                    @if (!empty($mediaAttachments))
                        <div class="mt-3 grid grid-2 gap-2">
                            @foreach ($mediaAttachments as $media)
                                @php $mediaUrl = $media['url'] ?? $media['href'] ?? null; @endphp
                                @if ($mediaUrl)
                                    <img src="{{ $mediaUrl }}" alt="" class="rounded w-full" style="height:12rem;object-fit:cover;" loading="lazy">
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
