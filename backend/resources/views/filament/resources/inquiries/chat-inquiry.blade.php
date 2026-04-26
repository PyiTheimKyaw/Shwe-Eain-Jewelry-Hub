@php
    $step = $pipeline['step'] ?? 'consulting';
    $labels = ['Consulting', 'Quoted', 'Awaiting deposit', 'In production', 'Quality review', 'Ready'];
    $stepIndex = match ($step) {
        'consulting' => 0,
        'quoted' => 1,
        'awaiting_deposit' => 2,
        'pre_production' => 2,
        'in_production' => 3,
        'pending_qc' => 4,
        'ready' => 5,
        default => 0,
    };
    /** @var \App\Models\Inquiry $inquiry */
    $inquiry = $this->getRecord();
    $inquiry->loadMissing('customer', 'product', 'attachments');
@endphp

<x-filament-panels::page>
    <div id="jh-chat-poll-sentinel" class="sr-only" wire:poll.visible.2s="pollChatSync" aria-hidden="true"></div>
    <div>
    @if (! $showChat)
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('Chat is unavailable. Ensure') }}
                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-800">FIREBASE_CREDENTIALS</code>,
                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-800">FIREBASE_PROJECT_ID</code>,
                {{ __('and') }}
                <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-800">FIREBASE_WEB_API_KEY</code>
                {{ __('are set, Firestore rules are deployed, and this inquiry has a thread id.') }}
            </p>
        </x-filament::section>
    @else
        @php
            $paymentProfiles = \App\Models\PaymentProfile::query()->active()->ordered()->get();
        @endphp
        <x-filament::section>
            <div
                class="jh-concierge-root flex min-h-0 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-950"
                wire:key="concierge-chat-shell-{{ $inquiry->id }}"
            >
                <div class="flex min-h-0 flex-1 flex-col-reverse lg:flex-row lg:min-h-[min(70vh,36rem)]">
                    {{-- Sidebar: journey + inquiry --}}
                    <aside
                        class="flex w-full shrink-0 flex-col border-t border-gray-200 bg-gray-50/80 dark:border-gray-700 dark:bg-gray-900/50 lg:w-80 lg:border-r lg:border-t-0"
                        wire:ignore.self
                    >
                        <div class="flex max-h-[40vh] flex-col gap-4 overflow-y-auto p-4 lg:max-h-none lg:flex-1">
                            <div class="flex items-center gap-2 border-b border-gray-200 pb-3 dark:border-gray-700">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-primary-500/40 bg-primary-500/10"
                                >
                                    <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-400">
                                        {{ __('Concierge') }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Private atelier') }}</p>
                                </div>
                            </div>

                            <div>
                                <p class="mb-3 text-[10px] font-semibold uppercase tracking-widest text-primary-600/90 dark:text-primary-400/90">
                                    {{ __('Your journey') }}
                                </p>
                                <nav class="relative flex flex-col gap-0" aria-label="{{ __('Pipeline') }}">
                                    @foreach ($labels as $i => $label)
                                        @php
                                            $done = $i < $stepIndex;
                                            $current = $i === $stepIndex;
                                        @endphp
                                        <div class="relative flex gap-3">
                                            @if ($i > 0)
                                                <div
                                                    class="absolute left-[13px] top-0 h-3 w-px -translate-y-full bg-gradient-to-b {{ $done || $current ? 'from-primary-500/50 to-primary-500/30' : 'from-gray-400 to-gray-500 dark:from-gray-600 dark:to-gray-700' }}"
                                                ></div>
                                            @endif
                                            <div
                                                class="relative z-[1] flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 transition-all duration-300 {{ $done ? 'border-primary-500 bg-primary-500/20 text-primary-600 dark:text-primary-400' : ($current ? 'border-primary-500 bg-primary-500/15 shadow-sm dark:shadow-primary-500/20' : 'border-gray-300 bg-gray-100 text-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-500') }}"
                                            >
                                                @if ($done)
                                                    <svg class="h-3.5 w-3.5" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                                @else
                                                    @switch($i)
                                                        @case(0)
                                                            <svg class="h-3.5 w-3.5" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.202 16.617 2.25 14.411 2.25 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>
                                                            @break
                                                        @case(1)
                                                            <svg class="h-3.5 w-3.5" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                                            @break
                                                        @case(2)
                                                            <svg class="h-3.5 w-3.5" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                                            @break
                                                        @case(3)
                                                            <svg class="h-3.5 w-3.5" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.227 2.28z" /></svg>
                                                            @break
                                                        @case(4)
                                                            <svg class="h-3.5 w-3.5" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                                                            @break
                                                        @default
                                                            <svg class="h-3.5 w-3.5" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                                                    @endswitch
                                                @endif
                                            </div>
                                            <div class="min-w-0 flex-1 pb-4 pt-0.5">
                                                <p
                                                    class="text-xs font-medium leading-tight {{ $current ? 'text-primary-600 dark:text-primary-400' : ($done ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-500') }}"
                                                >
                                                    {{ $label }}
                                                </p>
                                            </div>
                                        </div>
                                    @endforeach
                                </nav>
                            </div>

                            <div class="rounded-xl border border-gray-200 bg-white/80 p-3 shadow-inner dark:border-gray-700 dark:bg-gray-900/60">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-primary-600/80 dark:text-primary-400/80">
                                    {{ __('Inquiry') }} #{{ $inquiry->id }}
                                </p>
                                <p class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $inquiry->customer?->name ?? __('Customer') }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $inquiry->product?->title ?? __('Bespoke / no product') }}
                                </p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full border border-primary-500/30 bg-primary-500/10 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-primary-700 dark:text-primary-300"
                                    >
                                        {{ $inquiry->status }}
                                    </span>
                                    @if (! empty($pipeline['order']['order_number'] ?? null))
                                        <span
                                            class="inline-flex items-center rounded-full border border-gray-300 bg-gray-100 px-2 py-0.5 font-mono text-[10px] text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                        >
                                            {{ $pipeline['order']['order_number'] }}
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-2 break-all text-[10px] text-gray-400 dark:text-gray-500">
                                    <span class="text-gray-500 dark:text-gray-600">{{ __('Thread') }}</span>
                                    <code class="text-[10px] text-gray-500">{{ $threadId }}</code>
                                </p>
                                @if ($inquiry->attachments->isNotEmpty())
                                    <div class="mt-3">
                                        <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-primary-600/80 dark:text-primary-400/80">
                                            {{ __('Initial inquiry photos') }}
                                        </p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($inquiry->attachments as $att)
                                                @php
                                                    $attUrl = \Illuminate\Support\Str::startsWith((string) $att->storage_path, ['http://', 'https://'])
                                                        ? (string) $att->storage_path
                                                        : asset('storage/'.$att->storage_path);
                                                @endphp
                                                <a href="{{ $attUrl }}" target="_blank" rel="noopener noreferrer" class="block">
                                                    <img
                                                        src="{{ $attUrl }}"
                                                        alt="{{ __('Inquiry attachment') }}"
                                                        class="h-16 w-16 rounded-md border border-gray-300 object-cover shadow-sm dark:border-gray-600"
                                                        loading="lazy"
                                                    />
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </aside>

                    {{-- Main: messages + composer --}}
                    <main class="relative flex min-h-[50vh] min-w-0 flex-1 flex-col bg-gradient-to-b from-gray-50 to-white dark:from-gray-950 dark:to-gray-900 lg:min-h-0">
                        @php
                            $__jhAdminStatic = [
                                'quote' => $adminQuoteCardPayload,
                                'payment' => $pipeline['deposit_payment'] ?? null,
                                'payment_proof_history' => $pipeline['payment_proof_history'] ?? [],
                                'receipt' => $pipeline['latest_receipt'] ?? null,
                                'timeline_logs' => $pipeline['timeline_logs'] ?? [],
                                'recordCaption' => __('Record (matches customer app)'),
                            ];
                        @endphp
                        <script type="application/json" id="jh-admin-static-json">@json($__jhAdminStatic)</script>
                        <div
                            class="jh-scroll-thread jh-scroll min-h-0 flex-1 overflow-y-auto scroll-smooth"
                        >
                            <div id="jh-server-cards" class="space-y-1 px-3 pt-4 sm:px-5" wire:ignore></div>
                            <div
                                id="jh-messages"
                                class="min-h-[6rem] px-3 pb-4 sm:px-5"
                                wire:ignore
                            ></div>
                        </div>

                        <div
                            class="shrink-0 border-t border-gray-200 bg-white/95 p-3 shadow-sm backdrop-blur-md dark:border-gray-700 dark:bg-gray-950/90"
                        >
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ __('Quick actions') }}
                                </span>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        onclick="document.getElementById('jh-dialog-quote').showModal()"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-primary-500/40 bg-primary-500/10 px-3 py-1.5 text-xs font-medium text-primary-700 shadow-sm transition hover:bg-primary-500/20 dark:text-primary-300"
                                    >
                                        <svg class="h-4 w-4" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                        {{ __('Generate quote') }}
                                    </button>
                                    @if ($paymentProfiles->isEmpty())
                                        <span class="text-xs text-amber-700 dark:text-amber-400">
                                            {{ __('Add payment profiles under Settings → Payment profiles.') }}
                                        </span>
                                    @else
                                        <button
                                            type="button"
                                            onclick="document.getElementById('jh-dialog-payment').showModal()"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-primary-500/40 bg-white px-3 py-1.5 text-xs font-medium text-primary-700 shadow-sm transition hover:bg-primary-500/10 dark:border-primary-400/40 dark:bg-gray-900 dark:text-primary-300"
                                        >
                                            {{ __('Send payment info') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                            <form id="jh-form" class="flex gap-2" wire:ignore>
                                <input
                                    id="jh-input"
                                    type="text"
                                    maxlength="4000"
                                    class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 shadow-inner placeholder:text-gray-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
                                    placeholder="{{ __('Message the customer…') }}"
                                />
                                <input id="jh-image-input" type="file" accept="image/*" class="hidden" />
                                <button
                                    id="jh-image-btn"
                                    type="button"
                                    class="inline-flex shrink-0 items-center justify-center rounded-lg border border-primary-500/40 bg-white px-3 py-2.5 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-primary-500/10 dark:border-primary-400/40 dark:bg-gray-900 dark:text-primary-300"
                                    title="{{ __('Send image') }}"
                                >
                                    <svg class="h-5 w-5" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.159 2.159m-18 3.091V6.75A2.25 2.25 0 0 1 5.25 4.5h13.5A2.25 2.25 0 0 1 21 6.75v10.5A2.25 2.25 0 0 1 18.75 19.5H5.25A2.25 2.25 0 0 1 3 17.25Z" /></svg>
                                </button>
                                <button
                                    type="submit"
                                    class="inline-flex shrink-0 items-center justify-center rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500"
                                >
                                    {{ __('Send') }}
                                </button>
                            </form>
                        </div>
                    </main>
                </div>
            </div>
        </x-filament::section>

        <dialog
            id="jh-dialog-quote"
            class="jh-quote-dialog max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-gray-200 bg-white p-0 text-gray-900 shadow-xl dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
        >
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-200 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-900">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-400">
                        {{ __('Concierge') }}
                    </p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Generate quote') }}</p>
                </div>
                <button
                    type="button"
                    onclick="document.getElementById('jh-dialog-quote').close()"
                    class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    aria-label="{{ __('Close') }}"
                >
                    <svg class="h-5 w-5" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="p-5">
                @if ($this->quotePublishWarning)
                    <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
                        {{ $this->quotePublishWarning }}
                    </p>
                @endif
                <form wire:submit.prevent="publishQuoteFromConsole" class="space-y-3">
                    @if ($inquiry->product_id)
                        <button
                            type="button"
                            wire:click="prefillFromInquiryProduct"
                            class="w-full rounded-lg border border-primary-500/40 bg-primary-500/10 px-3 py-2 text-xs font-medium text-primary-700 hover:bg-primary-500/20 dark:text-primary-300"
                        >
                            {{ __('Use linked product as starting line') }}
                        </button>
                    @endif
                    @foreach ($quoteFormLines as $index => $line)
                        <div
                            class="space-y-2 rounded-xl border border-gray-200 bg-gray-50/80 p-3 dark:border-gray-700 dark:bg-gray-800/50"
                            wire:key="line-{{ $index }}"
                        >
                            <p class="text-xs font-medium text-primary-600 dark:text-primary-400">{{ __('Line') }} {{ $index + 1 }}</p>
                            <input
                                type="number"
                                step="any"
                                min="0"
                                required
                                wire:model="quoteFormLines.{{ $index }}.weight_grams"
                                placeholder="{{ __('Weight (g)') }}"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            />
                            <div class="grid grid-cols-2 gap-2">
                                <input
                                    type="number"
                                    step="any"
                                    min="0"
                                    wire:model="quoteFormLines.{{ $index }}.labor_fee_mmk"
                                    placeholder="{{ __('Labor MMK') }}"
                                    class="rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                />
                                <input
                                    type="number"
                                    step="any"
                                    min="0"
                                    wire:model="quoteFormLines.{{ $index }}.gem_price_mmk"
                                    placeholder="{{ __('Gems MMK') }}"
                                    class="rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                />
                            </div>
                            <input
                                type="text"
                                wire:model="quoteFormLines.{{ $index }}.custom_title"
                                placeholder="{{ __('Title (optional)') }}"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            />
                            <div class="grid grid-cols-2 gap-2">
                                <input
                                    type="text"
                                    wire:model="quoteFormLines.{{ $index }}.metal_type"
                                    placeholder="{{ __('Metal type (optional)') }}"
                                    class="rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                />
                                <input
                                    type="text"
                                    wire:model="quoteFormLines.{{ $index }}.stone_details"
                                    placeholder="{{ __('Stone details (optional)') }}"
                                    class="rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                />
                            </div>
                            <select
                                wire:model="quoteFormLines.{{ $index }}.product_id"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            >
                                <option value="">{{ __('Linked product (optional)') }}</option>
                                @foreach ($quoteProductOptions as $productOption)
                                    <option value="{{ $productOption['id'] }}">{{ $productOption['label'] }}</option>
                                @endforeach
                            </select>
                            @if (count($quoteFormLines) > 1)
                                <button
                                    type="button"
                                    wire:click="removeQuoteLine({{ $index }})"
                                    class="text-xs text-gray-500 underline hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400"
                                >
                                    {{ __('Remove line') }}
                                </button>
                            @endif
                        </div>
                    @endforeach

                    <button
                        type="button"
                        wire:click="addQuoteLine"
                        class="w-full rounded-xl border border-dashed border-primary-500/40 bg-primary-500/5 px-3 py-2.5 text-sm text-primary-700 hover:bg-primary-500/10 dark:text-primary-300"
                    >
                        {{ __('Add line') }}
                    </button>

                    <input
                        type="datetime-local"
                        wire:model="quoteValidUntil"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                    />

                    <textarea
                        wire:model="quoteNotes"
                        rows="2"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                        placeholder="{{ __('Internal notes (optional)') }}"
                    ></textarea>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <input
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            wire:model="quoteDepositPercentage"
                            placeholder="{{ __('Deposit due (%)') }}"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                        />
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            wire:model="quoteDepositAmountMmk"
                            placeholder="{{ __('Or fixed deposit (MMK)') }}"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                        />
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-lg bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-primary-500"
                    >
                        {{ __('Publish quote to chat') }}
                    </button>
                </form>
            </div>
        </dialog>

        <dialog
            id="jh-dialog-payment"
            class="jh-dialog-payment w-full max-w-md overflow-hidden rounded-xl border border-gray-200 bg-white p-0 text-gray-900 shadow-xl dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
        >
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Send payment instructions') }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Choose which payment method to show on the customer chat card.') }}
                </p>
            </div>
            <div class="space-y-4 p-5">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300" for="jh-payment-profile">
                        {{ __('Payment profile') }}
                    </label>
                    <select
                        id="jh-payment-profile"
                        wire:model.live="selectedPaymentProfileId"
                        class="fi-select-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-gray-950 dark:text-gray-100"
                    >
                        @foreach ($paymentProfiles as $pp)
                            <option value="{{ $pp->id }}">{{ $pp->name }} — {{ $pp->channel->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        onclick="document.getElementById('jh-dialog-payment').close()"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="button"
                        wire:click="sendPaymentInstructionsToChat"
                        wire:loading.attr="disabled"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500"
                    >
                        <span wire:loading.remove wire:target="sendPaymentInstructionsToChat">{{ __('Send to chat') }}</span>
                        <span wire:loading wire:target="sendPaymentInstructionsToChat">{{ __('Sending…') }}</span>
                    </button>
                </div>
            </div>
        </dialog>
    @endif
    </div>
</x-filament-panels::page>

@if ($showChat)
    @push('scripts')
        <style>
            dialog.jh-quote-dialog[open],
            dialog.jh-dialog-payment[open] {
                position: fixed;
                inset: 0;
                margin: auto;
                z-index: 70;
            }

            dialog.jh-quote-dialog::backdrop,
            dialog.jh-dialog-payment::backdrop {
                backdrop-filter: blur(6px);
                background: rgba(2, 6, 23, 0.45);
            }
        </style>
        @php
            $__firebaseWebConfig = [
                'apiKey' => trim((string) config('services.firebase.web_api_key')),
                'authDomain' => trim((string) config('services.firebase.project_id')).'.firebaseapp.com',
                'projectId' => trim((string) config('services.firebase.project_id')),
                'storageBucket' => (string) config('services.firebase.storage_bucket'),
                'messagingSenderId' => (string) config('services.firebase.messaging_sender_id'),
                'appId' => (string) config('services.firebase.web_app_id'),
            ];
        @endphp
        <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js"></script>
        <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-auth-compat.js"></script>
        <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore-compat.js"></script>
        <script>
            (function () {
                const firebaseConfig = @json($__firebaseWebConfig);
                const token = @json($firebaseToken);
                const threadId = @json($threadId);
                const inquiryId = @json($inquiry->id);
                const adminUploadUrl = @json(route('admin.inquiries.chat-upload', ['inquiry' => $inquiry->id]));
                function setChatPollingEnabled(enabled) {
                    var sentinel = document.getElementById('jh-chat-poll-sentinel');
                    if (!sentinel) {
                        return;
                    }
                    sentinel.classList.toggle('hidden', !enabled);
                }

                function syncPollingFromDialogs() {
                    var quoteDialog = document.getElementById('jh-dialog-quote');
                    var paymentDialog = document.getElementById('jh-dialog-payment');
                    var hasOpenDialog = Boolean((quoteDialog && quoteDialog.open) || (paymentDialog && paymentDialog.open));
                    setChatPollingEnabled(!hasOpenDialog);
                }

                function bindDialogPollingState(dialogId) {
                    var dlg = document.getElementById(dialogId);
                    if (!dlg) {
                        return;
                    }
                    dlg.addEventListener('click', syncPollingFromDialogs);
                    dlg.addEventListener('close', function () {
                        syncPollingFromDialogs();
                    });
                    dlg.addEventListener('cancel', function () {
                        syncPollingFromDialogs();
                    });
                    try {
                        var observer = new MutationObserver(syncPollingFromDialogs);
                        observer.observe(dlg, { attributes: true, attributeFilter: ['open'] });
                    } catch (e) {
                        // no-op
                    }
                }

                bindDialogPollingState('jh-dialog-quote');
                bindDialogPollingState('jh-dialog-payment');
                syncPollingFromDialogs();

                function esc(s) {
                    return String(s || '').replace(/</g, '&lt;');
                }

                function messageTypeKey(msg) {
                    var t = msg.type;
                    if (t == null || t === undefined) {
                        return '';
                    }
                    return String(t);
                }

                /** Mirrors Flutter: quote_card or structured quote payload without type. */
                function isQuoteCardPayload(msg) {
                    if (messageTypeKey(msg) === 'quote_card') {
                        return true;
                    }
                    if (msg.quote_id == null) {
                        return false;
                    }
                    return msg.quote_number != null && Array.isArray(msg.lines);
                }

                /** Mirrors Flutter _isPaymentInfoPayload — cards missing explicit type still render. */
                function isPaymentInfoPayload(msg) {
                    var t = messageTypeKey(msg);
                    if (t === 'payment_info_card') {
                        return true;
                    }
                    if (msg.sender_role !== 'admin') {
                        return false;
                    }
                    if (msg.quote_id != null) {
                        return false;
                    }
                    if (msg.payment_profile_id != null) {
                        return true;
                    }
                    return (
                        msg.order_number != null &&
                        msg.amount_mmk != null &&
                        Object.prototype.hasOwnProperty.call(msg, 'kpay_id')
                    );
                }

                function isReceiptCardPayload(msg) {
                    var t = messageTypeKey(msg);
                    if (t === 'receipt_card') {
                        return true;
                    }
                    if (msg.sender_role !== 'admin') {
                        return false;
                    }
                    if (msg.receipt_id != null && msg.receipt_number != null && msg.amount_mmk != null) {
                        return true;
                    }
                    return false;
                }

                function formatMessageTime(msg) {
                    var ts = msg.created_at;
                    if (!ts) {
                        return '';
                    }
                    try {
                        var d = null;
                        if (typeof ts.toDate === 'function') {
                            d = ts.toDate();
                        } else if (ts.seconds != null) {
                            d = new Date(ts.seconds * 1000);
                        }
                        if (!d || isNaN(d.getTime())) {
                            return '';
                        }
                        return d.toLocaleTimeString(undefined, {
                            hour: 'numeric',
                            minute: '2-digit',
                        });
                    } catch (e) {
                        return '';
                    }
                }

                function createdAtMs(msg) {
                    var ts = msg && msg.created_at;
                    if (!ts) {
                        return null;
                    }
                    try {
                        if (typeof ts.toDate === 'function') {
                            var d = ts.toDate();
                            return d && !isNaN(d.getTime()) ? d.getTime() : null;
                        }
                        if (ts.seconds != null) {
                            return Number(ts.seconds) * 1000;
                        }
                        if (typeof ts === 'number') {
                            return ts;
                        }
                    } catch (e) {
                        return null;
                    }
                    return null;
                }

                function roleTimeCaption(msg) {
                    var role = msg.sender_role || '';
                    var who = role === 'admin' ? 'Concierge' : 'Customer';
                    var t = formatMessageTime(msg);
                    return t ? who + ' · ' + t : who;
                }

                function cleanImageCaption(rawCaption) {
                    var caption = String(rawCaption || '').trim();
                    if (!caption) {
                        return '';
                    }
                    var normalized = caption.toLowerCase();
                    if (
                        normalized === 'admin image' ||
                        normalized === 'customer image' ||
                        normalized === 'image message'
                    ) {
                        return '';
                    }
                    return caption;
                }

                function fmtMmk(raw) {
                    var v = parseFloat(String(raw || '').replace(/,/g, ''));
                    if (isNaN(v)) {
                        return esc(String(raw || ''));
                    }
                    return 'K ' + Math.round(v).toLocaleString();
                }

                function paymentCardHtml(data) {
                    var method = esc(data.payment_method_label || '');
                    var qr = data.qr_image_url
                        ? '<div class="mt-3"><img src="' +
                          esc(data.qr_image_url) +
                          '" alt="" class="mx-auto max-h-48 max-w-full rounded-lg border border-primary-500/20 object-contain dark:border-primary-500/30" /></div>'
                        : '';
                    var amt = fmtMmk(data.amount_mmk);
                    var creditRaw = data.credited_deposit_mmk;
                    var creditNum = parseFloat(String(creditRaw || '').replace(/,/g, ''));
                    var balanceBreakdown = '';
                    if (
                        data.order_total_mmk != null &&
                        String(data.order_total_mmk) !== '' &&
                        !isNaN(creditNum) &&
                        creditNum > 0
                    ) {
                        balanceBreakdown =
                            '<div class="mb-3 space-y-1 rounded-lg border border-amber-200 bg-amber-50/90 p-2 text-[11px] text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">' +
                            '<div class="flex justify-between gap-2"><span>Order total</span><span>' +
                            fmtMmk(data.order_total_mmk) +
                            ' MMK</span></div>' +
                            '<div class="flex justify-between gap-2"><span>Recognized paid</span><span>' +
                            fmtMmk(creditRaw) +
                            ' MMK</span></div>' +
                            (data.balance_remaining_mmk != null && String(data.balance_remaining_mmk) !== ''
                                ? '<div class="flex justify-between gap-2 font-medium"><span>Balance on order</span><span>' +
                                  fmtMmk(data.balance_remaining_mmk) +
                                  ' MMK</span></div>'
                                : '') +
                            '</div>';
                    }
                    return (
                        '<div class="max-w-full rounded-2xl border-2 border-primary-500/30 bg-gradient-to-br from-white via-white to-gray-50 p-4 text-left shadow-md dark:border-primary-500/40 dark:from-gray-900 dark:via-gray-900 dark:to-gray-950">' +
                        '<div class="mb-3 flex items-center gap-2 text-primary-600 dark:text-primary-400">' +
                        '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /></svg>' +
                        '<span class="font-semibold tracking-tight">Payment ' +
                        esc(data.order_number) +
                        '</span></div>' +
                        (method
                            ? '<p class="mb-2 text-xs font-medium text-primary-700/90 dark:text-primary-300/90">' +
                              method +
                              '</p>'
                            : '') +
                        balanceBreakdown +
                        '<p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Pay now (deposit due)</p>' +
                        '<p class="mb-3 text-base font-medium text-primary-600 dark:text-primary-400">' +
                        amt +
                        ' MMK</p>' +
                        '<div class="space-y-2 text-xs">' +
                        '<div><span class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Wallet / KPay</span><p class="mt-0.5 text-gray-800 dark:text-gray-200">' +
                        esc(data.kpay_id || '') +
                        '</p></div>' +
                        '<div><span class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Bank</span><p class="mt-0.5 text-gray-800 dark:text-gray-200">' +
                        esc(data.bank_name || '') +
                        '</p></div>' +
                        '<div><span class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Account</span><p class="mt-0.5 text-gray-800 dark:text-gray-200">' +
                        esc(data.bank_account_number || '') +
                        ' · ' +
                        esc(data.bank_account_name || '') +
                        '</p></div>' +
                        '</div>' +
                        qr +
                        '</div>'
                    );
                }

                function quoteCardHtml(data) {
                    var lines = data.lines || [];
                    var rows = '';
                    lines.forEach(function (ln) {
                        var title = esc((ln.title != null ? ln.title : 'Item') || 'Item');
                        var metal = fmtMmk(ln.metal_mmk);
                        var labor = fmtMmk(ln.labor_mmk);
                        var gems = fmtMmk(ln.gems_mmk);
                        var lineTot = fmtMmk(ln.line_total_mmk);
                        rows +=
                            '<div class="mb-3 rounded-xl border border-gray-200/90 bg-gray-50/90 p-3 dark:border-gray-600 dark:bg-gray-800/90">' +
                            '<div class="mb-2 text-sm font-medium text-gray-900 dark:text-gray-100">' +
                            title +
                            '</div>' +
                            '<div class="flex flex-wrap gap-2">' +
                            '<span class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300">Gold ' +
                            metal +
                            '</span>' +
                            '<span class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300">Labor ' +
                            labor +
                            '</span>' +
                            '<span class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300">Gems ' +
                            gems +
                            '</span>' +
                            '</div>' +
                            '<div class="mt-2 text-right text-xs text-primary-600 dark:text-primary-400">Line ' +
                            lineTot +
                            '</div>' +
                            '</div>';
                    });
                    var totalFmt = fmtMmk(data.total_mmk);
                    return (
                        '<div class="max-w-full rounded-2xl border-2 border-primary-500/30 bg-gradient-to-br from-white via-white to-gray-50 p-4 text-left shadow-md dark:border-primary-500/40 dark:from-gray-900 dark:via-gray-900 dark:to-gray-950">' +
                        '<div class="mb-3 flex items-center gap-2 text-primary-600 dark:text-primary-400">' +
                        '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>' +
                        '<span class="font-semibold tracking-tight">Quote ' +
                        esc(data.quote_number) +
                        '</span></div>' +
                        '<p class="mb-2 text-[10px] font-semibold uppercase tracking-widest text-primary-600/80 dark:text-primary-400/80">Estimate (MMK)</p>' +
                        rows +
                        '<div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-3 text-sm dark:border-gray-700">' +
                        '<span class="text-gray-500 dark:text-gray-400">Total</span>' +
                        '<span class="text-lg font-semibold text-primary-600 dark:text-primary-400">' +
                        totalFmt +
                        '</span></div>' +
                        '<p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">Customer accepts or declines in the mobile app.</p>' +
                        '</div>'
                    );
                }

                function receiptCardHtml(data) {
                    var amountFmt = fmtMmk(data.amount_mmk || '0');
                    return (
                        '<div class="max-w-full rounded-2xl border-2 border-emerald-500/30 bg-gradient-to-br from-white via-white to-emerald-50 p-4 text-left shadow-md dark:border-emerald-400/40 dark:from-gray-900 dark:via-gray-900 dark:to-gray-950">' +
                        '<div class="mb-3 flex items-center gap-2 text-emerald-700 dark:text-emerald-300">' +
                        '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9z" /></svg>' +
                        '<span class="font-semibold tracking-tight">Payment Receipt</span>' +
                        '</div>' +
                        '<p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700/80 dark:text-emerald-300/80">Receipt number</p>' +
                        '<p class="mb-3 mt-1 font-mono text-sm text-gray-900 dark:text-gray-100">' +
                        esc(data.receipt_number || 'Receipt') +
                        '</p>' +
                        '<p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Confirmed amount</p>' +
                        '<p class="mt-1 text-base font-semibold text-emerald-700 dark:text-emerald-300">' +
                        amountFmt +
                        ' MMK</p>' +
                        '<p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">Receipt issued and available for customer download.</p>' +
                        '</div>'
                    );
                }

                function row(msg) {
                    if (messageTypeKey(msg) === 'timeline_log') {
                        var timelineText = String(msg.text || '').trim();
                        if (!timelineText) {
                            timelineText = '--- Workflow Update ---';
                        }
                        var wrapT = document.createElement('div');
                        wrapT.className = 'mb-3 flex w-full flex-col items-center';
                        var textT = document.createElement('p');
                        textT.className = 'text-center text-xs text-gray-500 dark:text-gray-400';
                        textT.textContent = timelineText;
                        var capT = document.createElement('p');
                        capT.className = 'mt-0.5 text-center text-[11px] text-gray-500 dark:text-gray-400';
                        var timeT = formatMessageTime(msg);
                        capT.textContent = timeT ? 'Workflow update · ' + timeT : 'Workflow update';
                        wrapT.appendChild(textT);
                        wrapT.appendChild(capT);
                        return wrapT;
                    }
                    if (isQuoteCardPayload(msg)) {
                        var wrapQ = document.createElement('div');
                        wrapQ.className = 'mb-4 flex w-full flex-col items-center px-1';
                        var innerQ = document.createElement('div');
                        innerQ.className = 'w-full max-w-lg';
                        innerQ.innerHTML = quoteCardHtml(msg);
                        wrapQ.appendChild(innerQ);
                        var capQ = document.createElement('p');
                        capQ.className = 'mt-2 text-center text-[11px] text-gray-500 dark:text-gray-400';
                        capQ.textContent = roleTimeCaption(msg);
                        wrapQ.appendChild(capQ);
                        return wrapQ;
                    }
                    if (isPaymentInfoPayload(msg)) {
                        var wrapP = document.createElement('div');
                        wrapP.className = 'mb-4 flex w-full flex-col items-center px-1';
                        var innerP = document.createElement('div');
                        innerP.className = 'w-full max-w-lg';
                        innerP.innerHTML = paymentCardHtml(msg);
                        wrapP.appendChild(innerP);
                        var capP = document.createElement('p');
                        capP.className = 'mt-2 text-center text-[11px] text-gray-500 dark:text-gray-400';
                        capP.textContent = roleTimeCaption(msg);
                        wrapP.appendChild(capP);
                        return wrapP;
                    }
                    if (isReceiptCardPayload(msg)) {
                        var wrapR = document.createElement('div');
                        wrapR.className = 'mb-4 flex w-full flex-col items-center px-1';
                        var innerR = document.createElement('div');
                        innerR.className = 'w-full max-w-lg';
                        innerR.innerHTML = receiptCardHtml(msg);
                        wrapR.appendChild(innerR);
                        var capR = document.createElement('p');
                        capR.className = 'mt-2 text-center text-[11px] text-gray-500 dark:text-gray-400';
                        capR.textContent = roleTimeCaption(msg);
                        wrapR.appendChild(capR);
                        return wrapR;
                    }
                    if (messageTypeKey(msg) === 'image') {
                        var roleI = msg.sender_role || '';
                        var isAdminImage = roleI === 'admin';
                        var isPaymentProof = String(msg.image_category || '') === 'payment_proof';
                        var wrapI = document.createElement('div');
                        wrapI.className =
                            'mb-4 flex w-full flex-col ' + (isAdminImage ? 'items-end' : 'items-start');
                        var innerI = document.createElement('div');
                        innerI.className =
                            (isAdminImage
                                ? 'max-w-[min(100%,24rem)] rounded-2xl border-2 border-primary-500/50 bg-white p-2 shadow-sm dark:border-primary-400/50 dark:bg-gray-800'
                                : 'max-w-[min(100%,24rem)] rounded-2xl border border-gray-200 bg-gray-50 p-2 shadow-sm dark:border-gray-600 dark:bg-gray-800');
                        var img = document.createElement('img');
                        img.src = msg.image_url || '';
                        img.className = 'max-h-72 w-full rounded-lg object-cover';
                        img.alt = '';
                        if (isPaymentProof) {
                            var headerI = document.createElement('p');
                            headerI.className =
                                'mb-2 px-1 text-[11px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300';
                            headerI.textContent = 'Payment Proof Uploaded';
                            innerI.appendChild(headerI);
                        }
                        var capI = document.createElement('p');
                        capI.className = 'mt-2 px-1 text-xs text-gray-600 dark:text-gray-300';
                        capI.textContent = cleanImageCaption(msg.caption);
                        innerI.appendChild(img);
                        if (capI.textContent) {
                            innerI.appendChild(capI);
                        }
                        wrapI.appendChild(innerI);
                        var capTime = document.createElement('p');
                        capTime.className =
                            'mt-1.5 text-[11px] text-gray-500 dark:text-gray-400 ' +
                            (isAdminImage ? 'pr-1 text-right' : 'pl-1 text-left');
                        capTime.textContent = roleTimeCaption(msg);
                        wrapI.appendChild(capTime);
                        return wrapI;
                    }
                    var role = msg.sender_role || '';
                    var text = msg.text || '';
                    var wrap = document.createElement('div');
                    wrap.className =
                        'mb-3 flex w-full flex-col ' + (role === 'admin' ? 'items-end' : 'items-start');
                    var bubble = document.createElement('div');
                    bubble.className =
                        role === 'admin'
                            ? 'max-w-[min(100%,28rem)] rounded-2xl border-2 border-primary-500/50 bg-white px-4 py-2.5 text-left text-sm text-gray-900 shadow-sm dark:border-primary-400/50 dark:bg-gray-800 dark:text-gray-100'
                            : 'max-w-[min(100%,28rem)] rounded-2xl border border-gray-200 bg-gray-100 px-4 py-2.5 text-left text-sm text-gray-900 shadow-sm dark:border-gray-600 dark:bg-gray-800/80 dark:text-gray-100';
                    var label = document.createElement('div');
                    label.className =
                        'mb-1 text-[10px] font-semibold uppercase tracking-wider ' +
                        (role === 'admin' ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-gray-400');
                    label.textContent = role === 'admin' ? 'Concierge' : 'Customer';
                    var body = document.createElement('div');
                    body.className = 'whitespace-pre-wrap break-words text-[15px] leading-relaxed';
                    body.textContent = text;
                    var foot = document.createElement('div');
                    foot.className = 'mt-2 text-[11px] text-gray-500 dark:text-gray-400';
                    foot.textContent = roleTimeCaption(msg);
                    bubble.appendChild(label);
                    bubble.appendChild(body);
                    bubble.appendChild(foot);
                    wrap.appendChild(bubble);
                    return wrap;
                }

                function appendMysqlCardBlock(container, htmlString, captionText) {
                    var wrap = document.createElement('div');
                    wrap.className = 'mb-4 flex w-full flex-col items-center px-1';
                    var inner = document.createElement('div');
                    inner.className = 'w-full max-w-lg';
                    inner.innerHTML = htmlString;
                    wrap.appendChild(inner);
                    var cap = document.createElement('p');
                    cap.className =
                        'mt-2 text-center text-[11px] font-medium text-amber-800 dark:text-amber-300';
                    cap.textContent = captionText;
                    wrap.appendChild(cap);
                    container.appendChild(wrap);
                }

                function appendMysqlTimelineBlock(container, text, occurredAt) {
                    var wrap = document.createElement('div');
                    wrap.className = 'mb-3 flex w-full flex-col items-center';
                    var textEl = document.createElement('p');
                    textEl.className = 'text-center text-xs text-gray-500 dark:text-gray-400';
                    textEl.textContent = String(text || '').trim();
                    var cap = document.createElement('p');
                    cap.className =
                        'mt-0.5 text-center text-[11px] text-gray-500 dark:text-gray-400';
                    var timeLabel = '';
                    if (occurredAt) {
                        var d = new Date(occurredAt);
                        if (!isNaN(d.getTime())) {
                            timeLabel = d.toLocaleTimeString(undefined, {
                                hour: 'numeric',
                                minute: '2-digit',
                            });
                        }
                    }
                    cap.textContent = timeLabel ? 'Workflow update · ' + timeLabel : 'Workflow update';
                    wrap.appendChild(textEl);
                    wrap.appendChild(cap);
                    container.appendChild(wrap);
                }

                function renderMysqlCards(serverCardsEl) {
                    var hq = null;
                    var hp = null;
                    var hr = null;
                    if (!serverCardsEl) {
                        return { hideQuoteId: hq, hidePaymentOrder: hp, hideReceiptId: hr, didRender: false };
                    }
                    var rawEl = document.getElementById('jh-admin-static-json');
                    if (!rawEl || !String(rawEl.textContent || '').trim()) {
                        return { hideQuoteId: hq, hidePaymentOrder: hp, hideReceiptId: hr, didRender: false };
                    }
                    var data;
                    try {
                        data = JSON.parse(rawEl.textContent);
                    } catch (e) {
                        return { hideQuoteId: hq, hidePaymentOrder: hp, hideReceiptId: hr, didRender: false };
                    }
                    if (!data) {
                        return { hideQuoteId: hq, hidePaymentOrder: hp, hideReceiptId: hr, didRender: false };
                    }

                    var nextCardsHtml = '';
                    var capBase = data.recordCaption || 'Record';
                    if (data.quote) {
                        var quoteWrap = document.createElement('div');
                        appendMysqlCardBlock(
                            quoteWrap,
                            quoteCardHtml(data.quote),
                            'Concierge · ' + capBase
                        );
                        nextCardsHtml += quoteWrap.innerHTML;
                        if (data.quote.quote_id != null) {
                            hq = Number(data.quote.quote_id);
                        }
                    }
                    if (data.payment) {
                        var paymentWrap = document.createElement('div');
                        appendMysqlCardBlock(
                            paymentWrap,
                            paymentCardHtml(data.payment),
                            'Concierge · ' + capBase
                        );
                        nextCardsHtml += paymentWrap.innerHTML;
                        if (data.payment.order_number) {
                            hp = String(data.payment.order_number);
                        }
                    }
                    if (data.receipt) {
                        var receiptWrap = document.createElement('div');
                        appendMysqlCardBlock(
                            receiptWrap,
                            receiptCardHtml(data.receipt),
                            'Concierge · ' + capBase
                        );
                        nextCardsHtml += receiptWrap.innerHTML;
                        if (data.receipt.receipt_id != null) {
                            hr = Number(data.receipt.receipt_id);
                        } else if (data.receipt.id != null) {
                            hr = Number(data.receipt.id);
                        }
                    }
                    mysqlTimelineLogs = [];
                    mysqlPaymentProofMessages = [];
                    var proofHistory = [];
                    if (Array.isArray(data.payment_proof_history)) {
                        proofHistory = data.payment_proof_history;
                    } else if (data.payment && Array.isArray(data.payment.payment_proof_history)) {
                        proofHistory = data.payment.payment_proof_history;
                    } else if (
                        data.payment &&
                        data.payment.payment_proof_image_url &&
                        String(data.payment.payment_proof_image_url).trim() !== ''
                    ) {
                        proofHistory = [
                            {
                                image_url: data.payment.payment_proof_image_url,
                                submitted_at: data.payment.payment_proof_submitted_at || null,
                            },
                        ];
                    }
                    for (var ph = 0; ph < proofHistory.length; ph++) {
                        var proof = proofHistory[ph] || {};
                        var proofUrl = String(proof.image_url || '').trim();
                        if (!proofUrl) {
                            continue;
                        }
                        var proofMs = Date.parse(proof.submitted_at || '');
                        mysqlPaymentProofMessages.push({
                            type: 'image',
                            sender_role: 'customer',
                            image_url: proofUrl,
                            image_category: 'payment_proof',
                            caption: 'Payment proof uploaded',
                            text: 'Payment proof uploaded',
                            created_at:
                                !isNaN(proofMs) ? { seconds: Math.floor(proofMs / 1000) } : null,
                        });
                    }
                    if (Array.isArray(data.timeline_logs)) {
                        data.timeline_logs.forEach(function (t) {
                            var msg = t && t.message != null ? String(t.message) : '';
                            if (!msg.trim()) {
                                return;
                            }
                            var ms = Date.parse(t.occurred_at || '');
                            mysqlTimelineLogs.push({
                                text: msg.trim(),
                                ms: isNaN(ms) ? null : ms,
                            });
                        });
                    }

                    serverCardsEl.innerHTML = nextCardsHtml;

                    return { hideQuoteId: hq, hidePaymentOrder: hp, hideReceiptId: hr, didRender: true };
                }

                var hideQuoteId = null;
                var hidePaymentOrder = null;
                var hideReceiptId = null;
                var mysqlTimelineLogs = [];
                var mysqlPaymentProofMessages = [];
                var localWorkflowLogs = [];
                var lastThreadDepositState = null;
                var lastMessagesSnapshot = null;

                function appendLocalWorkflowLog(text) {
                    var clean = String(text || '').trim();
                    if (!clean) {
                        return;
                    }
                    var now = Date.now();
                    for (var i = 0; i < localWorkflowLogs.length; i++) {
                        var e = localWorkflowLogs[i];
                        if (e.text === clean && Math.abs(now - e.ms) <= 12000) {
                            return;
                        }
                    }
                    localWorkflowLogs.push({ text: clean, ms: now });
                }

                function renderThreadMessages(snap) {
                    box.innerHTML = '';
                    var msgs = [];
                    snap.forEach(function (d) {
                        var msg = d.data();
                        if (shouldSkipFirestoreMsg(msg)) {
                            return;
                        }
                        msgs.push(msg);
                    });
                    msgs.sort(function (a, b) {
                        var am = createdAtMs(a);
                        var bm = createdAtMs(b);
                        if (am != null && bm != null) {
                            return am - bm;
                        }
                        if (am != null) {
                            return -1;
                        }
                        if (bm != null) {
                            return 1;
                        }
                        return 0;
                    });
                    for (var i = 0; i < msgs.length; i++) {
                        // rendered after timeline merges below
                    }

                    var fsTimeline = [];
                    for (var j = 0; j < msgs.length; j++) {
                        if (messageTypeKey(msgs[j]) !== 'timeline_log') {
                            continue;
                        }
                        var t = String(msgs[j].text || '').trim();
                        if (t) {
                            fsTimeline.push({
                                text: t,
                                ms: createdAtMs(msgs[j]),
                            });
                        }
                    }
                    var merged = msgs.slice();
                    var fsPaymentProofUrls = new Set();
                    for (var fp = 0; fp < merged.length; fp++) {
                        var msgFp = merged[fp];
                        if (
                            messageTypeKey(msgFp) === 'image' &&
                            String(msgFp.image_category || '') === 'payment_proof'
                        ) {
                            var u = String(msgFp.image_url || '').trim();
                            if (u) {
                                fsPaymentProofUrls.add(u);
                            }
                        }
                    }
                    for (var pm = 0; pm < mysqlPaymentProofMessages.length; pm++) {
                        var candidate = mysqlPaymentProofMessages[pm];
                        var candidateUrl = String(candidate.image_url || '').trim();
                        if (candidateUrl && fsPaymentProofUrls.has(candidateUrl)) {
                            continue;
                        }
                        merged.push(candidate);
                    }
                    for (var m = 0; m < mysqlTimelineLogs.length; m++) {
                        var remoteLog = mysqlTimelineLogs[m];
                        var hasNearbyFs = false;
                        for (var n = 0; n < fsTimeline.length; n++) {
                            if (fsTimeline[n].text !== remoteLog.text) {
                                continue;
                            }
                            if (fsTimeline[n].ms == null || remoteLog.ms == null) {
                                continue;
                            }
                            if (Math.abs(fsTimeline[n].ms - remoteLog.ms) <= 45000) {
                                hasNearbyFs = true;
                                break;
                            }
                        }
                        if (hasNearbyFs) {
                            continue;
                        }
                        merged.push({
                            type: 'timeline_log',
                            sender_role: 'system',
                            text: remoteLog.text,
                            created_at:
                                remoteLog.ms != null
                                    ? { seconds: Math.floor(remoteLog.ms / 1000) }
                                    : null,
                        });
                    }
                    for (var k = 0; k < localWorkflowLogs.length; k++) {
                        var l = localWorkflowLogs[k];
                        var hasFs = false;
                        for (var x = 0; x < fsTimeline.length; x++) {
                            if (fsTimeline[x].text !== l.text) {
                                continue;
                            }
                            if (fsTimeline[x].ms == null) {
                                continue;
                            }
                            if (Math.abs(fsTimeline[x].ms - l.ms) <= 12000) {
                                hasFs = true;
                                break;
                            }
                        }
                        if (hasFs) {
                            continue;
                        }
                        merged.push({
                            type: 'timeline_log',
                            sender_role: 'system',
                            text: l.text,
                            created_at: { seconds: Math.floor(l.ms / 1000) },
                        });
                    }
                    merged.sort(function (a, b) {
                        var am = createdAtMs(a);
                        var bm = createdAtMs(b);
                        if (am != null && bm != null) {
                            return am - bm;
                        }
                        if (am != null) {
                            return -1;
                        }
                        if (bm != null) {
                            return 1;
                        }
                        return 0;
                    });
                    for (var y = 0; y < merged.length; y++) {
                        box.appendChild(row(merged[y]));
                    }

                    var root = scrollRoot || box;
                    root.scrollTop = root.scrollHeight;
                }

                function shouldSkipFirestoreMsg(msg) {
                    if (
                        hideQuoteId != null &&
                        isQuoteCardPayload(msg) &&
                        msg.quote_id != null &&
                        Number(msg.quote_id) === hideQuoteId
                    ) {
                        return true;
                    }
                    if (
                        hidePaymentOrder &&
                        isPaymentInfoPayload(msg) &&
                        String(msg.order_number || '') === hidePaymentOrder
                    ) {
                        return true;
                    }
                    if (
                        hideReceiptId != null &&
                        isReceiptCardPayload(msg) &&
                        msg.receipt_id != null &&
                        Number(msg.receipt_id) === hideReceiptId
                    ) {
                        return true;
                    }
                    return false;
                }

                const scrollRoot = document.querySelector('.jh-scroll-thread');
                const serverCardsEl = document.getElementById('jh-server-cards');
                const box = document.getElementById('jh-messages');
                const form = document.getElementById('jh-form');
                const input = document.getElementById('jh-input');
                const imageInput = document.getElementById('jh-image-input');
                const imageBtn = document.getElementById('jh-image-btn');
                if (!box || !form || !input || !imageInput || !imageBtn) {
                    return;
                }

                var _jhDedupe = renderMysqlCards(serverCardsEl);
                if (_jhDedupe.didRender) {
                    hideQuoteId = _jhDedupe.hideQuoteId;
                    hidePaymentOrder = _jhDedupe.hidePaymentOrder;
                    hideReceiptId = _jhDedupe.hideReceiptId;
                }

                window.jhAdminChatRefreshStatic = function () {
                    var el = document.getElementById('jh-server-cards');
                    if (!el) {
                        return;
                    }
                    var d = renderMysqlCards(el);
                    if (d.didRender) {
                        hideQuoteId = d.hideQuoteId;
                        hidePaymentOrder = d.hidePaymentOrder;
                        hideReceiptId = d.hideReceiptId;
                    }
                    if (lastMessagesSnapshot) {
                        renderThreadMessages(lastMessagesSnapshot);
                    }
                };

                document.addEventListener('jh-admin-static-refresh', function () {
                    window.jhAdminChatRefreshStatic?.();
                });

                // Keep MySQL-backed cards resilient across Livewire morph timing.
                // When the static JSON/script node is re-rendered, refresh cards automatically.
                var refreshTimer = null;
                function scheduleStaticRefresh() {
                    if (refreshTimer !== null) {
                        clearTimeout(refreshTimer);
                    }
                    refreshTimer = setTimeout(function () {
                        window.jhAdminChatRefreshStatic?.();
                    }, 0);
                }

                if (window.__jhAdminCardsObserver) {
                    try {
                        window.__jhAdminCardsObserver.disconnect();
                    } catch (e) {
                        // no-op
                    }
                }
                window.__jhAdminCardsObserver = new MutationObserver(function (mutations) {
                    for (var i = 0; i < mutations.length; i++) {
                        var m = mutations[i];
                        if (m.type === 'characterData') {
                            var p = m.target && m.target.parentElement;
                            if (p && p.id === 'jh-admin-static-json') {
                                scheduleStaticRefresh();
                                return;
                            }
                        }
                        if (m.type === 'childList') {
                            var nodes = Array.prototype.slice.call(m.addedNodes || []);
                            for (var j = 0; j < nodes.length; j++) {
                                var n = nodes[j];
                                if (!n || n.nodeType !== 1) {
                                    continue;
                                }
                                if (
                                    n.id === 'jh-admin-static-json' ||
                                    n.id === 'jh-server-cards' ||
                                    (typeof n.querySelector === 'function' &&
                                        (n.querySelector('#jh-admin-static-json') ||
                                            n.querySelector('#jh-server-cards')))
                                ) {
                                    scheduleStaticRefresh();
                                    return;
                                }
                            }
                        }
                    }
                });
                window.__jhAdminCardsObserver.observe(document.body, {
                    childList: true,
                    subtree: true,
                    characterData: true,
                });

                if (!firebaseConfig.apiKey || !firebaseConfig.projectId) {
                    box.textContent =
                        'Firebase web config is incomplete. Set FIREBASE_WEB_API_KEY and FIREBASE_PROJECT_ID in backend .env, then run: php artisan config:clear';
                    return;
                }
                if (!firebase.apps.length) {
                    firebase.initializeApp(firebaseConfig);
                }

                firebase
                    .auth()
                    .signInWithCustomToken(token)
                    .then(function () {
                        const threadRef = firebase
                            .firestore()
                            .collection('inquiry_threads')
                            .doc(threadId);
                        threadRef.onSnapshot(function (snap) {
                            var d = (snap && typeof snap.data === 'function') ? (snap.data() || {}) : {};
                            var ds = String(d.deposit_state || '');
                            var didAppendLocalWorkflowLog = false;
                            if (lastThreadDepositState !== null && ds !== lastThreadDepositState) {
                                if (ds === 'rejected') {
                                    appendLocalWorkflowLog('--- Payment Rejected ---');
                                    didAppendLocalWorkflowLog = true;
                                } else if (ds === 'confirmed') {
                                    appendLocalWorkflowLog('--- Deposit Confirmed ---');
                                    didAppendLocalWorkflowLog = true;
                                }
                            }
                            lastThreadDepositState = ds;
                            if (didAppendLocalWorkflowLog && lastMessagesSnapshot) {
                                renderThreadMessages(lastMessagesSnapshot);
                            }
                        });

                        const ref = threadRef.collection('messages');
                        ref.onSnapshot(function (snap) {
                            lastMessagesSnapshot = snap;
                            renderThreadMessages(snap);
                        });
                    })
                    .catch(function (e) {
                        box.textContent = 'Firebase error: ' + e;
                    });

                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const text = (input.value || '').trim();
                    if (!text) return;
                    firebase
                        .firestore()
                        .collection('inquiry_threads')
                        .doc(threadId)
                        .collection('messages')
                        .add({
                            text: text,
                            sender_role: 'admin',
                            created_at: firebase.firestore.FieldValue.serverTimestamp(),
                        })
                        .then(function () {
                            input.value = '';
                        })
                        .catch(function (err) {
                            alert('Send failed: ' + err);
                        });
                });

                function setImageSending(busy) {
                    imageBtn.disabled = busy;
                    imageBtn.classList.toggle('opacity-60', busy);
                    imageBtn.classList.toggle('cursor-not-allowed', busy);
                }

                async function uploadImageToPublic(file) {
                    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    var fd = new FormData();
                    fd.append('file', file);
                    fd.append('folder', 'inquiry-chat-' + inquiryId);
                    var res = await fetch(adminUploadUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: fd,
                    });
                    var payload = await res.json();
                    if (!res.ok) {
                        throw new Error(payload?.message || ('Upload failed: ' + res.status));
                    }
                    var url = String(payload.url || '');
                    if (!url) {
                        throw new Error('Upload succeeded but URL is missing.');
                    }
                    if (url.indexOf('http://') === 0 || url.indexOf('https://') === 0) {
                        return url;
                    }
                    return new URL(url, window.location.origin).toString();
                }

                imageBtn.addEventListener('click', function () {
                    imageInput.click();
                });

                imageInput.addEventListener('change', async function () {
                    var file = imageInput.files && imageInput.files[0];
                    imageInput.value = '';
                    if (!file) {
                        return;
                    }
                    try {
                        setImageSending(true);
                        var imageUrl = await uploadImageToPublic(file);
                        await firebase
                            .firestore()
                            .collection('inquiry_threads')
                            .doc(threadId)
                            .collection('messages')
                            .add({
                                type: 'image',
                                image_url: imageUrl,
                                text: 'Image message',
                                sender_role: 'admin',
                                created_at: firebase.firestore.FieldValue.serverTimestamp(),
                            });
                    } catch (err) {
                        alert('Image send failed: ' + err);
                    } finally {
                        setImageSending(false);
                    }
                });
            })();
        </script>
    @endpush
@endif
