{{--
    WHMCS-style two-factor prompt. Keeps the default theme's Alpine logic:
    the six digits are joined client-side and sent with a single verify()
    round-trip (the code is set with the deferred flag so typing never
    triggers extra Livewire requests).
--}}
<div class="wf-page wf-page--auth">
    <div class="wf-title">
        <h1>{{ __('auth.verify_2fa') }}</h1>
        <span>{{ __('account.input.two_factor_code') }}</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-form-narrow" style="text-align:center">
        <form
            x-on:submit.prevent="submit"
            x-data="{
                busy: false,
                isNumber(value) { return value.match(/^[0-9]$/g); },
                getCode() {
                    return [
                        $refs.num1.value, $refs.num2.value, $refs.num3.value,
                        $refs.num4.value, $refs.num5.value, $refs.num6.value
                    ].join('');
                },
                submit() {
                    if (this.busy) return;
                    this.busy = true;
                    $wire.set('code', this.getCode(), false);
                    $wire.verify().finally(() => { this.busy = false; });
                },
                fillInputs(val) {
                    $refs.num1.value = val[0] || '';
                    $refs.num2.value = val[1] || '';
                    $refs.num3.value = val[2] || '';
                    $refs.num4.value = val[3] || '';
                    $refs.num5.value = val[4] || '';
                    $refs.num6.value = val[5] || '';
                },
                handlePaste(e) {
                    let num = e.clipboardData.getData('text/plain').trim();
                    if (num.length === 6 && num.match(/^[0-9]+$/g)) {
                        e.preventDefault();
                        this.fillInputs(num);
                        this.submit();
                    }
                },
                handleAutofill(e) {
                    let val = e.target.value;
                    if (val.length === 6) {
                        this.fillInputs(val);
                        this.submit();
                    } else {
                        if (val.length > 1) e.target.value = val.charAt(0);
                        if (this.isNumber(e.target.value)) $refs.num2.focus();
                        else e.target.value = '';
                    }
                }
            }"
        >
            <div class="wf-otp" wire:ignore>
                <input x-ref="num1" id="otp-input" autocomplete="one-time-code" inputmode="numeric" type="text"
                    autofocus class="wf-input wf-otp-box"
                    x-on:input="handleAutofill($event)" x-on:paste="handlePaste" />
                <input x-ref="num2" type="text" maxlength="1" inputmode="numeric" class="wf-input wf-otp-box"
                    x-on:input="isNumber($refs.num2.value) ? $refs.num3.focus() : $refs.num2.value = ''"
                    x-on:keydown.backspace="$refs.num2.value === '' ? $refs.num1.focus() : null" />
                <input x-ref="num3" type="text" maxlength="1" inputmode="numeric" class="wf-input wf-otp-box"
                    x-on:input="isNumber($refs.num3.value) ? $refs.num4.focus() : $refs.num3.value = ''"
                    x-on:keydown.backspace="$refs.num3.value === '' ? $refs.num2.focus() : null" />

                <span class="wf-otp-sep">-</span>

                <input x-ref="num4" type="text" maxlength="1" inputmode="numeric" class="wf-input wf-otp-box"
                    x-on:input="isNumber($refs.num4.value) ? $refs.num5.focus() : $refs.num4.value = ''"
                    x-on:keydown.backspace="$refs.num4.value === '' ? $refs.num3.focus() : null" />
                <input x-ref="num5" type="text" maxlength="1" inputmode="numeric" class="wf-input wf-otp-box"
                    x-on:input="isNumber($refs.num5.value) ? $refs.num6.focus() : $refs.num5.value = ''"
                    x-on:keydown.backspace="$refs.num5.value === '' ? $refs.num4.focus() : null" />
                <input x-ref="num6" type="text" maxlength="1" inputmode="numeric" class="wf-input wf-otp-box"
                    x-on:input="if(isNumber($refs.num6.value)) { if(getCode().length === 6) submit(); } else { $refs.num6.value = '' }"
                    x-on:keydown.backspace="$refs.num6.value === '' ? $refs.num5.focus() : null" />
            </div>

            @error('code') <span class="wf-error">{{ $message }}</span> @enderror

            <div class="wf-actions wf-actions--center">
                <button type="submit" class="wf-btn" x-bind:disabled="busy">
                    <span x-show="!busy">{{ __('auth.verify') }}</span>
                    <span x-show="busy" x-cloak>…</span>
                </button>
            </div>
        </form>
    </div>
</div>
