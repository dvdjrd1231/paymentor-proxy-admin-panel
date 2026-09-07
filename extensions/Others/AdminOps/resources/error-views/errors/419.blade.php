{{-- Delegates to the shared error page; see errors/_page.blade.php. --}}
@include('errors._page', [
    'code' => 419,
    'title' => 'Page Expired',
    'message' => 'This page sat idle too long and its security token expired. Load it again and retry.',
])
