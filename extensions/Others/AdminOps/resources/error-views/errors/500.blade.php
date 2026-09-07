{{-- Delegates to the shared error page; see errors/_page.blade.php. --}}
@include('errors._page', [
    'code' => 500,
    'title' => 'Server Error',
    'message' => 'Something went wrong on our end. The details have been logged.',
])
