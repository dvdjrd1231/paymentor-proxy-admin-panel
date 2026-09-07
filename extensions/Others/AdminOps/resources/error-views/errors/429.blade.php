{{-- Delegates to the shared error page; see errors/_page.blade.php. --}}
@include('errors._page', [
    'code' => 429,
    'title' => 'Too Many Requests',
    'message' => 'Too many requests were made in a short time. Wait a moment and try again.',
])
