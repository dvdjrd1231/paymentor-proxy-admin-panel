{{-- One error page for the whole platform (Leandro, 2026-09-07: "Every Admin Error page
     should be updated as WHMCS page standard format. Also, every client error page should
     have correct alignment"). --}}
@php
    $isAdmin = request()->is(config('app.admin_path', 'admin') . '*');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} — {{ $title }}</title>
    @if (class_exists(\Paymenter\Extensions\Others\AdminOps\AdminOps::class))
        <link rel="stylesheet" href="{{ \Paymenter\Extensions\Others\AdminOps\AdminOps::styleUrl() }}">
    @endif
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f5f6f8; color: #2b2b2b; }
        .ao-err-bar { background: #1a4d80; color: #fff; padding: 0.9rem 1.4rem; font-size: 1.15rem; font-weight: 600; }
        .ao-err-wrap { display: flex; justify-content: center; padding: 3rem 1.25rem; }
        .ao-err-card { width: 100%; max-width: 34rem; background: #fff; border: 1px solid #ddd; border-radius: 6px; overflow: hidden; }
        .ao-err-head { padding: 0.7rem 1.1rem; background: #f5f5f5; border-bottom: 1px solid #ddd; font-weight: 600; }
        .ao-err-body { padding: 1.4rem 1.1rem; text-align: center; }
        .ao-err-code { font-size: 3.2rem; font-weight: 700; line-height: 1; color: #1a4d80; }
        .ao-err-title { margin: 0.5rem 0 0.35rem; font-size: 1.15rem; font-weight: 600; }
        .ao-err-msg { margin: 0; color: #555; line-height: 1.5; }
        .ao-err-foot { display: flex; justify-content: center; gap: 0.6rem; padding: 0 1.1rem 1.3rem; }
        .ao-err-btn { display: inline-flex; align-items: center; height: 2.1rem; padding: 0 1rem; border: 1px solid #2b6cb0; border-radius: 4px; background: #337ab7; color: #fff; font-size: 0.9rem; text-decoration: none; }
        .ao-err-btn:hover { background: #286090; }
        .ao-err-btn-alt { background: #fff; border-color: #ccc; color: #2b2b2b; }
        .ao-err-btn-alt:hover { background: #f0f0f0; }
    </style>
</head>
<body>
    @if ($isAdmin)
        <div class="ao-err-bar">Paymenter</div>
    @endif

    <div class="ao-err-wrap">
        <div class="ao-err-card">
            <div class="ao-err-head">{{ $title }}</div>
            <div class="ao-err-body">
                <div class="ao-err-code">{{ $code }}</div>
                <h1 class="ao-err-title">{{ $title }}</h1>
                <p class="ao-err-msg">{{ $message }}</p>
            </div>
            <div class="ao-err-foot">
                @if ($isAdmin)
                    <a class="ao-err-btn" href="{{ url('/' . config('app.admin_path', 'admin')) }}">Return to Dashboard</a>
                @else
                    <a class="ao-err-btn" href="{{ url('/') }}">Return Home</a>
                @endif
                <a class="ao-err-btn ao-err-btn-alt" href="javascript:history.back()">Go Back</a>
            </div>
        </div>
    </div>
</body>
</html>
