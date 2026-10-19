{{--
    Students (and staff) and advisers sign in on separate pages: advisers are employees,
    who sign in with their adviser number too. $active: 'student' | 'adviser'.
--}}
<nav class="grid grid-cols-2 gap-1 bg-gray-100 rounded-full p-1 mb-7" aria-label="Who is signing in">
    @foreach (['student' => ['Student', route('login')], 'adviser' => ['Adviser', route('login.adviser')]] as $tab => [$label, $url])
        <a href="{{ $url }}" @if ($tab === $active) aria-current="page" @endif
           class="text-center text-sm font-semibold py-2.5 rounded-full transition-colors {{ $tab === $active ? 'bg-white text-green-800 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
            {{ $label }}
        </a>
    @endforeach
</nav>
