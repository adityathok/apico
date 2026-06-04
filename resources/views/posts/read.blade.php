<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $post->title }} - {{ config('app.name', 'Laravel') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @fonts
    @vite(['resources/css/app.css'])
</head>

<body class="bg-default font-sans antialiased text-default">
    <main class="mx-auto min-h-screen max-w-3xl px-6 py-10 sm:py-14">
        <article class="space-y-8">
            <header class="space-y-4">
                <div class="flex flex-wrap items-center gap-2 text-sm text-muted">
                    @if ($post->published_at)
                    <time datetime="{{ $post->published_at->toIso8601String() }}">
                        {{ $post->published_at->translatedFormat('d F Y') }}
                    </time>
                    @else
                    <span>Draft</span>
                    @endif

                    @if ($post->user)
                    <span aria-hidden="true">/</span>
                    <span>{{ $post->user->name }}</span>
                    @endif
                </div>

                <h1 class="text-3xl font-semibold tracking-tight text-highlighted sm:text-4xl">
                    {{ $post->title }}
                </h1>

                @if ($post->excerpt)
                <p class="text-lg leading-8 text-muted">
                    {{ $post->excerpt }}
                </p>
                @endif
            </header>

            @if ($imageUrl)
            <figure class="space-y-3">
                <img
                    src="{{ $imageUrl }}"
                    alt="{{ $post->image_caption ?? $post->title }}"
                    class="aspect-video w-full rounded-lg border border-default object-cover">

                @if ($post->image_caption)
                <figcaption class="text-sm text-muted">
                    {{ $post->image_caption }}
                </figcaption>
                @endif
            </figure>
            @endif

            <div class="prose prose-neutral max-w-none dark:prose-invert">
                {!! $post->content !!}
            </div>

            @if ($post->categories->isNotEmpty() || $post->tags->isNotEmpty())
            <footer class="flex flex-wrap gap-2 border-t border-default pt-6">
                @foreach ($post->categories as $category)
                <span class="rounded-md bg-muted px-2.5 py-1 text-sm text-muted">
                    {{ $category->name }}
                </span>
                @endforeach

                @foreach ($post->tags as $tag)
                <span class="rounded-md border border-default px-2.5 py-1 text-sm text-muted">
                    #{{ $tag->name }}
                </span>
                @endforeach
            </footer>
            @endif
        </article>
    </main>
</body>

</html>