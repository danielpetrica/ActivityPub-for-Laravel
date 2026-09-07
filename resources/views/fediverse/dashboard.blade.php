@extends('activitypub::fediverse.layout')

@section('content')
    <h2 class="text-2xl font-bold text-gray-900 mb-6">Dashboard</h2>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-6">
        <div class="flex items-start gap-5">
            <div class="flex-shrink-0">
                @if ($localActor->icon_url)
                    <img src="{{ $localActor->icon_url }}" alt="" class="w-16 h-16 rounded-full">
                @else
                    <div class="w-16 h-16 rounded-full bg-indigo-100 flex items-center justify-center">
                        <span class="text-2xl font-bold text-indigo-600">{{ strtoupper(substr($localActor->name ?? $localActor->username, 0, 1)) }}</span>
                    </div>
                @endif
            </div>

            <div class="flex-1 min-w-0">
                <h3 class="text-xl font-bold text-gray-900">{{ $localActor->name ?? $localActor->username }}</h3>
                <p class="text-sm text-gray-500">{{ $localActor->username.'@'.$actorDomain }}</p>
                <p class="text-xs text-gray-400 mt-1">
                    <a href="{{ $localActor->actor_id }}" class="hover:text-indigo-600" target="_blank">{{ $localActor->actor_id }}</a>
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-sm text-gray-500 font-medium">Followers</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($followerCount) }}</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-sm text-gray-500 font-medium">Following</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($followingCount) }}</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-sm text-gray-500 font-medium">Incoming</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($incomingCount) }}</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-sm text-gray-500 font-medium">Outgoing</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($outgoingCount) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900">Recent Inbox</h3>
            </div>

            @if ($recentInbox->isEmpty())
                <div class="p-5 text-sm text-gray-500">No incoming activities yet.</div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($recentInbox as $activity)
                        <div class="px-5 py-3 flex items-start gap-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                @switch($activity->type->value)
                                    @case('Follow') bg-blue-100 text-blue-700 @break
                                    @case('Like') bg-pink-100 text-pink-700 @break
                                    @case('Announce') bg-green-100 text-green-700 @break
                                    @case('Create') bg-purple-100 text-purple-700 @break
                                    @default bg-gray-100 text-gray-700 @endswitch
                            ">{{ $activity->type->value }}</span>
                            <div class="flex-1 min-w-0">
                                @if ($activity->remoteActor)
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ $activity->remoteActor->name ?? $activity->remoteActor->username }}</p>
                                @endif
                                <p class="text-xs text-gray-500">{{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="px-5 py-3 border-t border-gray-100">
                    <a href="{{ route('fediverse.inbox') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">View all &rarr;</a>
                </div>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900">Recent Outbox</h3>
            </div>

            @if ($recentOutbox->isEmpty())
                <div class="p-5 text-sm text-gray-500">No outgoing activities yet.</div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($recentOutbox as $activity)
                        <div class="px-5 py-3 flex items-start gap-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                @switch($activity->type->value)
                                    @case('Follow') bg-blue-100 text-blue-700 @break
                                    @case('Like') bg-pink-100 text-pink-700 @break
                                    @case('Announce') bg-green-100 text-green-700 @break
                                    @case('Create') bg-purple-100 text-purple-700 @break
                                    @default bg-gray-100 text-gray-700 @endswitch
                            ">{{ $activity->type->value }}</span>
                            @if ($activity->status->value === 'pending')
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>
                            @endif
                            @if ($activity->status->value === 'failed')
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Failed</span>
                            @endif
                            <div class="flex-1 min-w-0">
                                @if ($activity->remoteActor)
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ $activity->remoteActor->name ?? $activity->remoteActor->username }}</p>
                                @endif
                                <p class="text-xs text-gray-500">{{ $activity->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="px-5 py-3 border-t border-gray-100">
                    <a href="{{ route('fediverse.outbox') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">View all &rarr;</a>
                </div>
            @endif
        </div>
    </div>
@endsection
