{{-- Brazilian document input masks (CPF/CNPJ). Injected via the theme `footer`
     render hook, so it only loads in the client area. Uses event delegation so
     it keeps working across Livewire DOM updates. Progressive enhancement:
     validation is always enforced server-side regardless of the mask. --}}
<script>
    (function () {
        function onlyDigits(v) { return (v || '').replace(/\D/g, ''); }

        function maskCpf(v) {
            v = onlyDigits(v).slice(0, 11);
            if (v.length > 9) return v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
            if (v.length > 6) return v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
            if (v.length > 3) return v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
            return v;
        }

        function maskCnpj(v) {
            v = onlyDigits(v).slice(0, 14);
            if (v.length > 12) return v.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{1,2})/, '$1.$2.$3/$4-$5');
            if (v.length > 8) return v.replace(/(\d{2})(\d{3})(\d{3})(\d{1,4})/, '$1.$2.$3/$4');
            if (v.length > 5) return v.replace(/(\d{2})(\d{3})(\d{1,3})/, '$1.$2.$3');
            if (v.length > 2) return v.replace(/(\d{2})(\d{1,3})/, '$1.$2');
            return v;
        }

        function keyFor(el) {
            var model = el.getAttribute('wire:model')
                || el.getAttribute('wire:model.blur')
                || el.getAttribute('wire:model.live')
                || el.id || el.name || '';
            return model.split('.').pop();
        }

        document.addEventListener('input', function (e) {
            var el = e.target;
            if (!el || el.tagName !== 'INPUT') return;
            var key = keyFor(el);
            var masked = null;
            if (key === 'cpf') masked = maskCpf(el.value);
            else if (key === 'cnpj') masked = maskCnpj(el.value);
            if (masked !== null && masked !== el.value) {
                el.value = masked;
                // Keep Livewire's bound value in sync with the masked display.
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }, true);
    })();
</script>
