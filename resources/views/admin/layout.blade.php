<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') – {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex">
        <aside class="w-56 bg-gray-800 text-white min-h-screen py-4 relative">
            <div class="px-4 mb-6">
                <a href="{{ route('admin.dashboard') }}" class="text-lg font-semibold">{{ config('app.name') }} Admin</a>
            </div>
            <nav class="space-y-1 px-2">
                <a href="{{ route('admin.dashboard') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.dashboard') ? 'bg-gray-700' : 'hover:bg-gray-700' }}">Dashboard</a>
                <a href="{{ route('admin.settings.index') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.settings.*') ? 'bg-gray-700' : 'hover:bg-gray-700' }}">Settings</a>
                <a href="{{ route('admin.packages.index') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.packages.*') ? 'bg-gray-700' : 'hover:bg-gray-700' }}">Packages</a>
                <a href="{{ route('admin.gifts.index') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.gifts.*') ? 'bg-gray-700' : 'hover:bg-gray-700' }}">Gifts</a>
                <a href="{{ route('admin.themes.index') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.themes.*') ? 'bg-gray-700' : 'hover:bg-gray-700' }}">Room Themes</a>
                <a href="{{ route('admin.privileges.index') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.privileges.*') ? 'bg-gray-700' : 'hover:bg-gray-700' }}">Wealth Privileges</a>
                <a href="{{ route('admin.countries.index') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.countries.*') ? 'bg-gray-700' : 'hover:bg-gray-700' }}">Countries</a>
                <a href="{{ route('admin.users.index') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.users.*') ? 'bg-gray-700' : 'hover:bg-gray-700' }}">Users</a>
                <a href="{{ route('admin.withdrawals.index') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.withdrawals.*') ? 'bg-gray-700' : 'hover:bg-gray-700' }}">Withdrawals</a>
                <a href="{{ route('admin.reports.index') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.reports.*') ? 'bg-gray-700' : 'hover:bg-gray-700' }}">Reports</a>
            </nav>
            <div class="absolute bottom-4 left-4">
                <form method="POST" action="{{ route('admin.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-sm text-gray-300 hover:text-white">Logout</button>
                </form>
            </div>
        </aside>
        <main class="flex-1 p-6">
            @if (session('success'))
                <div class="mb-4 px-4 py-2 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 px-4 py-2 bg-red-100 text-red-800 rounded">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
