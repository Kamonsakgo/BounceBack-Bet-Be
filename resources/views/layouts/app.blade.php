<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'BounceBack Bet') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] min-h-screen flex flex-col">
        <header class="border-b border-[#e3e3e0] dark:border-[#3E3E3A]">
            <div class="max-w-7xl mx-auto px-5 py-3 flex items-center justify-between">
                <a href="{{ url('/') }}" class="font-medium">{{ config('app.name', 'BounceBack Bet') }}</a>
                <nav class="flex items-center gap-4 text-sm">
                    <a href="{{ url('/') }}" class="hover:underline">หน้าแรก</a>
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="hover:underline">เข้าสู่ระบบ</a>
                    @endif
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="hover:underline">สมัครสมาชิก</a>
                    @endif
                </nav>
            </div>
        </header>

        <main class="flex-1">
            {{ $slot ?? '' }}
            @yield('content')
        </main>

        <footer class="border-t border-[#e3e3e0] dark:border-[#3E3E3A] text-sm text-[#706f6c] dark:text-[#A1A09A]">
            <div class="max-w-7xl mx-auto px-5 py-4">&copy; {{ date('Y') }} {{ config('app.name', 'BounceBack Bet') }}</div>
        </footer>
    </body>
    </html>


