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

    /* Issue #38: a Brazilian registration is either a Pessoa Física or a Pessoa Jurídica, and
       the two need different documents — RG and CPF for a citizen, CNPJ with the state and
       municipal registrations for a company. Showing both sets at once asked companies for a
       CPF, so the form now asks the country, then the kind of person, then only what that
       kind actually has.

       This serves the forms that render their properties generically through the
       x-form.properties component — the account profile among them — which is why fields are
       found by their `name`, the one attribute that component always sets. (Written without
       angle brackets on purpose: Blade compiles a component tag wherever it appears, a JS
       comment included, and an unclosed one breaks the view.) The Proxy theme's
       registration view lays its fields out by hand and does the same toggling in Alpine, so
       it carries no `name` attributes and nothing here matches it: the two never both act on
       the same form.

       Presentation only: which documents are *required* is decided server-side by the
       conditional rules on each field, so this can be switched off, bypassed, or fail to run
       and the registration is still validated correctly. That is also why the default state
       is "everything visible" — with no script, the form degrades to the old behaviour of
       showing every field rather than hiding fields nobody can then fill in. */
    (function () {
        var INDIVIDUAL = ['cpf', 'rg'];
        var COMPANY = ['trade_name', 'cnpj', 'state_registration', 'state_registration_exempt', 'municipal_registration'];
        var MANDATORY = { cpf: 'individual', cnpj: 'company' };

        function field(key) {
            return document.querySelector('[name="properties.' + key + '"]');
        }

        /* The theme wraps a field in a <fieldset class="wf-field"> (or a .wf-check for a
           checkbox), which is what has to be hidden — hiding the control alone would leave
           its label behind. */
        function box(el) {
            return el.closest('fieldset, .wf-check, .wf-field') || el.parentElement;
        }

        function show(key, visible, required) {
            var el = field(key);
            if (!el) return;
            var wrapper = box(el);
            if (wrapper) wrapper.style.display = visible ? '' : 'none';
            if (required !== undefined) el.required = visible && required;
        }

        function isBrazil(value) {
            return ['brazil', 'brasil', 'br'].indexOf((value || '').trim().toLowerCase()) !== -1;
        }

        function kindOf(value) {
            var v = (value || '').trim().toLowerCase();
            if (!v) return '';
            return (v.indexOf('jur') !== -1 || v.indexOf('company') !== -1 || v === 'pj') ? 'company' : 'individual';
        }

        function apply() {
            var country = field('country');
            var type = field('person_type');
            if (!country || !type) return;          // not a form that carries these fields

            var brazilian = isBrazil(country.value);
            var kind = brazilian ? kindOf(type.value) : '';

            show('person_type', brazilian);

            INDIVIDUAL.concat(COMPANY).forEach(function (key) {
                var belongs = INDIVIDUAL.indexOf(key) !== -1 ? 'individual' : 'company';
                show(key, kind === belongs, MANDATORY[key] === belongs);
            });

            /* An exempt company writes the word rather than a number, so the field shows
               what the invoice will say instead of sitting empty and disabled. */
            var exempt = field('state_registration_exempt');
            var ie = field('state_registration');
            if (exempt && ie && kind === 'company') {
                ie.disabled = exempt.checked;
                if (exempt.checked && ie.value.trim() === '') {
                    ie.value = 'ISENTO';
                    ie.dispatchEvent(new Event('change', { bubbles: true }));
                } else if (!exempt.checked && ie.value.trim() === 'ISENTO') {
                    ie.value = '';
                    ie.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }

        document.addEventListener('change', apply, true);
        document.addEventListener('DOMContentLoaded', apply);
        document.addEventListener('livewire:navigated', apply);
        // Livewire replaces the form's DOM on every round trip, which takes the inline
        // display with it.
        document.addEventListener('livewire:init', function () {
            if (window.Livewire && window.Livewire.hook) window.Livewire.hook('morphed', apply);
        });
        apply();
    })();
</script>
