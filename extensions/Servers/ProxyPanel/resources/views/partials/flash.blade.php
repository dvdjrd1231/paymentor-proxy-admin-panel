{{-- Shared by every management view: what the last action said. --}}
@if (session('success'))
    <div class="wf-alert wf-alert--info">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="wf-alert wf-alert--danger">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="wf-alert wf-alert--danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif
