{{-- Delegates to the shared error page; see errors/_page.blade.php. --}}
@include('errors._page', [
    'code' => 503,
    'title' => 'Under Maintenance',
    'message' => 'The system is briefly unavailable while maintenance completes. Please try again shortly.',
])
