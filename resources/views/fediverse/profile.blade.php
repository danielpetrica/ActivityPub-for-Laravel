@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="h2 mb-6">Profile</h2>

    <div class="card mb-6" style="max-width:42rem;">
        <div class="card-body">
            <div class="flex items-start gap-5 mb-6 pb-6" style="border-bottom:1px solid var(--color-gray-100);">
                <div class="flex-shrink-0">
                    @if ($localActor->icon_url)
                        <img src="{{ $localActor->icon_url }}" alt="" style="width:5rem;height:5rem;border-radius:9999px;">
                    @else
                        <div class="rounded-full" style="width:5rem;height:5rem;background:var(--color-indigo-light);display:flex;align-items:center;justify-content:center;">
                            <span class="h2" style="color:var(--color-indigo);">{{ strtoupper(substr($localActor->name ?? $localActor->username, 0, 1)) }}</span>
                        </div>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <h3 class="h3">{{ $localActor->name ?? $localActor->username }}</h3>
                    <p class="text-sm text-muted">{{ $localActor->username.'@'.$actorDomain }}</p>
                    <p class="text-xs mt-1" style="color:var(--color-gray-400);">
                        <a href="{{ $localActor->actor_id }}" target="_blank">{{ $localActor->actor_id }}</a>
                    </p>
                </div>
            </div>

            <form action="{{ route('fediverse.profile.update') }}" method="POST">
                @csrf

                <div class="flex flex-col gap-4">
                    <div class="form-group">
                        <label for="name" class="form-label">Display name</label>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            value="{{ old('name', $localActor->name ?? '') }}"
                            class="form-input"
                            maxlength="255"
                        >
                        @error('name')
                            <p class="text-xs mt-1" style="color:var(--color-danger);">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="summary" class="form-label">Bio / Summary</label>
                        <textarea
                            name="summary"
                            id="summary"
                            rows="4"
                            class="form-input"
                            maxlength="5000"
                        >{{ old('summary', $localActor->summary ?? '') }}</textarea>
                        <p class="text-xs text-muted mt-1">HTML is allowed.</p>
                        @error('summary')
                            <p class="text-xs mt-1" style="color:var(--color-danger);">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="icon_url" class="form-label">Avatar URL</label>
                        <input
                            type="url"
                            name="icon_url"
                            id="icon_url"
                            value="{{ old('icon_url', $localActor->icon_url ?? '') }}"
                            class="form-input"
                            maxlength="2048"
                            placeholder="https://example.com/avatar.jpg"
                        >
                        @error('icon_url')
                            <p class="text-xs mt-1" style="color:var(--color-danger);">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="image_url" class="form-label">Header image URL</label>
                        <input
                            type="url"
                            name="image_url"
                            id="image_url"
                            value="{{ old('image_url', $localActor->image_url ?? '') }}"
                            class="form-input"
                            maxlength="2048"
                            placeholder="https://example.com/banner.jpg"
                        >
                        @error('image_url')
                            <p class="text-xs mt-1" style="color:var(--color-danger);">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 pt-5" style="border-top:1px solid var(--color-gray-100);">
                    <button type="submit" class="btn btn-primary">
                        Save changes
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
