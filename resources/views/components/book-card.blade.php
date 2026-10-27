@props(['book'])

<article class="flex gap-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    @if ($book->cover_path)
        <img
            src="{{ Storage::disk('public')->url($book->cover_path) }}"
            alt="Cover of {{ $book->title }}"
            class="h-24 w-16 shrink-0 rounded object-cover"
        >
    @else
        <div class="flex h-24 w-16 shrink-0 items-center justify-center rounded bg-gray-100 text-2xl text-gray-400 dark:bg-gray-800">📖</div>
    @endif

    <div class="flex min-w-0 flex-col gap-1">
        <h3 class="truncate font-semibold text-gray-900 dark:text-white">{{ $book->title }}</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $book->author->name }}</p>

        {{ $slot }}
    </div>
</article>
