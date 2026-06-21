@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="text-2xl font-bold text-gray-900 mb-6">Discover</h2>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <form action="{{ route('fediverse.discover.resolve') }}" method="POST" class="flex gap-3">
            @csrf
            <div class="flex-1">
                <label for="handle" class="block text-sm font-medium text-gray-700 mb-1">Fediverse address</label>
                <input
                    type="text"
                    name="handle"
                    id="handle"
                    value="{{ old('handle', $handle ?? '') }}"
                    placeholder="user@example.com"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    required
                >
                @error('handle')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex items-end">
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    Search
                </button>
            </div>
        </form>
    </div>

    @isset($remoteActor)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-start gap-5">
                <div class="flex-shrink-0">
                    @if (isset($remoteActor['icon']['url']))
                        <img src="{{ $remoteActor['icon']['url'] }}" alt="" class="w-16 h-16 rounded-full">
                    @else
                        <div class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center">
                            <span class="text-2xl font-bold text-gray-500">{{ strtoupper(substr($remoteActor['preferredUsername'] ?? '?', 0, 1)) }}</span>
                        </div>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <h3 class="text-lg font-bold text-gray-900">{{ $remoteActor['name'] ?? $remoteActor['preferredUsername'] ?? 'Unknown' }}</h3>
                    <p class="text-sm text-gray-500">{{ ($remoteActor['preferredUsername'] ?? '?').'@'.($domain ?? '?') }}</p>

                    @if (isset($remoteActor['summary']))
                        <div class="mt-3 text-sm text-gray-600 prose prose-sm max-w-none">
                            {{ $remoteActor['summary'] }}
                        </div>
                    @endif

                    <div class="mt-4 flex gap-3">
                        @if ($isFollowing)
                            <form action="{{ route('fediverse.unfollow') }}" method="POST">
                                @csrf
                                <input type="hidden" name="remote_actor_url" value="{{ $remoteActorUrl }}">
                                <button type="submit" class="px-4 py-2 border border-red-200 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors">
                                    Unfollow
                                </button>
                            </form>
                        @else
                            <form action="{{ route('fediverse.follow') }}" method="POST">
                                @csrf
                                <input type="hidden" name="remote_actor_url" value="{{ $remoteActorUrl }}">
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                                    Follow
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endisset

    @if (! isset($remoteActor) && ! old('handle'))
        <div class="text-center py-12">
            <p class="text-gray-500">Enter a Fediverse address to find and follow someone.</p>
        </div>
    @endif
@endsection
