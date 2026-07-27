<div class="mt-2 text-white">
    <p class="mb-3 text-sm text-gray-300">
        You'll be redirected to Cryptomus to complete your cryptocurrency payment.
    </p>

    @if ($paymentUrl)
        <a href="{{ $paymentUrl }}" target="_blank" rel="noopener"
            class="block text-center mt-2 bg-secondary-500 text-white hover:bg-secondary py-2 px-4 rounded-md w-full bg-gradient-to-tr from-secondary via-50% via-20% via-secondary to-[#5573FD80] duration-300">
            Pay with Cryptomus
        </a>
    @else
        <p class="text-red-400 text-sm">Could not start the Cryptomus payment. Please try another method.</p>
    @endif

    <p class="mt-4 text-xs text-gray-500">
        This page updates automatically once the payment is confirmed. You can close the
        Cryptomus tab after paying.
    </p>
</div>
