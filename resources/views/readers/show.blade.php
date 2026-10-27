<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $reader->name }}'s bookshelf · {{ config('app.name', 'Laravel') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
        <main class="mx-auto flex max-w-5xl flex-col gap-10 px-4 py-12">
            <header class="flex flex-col gap-2">
                <p class="text-sm font-medium tracking-wide text-green-600 uppercase dark:text-green-400">Bookshelf</p>
                <h1 class="text-3xl font-semibold">{{ $reader->name }}</h1>
                <p class="text-gray-600 dark:text-gray-400">
                    {{ $finishedThisYear }} {{ str('book')->plural($finishedThisYear) }} finished in {{ now()->year }}@if ($goal) of a {{ $goal->target_books }} book goal @endif
                </p>

                @if ($goal)
                    <div class="h-2 w-full max-w-sm overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                        <div class="h-full rounded-full bg-green-500" style="width: {{ $goal->progress_percentage }}%"></div>
                    </div>
                @endif
            </header>

            <section class="flex flex-col gap-4">
                <h2 class="text-xl font-semibold">Currently reading</h2>

                @forelse ($currentlyReading as $book)
                    @if ($loop->first)
                        <div class="grid gap-4 sm:grid-cols-2">
                    @endif

                    <x-book-card :book="$book">
                        @if ($book->progress_percentage !== null)
                            <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <div class="h-1.5 w-24 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                                    <div class="h-full rounded-full bg-amber-500" style="width: {{ $book->progress_percentage }}%"></div>
                                </div>
                                {{ $book->progress_percentage }}%
                            </div>
                        @endif
                    </x-book-card>

                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <p class="text-gray-600 dark:text-gray-400">Nothing on the nightstand right now.</p>
                @endforelse
            </section>

            <section class="flex flex-col gap-4">
                <h2 class="text-xl font-semibold">Recently finished</h2>

                @forelse ($recentlyFinished as $book)
                    @if ($loop->first)
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @endif

                    <x-book-card :book="$book">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            @if ($book->rating)
                                <span class="text-amber-500" aria-label="{{ $book->rating }} out of 5 stars">{{ str_repeat('★', $book->rating) }}{{ str_repeat('☆', 5 - $book->rating) }}</span>
                            @endif
                            @if ($book->read_at)
                                <span>{{ $book->read_at->format('M j, Y') }}</span>
                            @endif
                        </p>
                    </x-book-card>

                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <p class="text-gray-600 dark:text-gray-400">No finished books yet.</p>
                @endforelse
            </section>

            @if ($shelves->isNotEmpty())
                <section class="flex flex-col gap-4">
                    <h2 class="text-xl font-semibold">Shelves</h2>

                    <ul class="flex flex-wrap gap-2">
                        @foreach ($shelves as $shelf)
                            <li class="rounded-full border border-gray-200 bg-white px-3 py-1 text-sm dark:border-gray-800 dark:bg-gray-900">
                                {{ $shelf->name }} <span class="text-gray-500">· {{ $shelf->books_count }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </main>
    </body>
</html>
