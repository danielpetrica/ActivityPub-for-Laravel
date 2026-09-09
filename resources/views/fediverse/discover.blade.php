@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="h2 mb-6">Discover</h2>

    <div class="card mb-6">
        <div class="card-body">
            <form action="{{ route('fediverse.discover.resolve') }}" method="POST" class="flex gap-3">
                @csrf
                <div class="flex-1">
                    <label for="handle" class="form-label">Fediverse address</label>
                    <input
                        type="text"
                        name="handle"
                        id="handle"
                        value="{{ old('handle', $handle ?? '') }}"
                        placeholder="user@example.com"
                        class="form-input"
                        required
                    >
                    @error('handle')
                        <p class="text-xs mt-1" style="color:var(--color-danger);">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary">
                        Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    @isset($remoteActor)
        <div class="card">
            <div class="card-body">
                <div class="flex items-start gap-5">
                    <div class="flex-shrink-0">
                        @if (isset($remoteActor['icon']['url']))
                            <img src="{{ $remoteActor['icon']['url'] }}" alt="" style="width:4rem;height:4rem;border-radius:9999px;">
                        @else
                            <div class="rounded-full" style="width:4rem;height:4rem;background:var(--color-gray-200);display:flex;align-items:center;justify-content:center;">
                                <span class="h3 text-muted">{{ strtoupper(substr($remoteActor['preferredUsername'] ?? '?', 0, 1)) }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <h3 class="h3">{{ $remoteActor['name'] ?? $remoteActor['preferredUsername'] ?? 'Unknown' }}</h3>
                        <p class="text-sm text-muted">{{ ($remoteActor['preferredUsername'] ?? '?').'@'.($domain ?? '?') }}</p>

                        @if (isset($remoteActor['summary']))
                            <div class="mt-3 text-sm text-muted">
                                {{ $remoteActor['summary'] }}
                            </div>
                        @endif

                        <div class="mt-4 flex gap-3">
                            @if ($isFollowing)
                                <form action="{{ route('fediverse.unfollow') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="remote_actor_url" value="{{ $remoteActorUrl }}">
                                    <button type="submit" class="btn btn-outline" style="color:var(--color-danger);border-color:var(--color-danger);">
                                        Unfollow
                                    </button>
                                </form>
                            @else
                                <form action="{{ route('fediverse.follow') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="remote_actor_url" value="{{ $remoteActorUrl }}">
                                    <button type="submit" class="btn btn-primary">
                                        Follow
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endisset

    @if (! isset($remoteActor) && ! old('handle'))
        <div class="text-center py-6">
            <p class="text-muted">Enter a Fediverse address to find and follow someone.</p>
        </div>
    @endif
@endsection
