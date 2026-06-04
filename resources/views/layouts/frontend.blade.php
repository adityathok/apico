<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', config('app.name', 'Laravel'))</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @fonts
    @vite(['resources/css/app.css'])
    @stack('head')
</head>

<body class="bg-default font-sans antialiased text-default">
    <div class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-40 border-b border-default bg-default/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
                <a href="/" class="flex items-center gap-3">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-inverted text-sm font-semibold text-inverted">
                        {{ str(config('app.name', 'A'))->substr(0, 1)->upper() }}
                    </span>
                    <span class="text-base font-semibold tracking-tight text-highlighted">
                        {{ config('app.name', 'Laravel') }}
                    </span>
                </a>

                <nav class="flex items-center gap-1 text-sm">
                    <a
                        href="/"
                        class="rounded-md px-3 py-2 text-muted transition hover:bg-muted hover:text-highlighted">
                        Home
                    </a>

                    @auth
                    <a
                        href="/posts"
                        class="rounded-md px-3 py-2 text-muted transition hover:bg-muted hover:text-highlighted">
                        Posts
                    </a>
                    @else
                    <a
                        href="/login"
                        class="rounded-md px-3 py-2 text-muted transition hover:bg-muted hover:text-highlighted">
                        Login
                    </a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="@yield('main_class', 'mx-auto w-full max-w-3xl flex-1 px-6 py-10 sm:py-14')">
            @yield('content')
        </main>

        <footer class="border-t border-default">
            <div class="mx-auto flex max-w-6xl flex-col gap-2 px-6 py-8 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
                <p>
                    &copy; {{ now()->year }} {{ config('app.name', 'Laravel') }}.
                </p>
                <p>
                    Stories, notes, and updates.
                </p>
            </div>
        </footer>
    </div>

    @stack('scripts')
</body>

</html>