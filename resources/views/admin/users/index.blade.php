@extends('layouts.app-admin')
@section('title', 'Manage Users — Admin')
@section('page-title', 'Manage Users')

@php
    $roleBadge = [
        'dsa'       => 'bg-purple-50 text-purple-600 border-purple-200',
        'vpa'       => 'bg-teal-50 text-teal-700 border-teal-200',
        'adviser'   => 'bg-blue-50 text-blue-600 border-blue-200',
        'president' => 'bg-amber-50 text-amber-600 border-amber-200',
        'treasurer' => 'bg-amber-50 text-amber-600 border-amber-200',
        'mmo'       => 'bg-amber-50 text-amber-600 border-amber-200',
        'member'    => 'bg-gray-50 text-gray-600 border-gray-200',
    ];
@endphp

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <h1 class="text-white font-bold text-xl">Manage Users</h1>
    <p class="text-white/60 text-xs mt-1">Change a user's role. Every change is recorded in the audit log.</p>
</div>

<div class="px-4 py-5 space-y-4 max-w-4xl">

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.users.index') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-3">
        <div class="flex items-center gap-2">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search by name or email…"
                   class="flex-1 border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
            <button type="submit" class="bg-[#07873e] text-white text-xs font-semibold rounded-lg px-4 py-2 hover:opacity-90 transition-opacity">
                Search
            </button>
            @if($search)
                <a href="{{ route('admin.users.index') }}" class="text-xs text-gray-400 font-medium hover:text-gray-600">Clear</a>
            @endif
        </div>
    </form>

    {{-- User list --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm divide-y divide-gray-50">
        @forelse($users as $user)
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 px-4 py-3">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $user->name }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ $user->email }}</p>
                    @if($user->department || $user->course)
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ $user->department?->short_name }}{{ $user->department && $user->course ? ' · ' : '' }}{{ $user->course?->name }}</p>
                    @endif
                </div>

                <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-semibold border w-fit {{ $roleBadge[$user->role] ?? 'bg-gray-50 text-gray-600 border-gray-200' }}">
                    {{ ucfirst($user->role) }}
                </span>

                @if($user->id === auth_user_id())
                    <span class="text-xs text-gray-400 italic w-full sm:w-48 text-right">(your account)</span>
                @else
                    <form method="POST" action="{{ route('admin.users.role.update', $user) }}"
                          class="flex items-center gap-2 w-full sm:w-auto"
                          onsubmit="return confirm('Change {{ $user->name }}\'s role to the selected value? This is recorded in the audit log.')">
                        @csrf
                        @method('PATCH')
                        <select name="role" class="border border-gray-300 rounded-lg text-xs px-2.5 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                            @foreach($roles as $role)
                                <option value="{{ $role }}" {{ $user->role === $role ? 'selected' : '' }}>{{ ucfirst($role) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="bg-[#07873e] text-white text-xs font-semibold rounded-lg px-3 py-2 hover:opacity-90 transition-opacity flex-shrink-0">
                            Update
                        </button>
                    </form>
                @endif
            </div>
        @empty
            <p class="text-gray-400 text-sm text-center py-6">No users found.</p>
        @endforelse
    </div>

    @if($users->hasPages())
        <div class="pt-2">{{ $users->links() }}</div>
    @endif

</div>
@endsection
