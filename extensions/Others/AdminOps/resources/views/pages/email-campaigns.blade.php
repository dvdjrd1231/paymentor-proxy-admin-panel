{{--
    Email Campaigns, to the reference screenshots: Step 1 of 2 — Message Type, Client
    Criteria, Product/Service Criteria, Compose Message, the footnote, and the blue banner
    when nobody matches. Step 2 — subject, message, Send.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-ec">
        @if ($noRecipients)
            <div class="ao-ec-banner">
                <span class="ao-ec-banner-ic" aria-hidden="true">&#8505;</span>
                The criteria selected for this email has resulted in no recipients which means it cannot be sent
            </div>
        @endif

        @if ($step === 1)
            <h4 class="ao-ano-heading">Step 1 of 2: Configure Campaign Recipients</h4>
            <p class="ao-ec-intro">
                This mass mail tool allows you to send emails to selective groups of your clients.
                The type of email you choose to send will determine what merge fields you can include
                within it. Use Ctrl+Click to make multiple selections.
            </p>

            <h4 class="ao-ano-heading">Message Type</h4>
            <div class="ao-anc-card">
                <label class="ao-anc-row">
                    <span>Campaign Name</span>
                    <input type="text" class="ao-w-45" wire:model="campaignName" placeholder="e.g. August maintenance notice" required>
                </label>
                <div class="ao-anc-row">
                    <span>Email Type</span>
                    <span class="ao-anc-field ao-ec-radios">
                        <label><input type="radio" value="general" wire:model.live="emailType"> General</label>
                        <label><input type="radio" value="product" wire:model.live="emailType"> Product/Service</label>
                        <label class="ao-ano-off" title="Paymenter has no separately-billed addon records"><input type="radio" disabled> Addon</label>
                        <label class="ao-ano-off" title="No domain registrar is connected"><input type="radio" disabled> Domain</label>
                    </span>
                </div>
            </div>

            <h4 class="ao-ano-heading">Client Criteria</h4>
            <div class="ao-anc-card">
                <label class="ao-anc-row ao-ec-multi">
                    <span>Country</span>
                    <select multiple size="4" wire:model.live="countries">
                        @foreach ($countryOptions as $country)
                            <option value="{{ $country }}">{{ $country }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ao-anc-row ao-ec-multi">
                    <span>Client Status</span>
                    <select multiple size="2" wire:model.live="clientStatuses">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </label>
            </div>

            @if ($emailType === 'product')
                <h4 class="ao-ano-heading">Product/Service Criteria</h4>
                <div class="ao-anc-card">
                    <label class="ao-anc-row ao-ec-multi">
                        <span>Product/Service</span>
                        <select multiple size="6" wire:model.live="products">
                            @foreach ($productOptions as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="ao-anc-row ao-ec-multi">
                        <span>Product/Service Status</span>
                        <select multiple size="4" wire:model.live="serviceStatuses">
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="cancelled">Terminated</option>
                        </select>
                    </label>
                    <div class="ao-anc-row">
                        <span>Send for Each Service</span>
                        <span class="ao-anc-field">
                            <label><input type="checkbox" wire:model.live="perService"> Check to send an email for every matching service *</label>
                        </span>
                    </div>
                </div>
            @endif

            <div class="ao-pr-center">
                <span class="ao-ec-count">{{ number_format($recipientCount) }} matching recipient(s)</span>
                <button type="button" class="ao-cq-addline" wire:click="compose">Compose Message</button>
            </div>

            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            @endif

            <p class="ao-ec-foot">
                * By default, a customer will receive only one copy of the mailing containing merge
                data for the first matching product found in their account. However, checking this
                box will mean an email is sent for each item that matches the given criteria and
                therefore a single client may receive the email multiple times — once for each
                qualifying product they have.
            </p>
        @else
            <h4 class="ao-ano-heading">Step 2 of 2: Compose Message — {{ $campaignName }}</h4>
            <p class="ao-ec-intro">
                Going to <b>{{ number_format($recipientCount) }}</b> recipient(s).
                Merge fields: <code>{name}</code> and, for product emails, <code>{service}</code>.
            </p>

            <div class="ao-anc-card">
                <label class="ao-anc-row">
                    <span>Subject</span>
                    <input type="text" class="ao-w-60" wire:model="subject" placeholder="What the email is about" required>
                </label>
                <label class="ao-anc-row ao-an-body">
                    <span>Message</span>
                    <textarea rows="12" wire:model="body" placeholder="Dear {name}, …" required></textarea>
                </label>
            </div>

            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            @endif

            <div class="ao-pr-center">
                <button type="button" class="ao-cq-addline" wire:click="back">&laquo; Back</button>
                <button type="button" class="ao-find-go" wire:click="send" wire:confirm="Send this campaign to {{ $recipientCount }} recipient(s) now?">Send Message</button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
