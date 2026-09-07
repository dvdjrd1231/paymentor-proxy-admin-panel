{{-- Delegates to the shared error page; see errors/_page.blade.php. --}}
@include('errors._page', [
    'code' => 403,
    'title' => 'Forbidden',
    'message' => 'You do not have permission to view this page.',
])
