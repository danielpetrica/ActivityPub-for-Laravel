@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="h2 mb-6">Federated Servers</h2>

    @if ($servers->isEmpty())
        <div class="card p-6 text-center">
            <p class="text-muted">No federated servers yet. When someone follows you, their server will appear here.</p>
        </div>
    @else
        <div class="card">
            @foreach ($servers as $server)
                @php $isBlocked = in_array($server->domain, $blockedDomains); @endphp
                <div class="flex items-center gap-4 p-4 @if (! $loop->last) border @endif">
                    <div class="rounded-full {{ $isBlocked ? 'badge badge-red' : 'badge badge-indigo' }}" style="width:2.5rem;height:2.5rem;display:inline-flex;align-items:center;justify-content:center;">
                        <span class="h5">{{ strtoupper(substr($server->domain, 0, 1)) }}</span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-sm" style="font-weight:600;">
                            {{ $server->domain }}
                            @if ($isBlocked)
                                <span class="badge badge-red ml-2">Blocked</span>
                            @endif
                        </p>
                        <p class="text-xs text-muted">{{ $server->follower_count }} {{ Str::plural('follower', $server->follower_count) }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="{{ route('fediverse.followers', ['domain' => $server->domain]) }}" class="btn btn-outline btn-xs">
                            View followers
                        </a>

                        @if ($isBlocked)
                            <form action="{{ route('fediverse.servers.unblock-domain') }}" method="POST" onsubmit="return confirm('Unblock {{ addslashes($server->domain) }}?')">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="domain" value="{{ $server->domain }}">
                                <button type="submit" class="btn btn-outline btn-xs" style="color:var(--color-success);border-color:var(--color-success);">
                                    Unblock
                                </button>
                            </form>
                        @else
                            <form action="{{ route('fediverse.servers.block-domain') }}" method="POST" onsubmit="return confirm('Block {{ addslashes($server->domain) }}? This will remove all followers from this server.')">
                                @csrf
                                <input type="hidden" name="domain" value="{{ $server->domain }}">
                                <button type="submit" class="btn btn-outline btn-xs" style="color:var(--color-danger);border-color:var(--color-danger);">
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
