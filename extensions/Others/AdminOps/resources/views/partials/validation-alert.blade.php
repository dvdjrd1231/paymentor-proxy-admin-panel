{{-- The reference's validation alert: a bordered red panel above the form it belongs to,
     headed "Validation Error", with one line per problem (Leandro, 2026-09-18). Ours put a
     bare pink strip below the form, where it sat under the fold on a long form. --}}
@if ($errors->any())
    <div class="ao-valert" role="alert">
        <x-filament::icon icon="ri-close-circle-fill" class="ao-valert-ic" />
        <div class="ao-valert-body">
            <b class="ao-valert-title">Validation Error</b>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
