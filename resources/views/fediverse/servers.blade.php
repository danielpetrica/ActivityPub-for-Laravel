@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="text-2xl font-bold text-gray-900 mb-6">Federated Servers</h2>

    @if ($servers->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <p class="text-gray-500">No federated servers yet. When someone follows you, their server will appear here.</p>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 divide-y divide-gray-100">
            @foreach ($servers as $server)
                @php $isBlocked = in_array($server->domain, $blockedDomains); @endphp
                <div class="px-5 py-4 flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full {{ $isBlocked ? 'bg-red-100' : 'bg-indigo-100' }} flex items-center justify-center">
                        <span class="text-lg font-bold {{ $isBlocked ? 'text-red-600' : 'text-indigo-600' }}">{{ strtoupper(substr($server->domain, 0, 1)) }}</span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900">
                            {{ $server->domain }}
                            @if ($isBlocked)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700 ml-2">Blocked</span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-500">{{ $server->follower_count }} {{ Str::plural('follower', $server->follower_count) }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="{{ route('fediverse.followers', ['domain' => $server->domain]) }}" class="px-3 py-1.5 text-xs font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors">
                            View followers
                        </a>

                        @if ($isBlocked)
                            <form action="{{ route('fediverse.servers.unblock-domain') }}" method="POST" onsubmit="return confirm('Unblock {{ addslashes($server->domain) }}?')">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="domain" value="{{ $server->domain }}">
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium text-green-600 border border-green-200 rounded-lg hover:bg-green-50 transition-colors">
                                    Unblock
                                </button>
                            </form>
                        @else
                            <form action="{{ route('fediverse.servers.block-domain') }}" method="POST" onsubmit="return confirm('Block {{ addslashes($server->domain) }}? This will remove all followers from this server.')">
                                @csrf
                                <input type="hidden" name="domain" value="{{ $server->domain }}">
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                                    Block
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
