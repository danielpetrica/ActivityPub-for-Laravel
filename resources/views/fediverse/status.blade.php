@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="text-2xl font-bold text-gray-900 mb-6">Status</h2>

    <div class="space-y-3">
        @foreach ($checks as $check)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-5 py-4 flex items-center gap-4">
                <div class="flex-shrink-0">
                    @if ($check['status'] === 'ok')
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-green-100">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </span>
                    @elseif ($check['status'] === 'warning')
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-yellow-100">
                            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        </span>
                    @else
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-100">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </span>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900">{{ $check['label'] }}</p>
                    <p class="text-sm text-gray-500 mt-0.5">{{ $check['detail'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Configuration</h3>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    <tr class="px-5 py-3 flex items-center justify-between">
                        <td class="font-medium text-gray-600">Domain</td>
                        <td class="text-gray-900">{{ config('activitypub.domain') }}</td>
                    </tr>
                    <tr class="px-5 py-3 flex items-center justify-between">
                        <td class="font-medium text-gray-600">Actor Type</td>
                        <td class="text-gray-900">{{ config('activitypub.actor_type') }}</td>
                    </tr>
                    <tr class="px-5 py-3 flex items-center justify-between">
                        <td class="font-medium text-gray-600">Federation</td>
                        <td class="text-gray-900">{{ config('activitypub.federation.enabled') ? 'Enabled' : 'Disabled' }}</td>
                    </tr>
                    <tr class="px-5 py-3 flex items-center justify-between">
                        <td class="font-medium text-gray-600">HTTP Signatures</td>
                        <td class="text-gray-900">{{ config('activitypub.http_signatures.enabled') ? 'Enabled' : 'Disabled' }}</td>
                    </tr>
                    <tr class="px-5 py-3 flex items-center justify-between">
                        <td class="font-medium text-gray-600">Queue Connection</td>
                        <td class="text-gray-900">{{ config('activitypub.queue.connection') ?? config('queue.default') }}</td>
                    </tr>
                    <tr class="px-5 py-3 flex items-center justify-between">
                        <td class="font-medium text-gray-600">Queue Name</td>
                        <td class="text-gray-900">{{ config('activitypub.queue.queue') }}</td>
                    </tr>
                    <tr class="px-5 py-3 flex items-center justify-between">
                        <td class="font-medium text-gray-600">Delivery Timeout</td>
                        <td class="text-gray-900">{{ config('activitypub.federation.delivery_timeout') }}s</td>
                    </tr>
                    <tr class="px-5 py-3 flex items-center justify-between">
                        <td class="font-medium text-gray-600">Resolve Timeout</td>
                        <td class="text-gray-900">{{ config('activitypub.federation.resolve_timeout') }}s</td>
                    </tr>
                    <tr class="px-5 py-3 flex items-center justify-between">
                        <td class="font-medium text-gray-600">Debug Display</td>
                        <td class="text-gray-900">{{ config('activitypub.debug_display') ? 'Enabled' : 'Disabled' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Maintenance</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h4 class="text-sm font-semibold text-gray-900">Reschedule Pending</h4>
                <p class="text-xs text-gray-500 mt-1">Re-dispatch all pending outgoing activities to the queue for delivery.</p>
                <form action="{{ route('fediverse.status.reschedule') }}" method="POST" class="mt-3" onsubmit="return confirm('Reschedule all pending activities?')">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                        Reschedule
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h4 class="text-sm font-semibold text-gray-900">Refresh Followed Accounts</h4>
                <p class="text-xs text-gray-500 mt-1">Fetch latest profile data for all followed remote accounts.</p>
                <form action="{{ route('fediverse.status.refresh-accounts') }}" method="POST" class="mt-3" onsubmit="return confirm('Refresh all followed accounts?')">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                        Refresh All
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h4 class="text-sm font-semibold text-gray-900">Prune Old Activities</h4>
                <p class="text-xs text-gray-500 mt-1">Delete delivered activities older than 30 days to keep the database clean.</p>
                <form action="{{ route('fediverse.status.prune') }}" method="POST" class="mt-3" onsubmit="return confirm('Prune old activities?')">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors">
                        Prune
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
