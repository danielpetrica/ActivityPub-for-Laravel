@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="h2 mb-6">Status</h2>

    <div class="flex flex-col gap-3">
        @foreach ($checks as $check)
            <div class="card">
                <div class="flex items-center gap-4 p-4">
                    <div class="flex-shrink-0">
                        @if ($check['status'] === 'ok')
                            <span class="badge badge-green" style="width:2rem;height:2rem;display:inline-flex;align-items:center;justify-content:center;">
                                <svg style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </span>
                        @elseif ($check['status'] === 'warning')
                            <span class="badge badge-yellow" style="width:2rem;height:2rem;display:inline-flex;align-items:center;justify-content:center;">
                                <svg style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            </span>
                        @else
                            <span class="badge badge-red" style="width:2rem;height:2rem;display:inline-flex;align-items:center;justify-content:center;">
                                <svg style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </span>
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-sm" style="font-weight:600;">{{ $check['label'] }}</p>
                        <p class="text-sm text-muted mt-1">{{ $check['detail'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6">
        <h3 class="h3 mb-4">Configuration</h3>
        <div class="card overflow-auto">
            <table class="table">
                <tbody>
                    <tr class="table-row">
                        <td style="font-weight:500;">Domain</td>
                        <td>{{ config('activitypub.domain') }}</td>
                    </tr>
                    <tr class="table-row">
                        <td style="font-weight:500;">Actor Type</td>
                        <td>{{ config('activitypub.actor_type') }}</td>
                    </tr>
                    <tr class="table-row">
                        <td style="font-weight:500;">Federation</td>
                        <td>{{ config('activitypub.federation.enabled') ? 'Enabled' : 'Disabled' }}</td>
                    </tr>
                    <tr class="table-row">
                        <td style="font-weight:500;">HTTP Signatures</td>
                        <td>{{ config('activitypub.http_signatures.enabled') ? 'Enabled' : 'Disabled' }}</td>
                    </tr>
                    <tr class="table-row">
                        <td style="font-weight:500;">Queue Connection</td>
                        <td>{{ config('activitypub.queue.connection') ?? config('queue.default') }}</td>
                    </tr>
                    <tr class="table-row">
                        <td style="font-weight:500;">Queue Name</td>
                        <td>{{ config('activitypub.queue.queue') }}</td>
                    </tr>
                    <tr class="table-row">
                        <td style="font-weight:500;">Delivery Timeout</td>
                        <td>{{ config('activitypub.federation.delivery_timeout') }}s</td>
                    </tr>
                    <tr class="table-row">
                        <td style="font-weight:500;">Resolve Timeout</td>
                        <td>{{ config('activitypub.federation.resolve_timeout') }}s</td>
                    </tr>
                    <tr class="table-row">
                        <td style="font-weight:500;">Debug Display</td>
                        <td>{{ config('activitypub.debug_display') ? 'Enabled' : 'Disabled' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        <h3 class="h3 mb-4">Maintenance</h3>
        <div class="grid grid-4 gap-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="text-sm" style="font-weight:600;">Reschedule Pending</h4>
                    <p class="text-xs text-muted mt-1">Re-dispatch all pending outgoing activities to the queue for delivery.</p>
                    <form action="{{ route('fediverse.status.reschedule') }}" method="POST" class="mt-3" onsubmit="return confirm('Reschedule all pending activities?')">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            Reschedule
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h4 class="text-sm" style="font-weight:600;">Refresh Followed Accounts</h4>
                    <p class="text-xs text-muted mt-1">Fetch latest profile data for all followed remote accounts.</p>
                    <form action="{{ route('fediverse.status.refresh-accounts') }}" method="POST" class="mt-3" onsubmit="return confirm('Refresh all followed accounts?')">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            Refresh All
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h4 class="text-sm" style="font-weight:600;">Prune Old Activities</h4>
                    <p class="text-xs text-muted mt-1">Delete delivered activities older than 30 days to keep the database clean.</p>
                    <form action="{{ route('fediverse.status.prune') }}" method="POST" class="mt-3" onsubmit="return confirm('Prune old activities?')">
                        @csrf
                        <button type="submit" class="btn btn-danger">
                            Prune
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h4 class="text-sm" style="font-weight:600;">Forward Posts</h4>
                    <p class="text-xs text-muted mt-1">Send oldest posts to all servers that follow you. Requires federatable_models to be configured.</p>
                    <form action="{{ route('fediverse.status.forward-posts') }}" method="POST" class="mt-3" onsubmit="return confirm('Forward posts to all follower servers?')">
                        @csrf
                        <button type="submit" class="btn btn-primary" style="background:var(--color-purple);border-color:var(--color-purple);">
                            Forward Now
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
