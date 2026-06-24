@extends('layouts.canvas')

@section('title', 'Changelog - '.$project->name.' - '.config('app.name', 'Laravel'))

@section('content')
<main class="min-h-screen bg-default">
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-8 px-4 py-10 sm:px-6 lg:px-8">
        <header class="rounded-2xl border border-default bg-muted/30 p-6 shadow-sm">
            <div class="space-y-3">
                <p class="text-sm font-medium uppercase tracking-[0.2em] text-muted">
                    Project Changelog
                </p>
                <div class="space-y-2">
                    <h1 class="text-2xl font-semibold tracking-tight text-highlighted sm:text-3xl">
                        {{ $project->name }}
                    </h1>
                    <p class="max-w-3xl text-sm leading-6 text-muted sm:text-base">
                        @if($project->description)
                        <span>
                            {{ $project->description ?: 'Riwayat perubahan versi untuk project ini.' }}
                        </span>
                        @endif

                        @if($project->version)
                        <span class="text-highlighted bg-accent px-2 rounded-full">
                            {{ $project->version ?: 'Belum diatur' }}
                        </span>
                        @endif
                    </p>
                </div>
            </div>
        </header>

        @if ($project->changelogs->isEmpty())
        <section class="rounded-2xl border border-dashed border-default bg-default px-6 py-12 text-center">
            <h2 class="text-xl font-semibold text-highlighted">
                Belum ada changelog
            </h2>
            <p class="mx-auto mt-3 max-w-2xl text-sm leading-6 text-muted">
                Project ini belum memiliki catatan perubahan yang dipublikasikan.
            </p>
        </section>
        @else
        <section class="space-y-4">
            @foreach ($project->changelogs->sortByDesc('created_at') as $changelog)
            <article class="overflow-hidden rounded-2xl border border-default bg-default shadow-sm">
                <div class="border-b border-default bg-muted/20 px-6 py-4">
                    <div>
                        <h2 class="text-base font-semibold text-highlighted">
                            Version {{ $changelog->project_version }}
                        </h2>
                        <p class="mt-1 text-xs text-muted">
                            {{ $changelog->updated_at?->translatedFormat('d F Y, H:i') }}
                        </p>
                    </div>
                </div>

                <div class="px-6 py-5">
                    <div class="prose prose-sm max-w-none whitespace-pre-line text-default prose-headings:text-highlighted prose-p:text-default prose-strong:text-highlighted">
                        {{ $changelog->changelog_content }}
                    </div>
                </div>
            </article>
            @endforeach
        </section>
        @endif
    </div>
</main>
@endsection