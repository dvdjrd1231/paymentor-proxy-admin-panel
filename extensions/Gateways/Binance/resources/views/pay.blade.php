<div class="mt-2 text-white">
    <p class="mb-3 text-sm text-gray-300">
        You'll be redirected to Binance Pay to complete your payment in {{ $currency }}.
    </p>

    @if ($checkoutUrl)
        <a href="{{ $checkoutUrl }}" target="_blank" rel="noopener"
            class="block text-center mt-2 bg-secondary-500 text-white hover:bg-secondary py-2 px-4 rounded-md w-full bg-gradient-to-tr from-secondary via-50% via-20% via-secondary to-[#5573FD80] duration-300">
            Pay with Binance Pay
        </a>
    @endif

    @if ($qrcodeLink)
        <div class="mt-4 text-center">
            <p class="text-xs text-gray-400 mb-2">Or scan with the Binance app:</p>
            <img src="{{ $qrcodeLink }}" alt="Binance Pay QR code" class="mx-auto rounded-md" width="180" height="180">
        </div>
    @endif

    <p class="mt-4 text-xs text-gray-500">
        This page updates automatically once Binance confirms the payment. You can close the
        Binance Pay tab after paying.
    </p>
</div>
