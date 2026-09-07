{{-- Delegates to the shared error page; see errors/_page.blade.php. --}}
@include('errors._page', [
    'code' => 404,
    'title' => 'Page Not Found',
    'message' => 'The page you were looking for does not exist, or has moved.',
])
