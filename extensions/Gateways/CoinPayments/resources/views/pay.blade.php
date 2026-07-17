<div class="mt-2 text-white">
    <p class="mb-3 text-sm text-gray-300">
        You will be redirected to CoinPayments to complete your payment securely in
        {{ $currency2 }} (or any supported coin — CoinPayments converts automatically).
    </p>

    <a href="{{ $checkoutUrl }}" target="_blank" rel="noopener"
        class="block text-center mt-2 bg-secondary-500 text-white hover:bg-secondary py-2 px-4 rounded-md w-full bg-gradient-to-tr from-secondary via-50% via-20% via-secondary to-[#5573FD80] duration-300">
        Pay with CoinPayments
    </a>

    @if ($qrcodeUrl && $address)
        <div class="mt-4 text-center">
            <p class="text-xs text-gray-400 mb-2">Or send exactly
                <span class="font-mono">{{ $cryptoAmount }} {{ $currency2 }}</span> to:</p>
            <img src="{{ $qrcodeUrl }}" alt="Payment QR code" class="mx-auto rounded-md" width="160" height="160">
            <p class="mt-2 font-mono text-xs break-all">{{ $address }}</p>
        </div>
    @endif

    <p class="mt-4 text-xs text-gray-500">
        This page will update automatically once the payment is confirmed on the blockchain.
        You can safely close the CoinPayments tab after paying.
    </p>
</div>
