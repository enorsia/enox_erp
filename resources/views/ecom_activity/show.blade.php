@extends('layouts.app')

@section('title', 'Session Activity')

@section('content')
    <div class="max-w-6xl mx-auto px-5 py-6 pb-28">

        <div class="flex items-start justify-between mb-6 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-accent-400/10 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-accent-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Visitor Session</h1>
                    <p class="text-[12px] font-mono text-slate-400 mt-0.5">{{ $activityUser->session_id }}</p>
                    <p class="text-[11px] text-slate-400 mt-1">All times {{ \App\Support\TrackerTime::timezoneLabel() }}</p>
                </div>
            </div>
            <a href="{{ $backUrl }}"
               class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors no-underline">
                ← Back
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5">

            <div class="space-y-4 min-w-0">
                <div class="section-card"
                     x-data="{
                        replicaOpen: false,
                        replicaDuplicateOpen: false,
                        replicaDuplicateConfirmed: false,
                        sessionHasPaymentSuccess: @js(in_array('payment_success', $reachedSteps, true)),
                        existingOrderIds: @js(collect($sessionPaymentOrders ?? [])->pluck('order_id')->filter()->values()->all()),
                        replicaGrand: '',
                        replicaPaymentMethod: 'WORLD PAY',
                        replicaFormAction: '',
                        syncReplicaPaidAtPicker(paidAt, paidAtMin) {
                            this.$nextTick(() => {
                                const el = this.$refs.replicaPaidAtInput;

                                if (! el) {
                                    return;
                                }

                                el.value = paidAt;

                                if (! el._etdFlatpickr && window.initEtdFlatpickr) {
                                    window.initEtdFlatpickr(el);
                                }

                                const fp = el._etdFlatpickr;

                                if (! fp) {
                                    return;
                                }

                                fp.setDate(paidAt, false);

                                if (paidAtMin) {
                                    fp.set('minDate', paidAtMin);
                                }
                            });
                        },
                        openReplica(actionId, grandTotal, paidAt, paidAtMin) {
                            this.replicaGrand = grandTotal;
                            this.replicaPaymentMethod = 'WORLD PAY';
                            this.replicaFormAction = @js(route('admin.ecom-activity.proceed-payment-success', ['session' => $activityUser->session_id, 'action' => 0])).replace('/proceed/0/', '/proceed/' + actionId + '/');
                            this.replicaDuplicateConfirmed = false;
                            this.replicaDuplicateOpen = false;
                            this.replicaOpen = true;
                            this.syncReplicaPaidAtPicker(paidAt, paidAtMin);
                        },
                        submitReplica() {
                            if (this.sessionHasPaymentSuccess && !this.replicaDuplicateConfirmed) {
                                this.replicaDuplicateOpen = true;
                                return;
                            }
                            this.$refs.replicaForm.submit();
                        },
                        confirmDuplicateReplica() {
                            this.replicaDuplicateConfirmed = true;
                            this.replicaDuplicateOpen = false;
                            this.$refs.replicaForm.submit();
                        },
                     }">
                    <div class="section-title flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Action Timeline
                        </div>
                        @if ($timeline->total() > 0)
                            <span class="text-[11px] font-normal text-slate-400">
                                {{ $timeline->total() }} {{ Str::plural('event', $timeline->total()) }}
                            </span>
                        @endif
                    </div>

                    @if (session('payment_success_replica'))
                        <p class="text-[12px] text-slate-600 dark:text-slate-300 mb-3">{{ session('payment_success_replica') }}</p>
                    @endif

                    @if ($timeline->total() > 0)
                        <p class="text-[11px] text-slate-400 mb-3">
                            Newest first. Showing {{ $timeline->firstItem() }}–{{ $timeline->lastItem() }} of {{ $timeline->total() }}
                        </p>
                    @endif

                    @forelse ($timeline as $item)
                        @php
                            $badgeClass = $badgeColors[$item->action_type] ?? 'badge-amber';
                            $jsonPayload = match ($item->action_type) {
                                'add_to_cart', 'edit_bag' => $item->add_to_cart,
                                'begin_checkout' => $item->begin_checkout,
                                'proceed_checkout' => $item->proceed_to_checkout,
                                'payment_success' => $item->payment_success,
                                default => null,
                            };
                        @endphp
                        <div class="border border-slate-200 dark:border-slate-700 rounded-xl p-4 mb-3 last:mb-0 min-w-0 overflow-hidden">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="badge-custom {{ $badgeClass }}">{{ str_replace('_', ' ', $item->action_type) }}</span>
                                <span class="text-[12px] text-slate-400">
                                    {{ \App\Support\TrackerTime::formatFromStorage($item->created_at, 'd M Y, h:i:s A') }}
                                </span>
                                @if ($item->dwell_seconds !== null)
                                    <span class="text-[11px] text-slate-500">
                                        Dwell: {{ $item->dwell_seconds }}s
                                        @if ($item->is_grouped_product_view || ($item->is_grouped_grid_impression ?? false))
                                            <span class="text-slate-400">(combined)</span>
                                        @endif
                                    </span>
                                @endif
                                @if ($item->action_type === 'proceed_checkout' && filled($item->id))
                                    @php
                                        $proceedReplica = is_array($item->proceed_to_checkout) ? $item->proceed_to_checkout : [];
                                        $replicaGrandDefault = (string) (($proceedReplica['totals']['grand_total'] ?? null) ?: ($proceedReplica['cart_total'] ?? ''));
                                        $proceedLocal = \App\Support\TrackerTime::toLocal($item->created_at);
                                        $replicaProceedMinAt = '';
                                        $replicaPaidAtDefault = '';
                                        if ($proceedLocal) {
                                            $earliestPaid = $proceedLocal->copy()->addMinute();
                                            $replicaProceedMinAt = $earliestPaid->format('Y-m-d H:i');
                                            $nowLocal = \App\Support\TrackerTime::localNow();
                                            $replicaPaidAtDefault = ($nowLocal->greaterThan($earliestPaid) ? $nowLocal : $earliestPaid)->format('Y-m-d H:i');
                                        }
                                    @endphp
                                    <button type="button"
                                            class="ml-auto inline-flex items-center px-2.5 h-7 text-[11px] font-medium rounded-md bg-emerald-600 text-white hover:bg-emerald-700"
                                            @click="openReplica(@js($item->id), @js($replicaGrandDefault), @js($replicaPaidAtDefault), @js($replicaProceedMinAt))">
                                        Go to success
                                    </button>
                                @endif
                            </div>

                            @if ($item->referer || $item->page_url)
                                <div class="text-[12px] text-slate-500 dark:text-slate-400 mb-2 space-y-1.5 min-w-0">
                                    @if ($item->referer)
                                        <div class="min-w-0">
                                            <span class="text-slate-400">From:</span>
                                            <a href="{{ $item->referer }}" target="_blank" rel="noopener" class="text-accent-500 hover:underline break-all">{{ $item->referer }}</a>
                                        </div>
                                    @endif
                                    @if ($item->page_url)
                                        <div class="min-w-0">
                                            <span class="text-slate-400">To:</span>
                                            <a href="{{ $item->page_url }}" target="_blank" rel="noopener" class="text-accent-500 hover:underline break-all">{{ $item->page_url }}</a>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[12px]">
                                @if ($item->category_name)
                                    <div>
                                        <span class="text-slate-400">Category:</span>
                                        {{ $item->category_name }}@if ($item->category_code) ({{ $item->category_code }})@endif
                                    </div>
                                @endif
                                @if ($item->product_name)
                                    <div><span class="text-slate-400">Product:</span> {{ $item->product_name }}</div>
                                @endif
                                @if ($item->product_code)
                                    <div><span class="text-slate-400">Product code:</span> {{ $item->product_code }}</div>
                                @endif
                                @if ($item->sku)
                                    <div><span class="text-slate-400">SKU:</span> {{ $item->sku }}</div>
                                @endif
                                @if (in_array($item->action_type, ['product_view', 'product_view_popup'], true) && $item->color_timeline)
                                    <div class="sm:col-span-2">
                                        <span class="text-slate-400">Colors:</span>
                                        <span class="text-slate-700 dark:text-slate-200">{{ $item->color_timeline }}</span>
                                    </div>
                                @endif
                                @if ($item->action_type === 'grid_impression' && ($item->sku_timeline ?? null))
                                    <div class="sm:col-span-2">
                                        <span class="text-slate-400">Grid view:</span>
                                        <span class="text-slate-700 dark:text-slate-200">{{ $item->sku_timeline }}</span>
                                    </div>
                                @endif
                                @if ($item->action_type === 'grid_click' && ($item->sku_timeline ?? null))
                                    <div class="sm:col-span-2">
                                        <span class="text-slate-400">Grid click:</span>
                                        <span class="text-slate-700 dark:text-slate-200">{{ $item->sku_timeline }}</span>
                                    </div>
                                @endif
                                @if ($item->product_price)
                                    <div><span class="text-slate-400">Price:</span> £{{ number_format($item->product_price, 2) }}</div>
                                @endif
                                @if (in_array($item->action_type, ['add_to_cart', 'edit_bag'], true) && is_array($item->add_to_cart))
                                    @if (! empty($item->add_to_cart['qty']))
                                        <div><span class="text-slate-400">Qty:</span> {{ $item->add_to_cart['qty'] }}</div>
                                    @endif
                                    @if (! empty($item->add_to_cart['product_id']))
                                        <div><span class="text-slate-400">Product ID:</span> {{ $item->add_to_cart['product_id'] }}</div>
                                    @endif
                                    @if (! empty($item->add_to_cart['color_name']) || ! empty($item->add_to_cart['color_id']))
                                        <div>
                                            <span class="text-slate-400">Color:</span>
                                            {{ $item->add_to_cart['color_name'] ?: '—' }}
                                            @if (! empty($item->add_to_cart['color_id']))
                                                <span class="text-slate-400">(#{{ $item->add_to_cart['color_id'] }})</span>
                                            @endif
                                        </div>
                                    @endif
                                    @if (! empty($item->add_to_cart['size_name']) || ! empty($item->add_to_cart['size_id']))
                                        <div>
                                            <span class="text-slate-400">Size:</span>
                                            {{ $item->add_to_cart['size_name'] ?: '—' }}
                                            @if (! empty($item->add_to_cart['size_id']))
                                                <span class="text-slate-400">(#{{ $item->add_to_cart['size_id'] }})</span>
                                            @endif
                                        </div>
                                    @endif
                                @endif
                            </div>

                            @if ($jsonPayload)
                                <details class="mt-3">
                                    <summary class="text-[12px] font-medium text-slate-600 dark:text-slate-300 cursor-pointer">View JSON payload</summary>
                                    <pre class="mt-2 p-3 rounded-lg bg-slate-50 dark:bg-slate-900/50 text-[11px] overflow-x-auto text-slate-700 dark:text-slate-200">{{ json_encode($jsonPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @endif

                            @if ($item->is_grouped_product_view)
                                <details class="mt-3">
                                    <summary class="text-[12px] font-medium text-slate-600 dark:text-slate-300 cursor-pointer">
                                        View {{ $item->actions->count() }} color segments
                                    </summary>
                                    <div class="mt-2 space-y-2">
                                        @foreach ($item->actions as $segmentAction)
                                            @php
                                                $segmentSeconds = ($segmentAction->start_time && $segmentAction->end_time)
                                                    ? $segmentAction->start_time->diffInSeconds($segmentAction->end_time)
                                                    : null;
                                            @endphp
                                            <div class="rounded-lg bg-slate-50 dark:bg-slate-900/50 px-3 py-2 text-[11px] text-slate-600 dark:text-slate-300">
                                                <span class="font-medium">{{ $segmentAction->general_color_name ?: 'Unknown' }}</span>
                                                @if ($segmentSeconds !== null)
                                                    <span class="text-slate-400">· {{ $segmentSeconds }}s</span>
                                                @endif
                                                <span class="text-slate-400">· {{ \App\Support\TrackerTime::formatFromStorage($segmentAction->created_at ?? $segmentAction->start_time, 'h:i:s A') }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 dark:text-slate-500">No actions recorded for this session.</p>
                    @endforelse

                    @include('layouts.pagination', ['paginator' => $timeline])

                    <div x-show="replicaOpen"
                         x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center p-4"
                         role="dialog"
                         aria-modal="true"
                         aria-label="Create payment success">
                        <div class="absolute inset-0 bg-slate-900/50" @click="replicaOpen = false"></div>
                        <div class="relative w-full max-w-md rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 shadow-xl p-5">
                            <h3 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100 mb-1">Create payment success</h3>
                            <p class="text-[12px] text-slate-500 dark:text-slate-400 mb-4">
                                Cart, customer, and lines come from this proceed checkout. Paid at is the real payment time (stored like the storefront API).
                            </p>
                            <form method="POST"
                                  x-ref="replicaForm"
                                  x-bind:action="replicaFormAction"
                                  class="space-y-3"
                                  @submit.prevent="submitReplica()">
                                @csrf
                                <label class="block">
                                    <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Order number</span>
                                    <input type="text" name="order_id" required maxlength="50"
                                           class="mt-1 w-full h-9 px-3 text-[13px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900"
                                           placeholder="e.g. 6131901203">
                                </label>
                                <label class="block">
                                    <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Payment method</span>
                                    <input type="text" name="payment_method" required maxlength="50"
                                           x-model="replicaPaymentMethod"
                                           class="mt-1 w-full h-9 px-3 text-[13px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900"
                                           placeholder="e.g. WORLD PAY">
                                </label>
                                <label class="block">
                                    <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Amount paid (GBP)</span>
                                    <input type="number" name="amount_paid" required min="0" step="0.01"
                                           x-model="replicaGrand"
                                           class="mt-1 w-full h-9 px-3 text-[13px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900">
                                </label>
                                <label class="block">
                                    <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Shop order PK (optional)</span>
                                    <input type="text" name="order_pk" maxlength="20"
                                           class="mt-1 w-full h-9 px-3 text-[13px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900"
                                           placeholder="Internal order id">
                                </label>
                                <label class="block">
                                    <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Paid at ({{ \App\Support\TrackerTime::timezoneLabel() }})</span>
                                    <input type="text"
                                           name="paid_at"
                                           required
                                           readonly
                                           x-ref="replicaPaidAtInput"
                                           placeholder="Select date & time"
                                           autocomplete="off"
                                           class="etd-flatpickr-datetime mt-1 w-full h-9 px-3 text-[13px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900">
                                    <span class="mt-1 block text-[11px] text-slate-400">Must be after proceed checkout. Drives event time and dashboard payment day.</span>
                                </label>
                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button"
                                            @click="replicaOpen = false"
                                            class="px-3 h-9 text-[13px] rounded-lg border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            class="px-3 h-9 text-[13px] font-medium rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
                                        Submit
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div x-show="replicaDuplicateOpen"
                         x-cloak
                         class="fixed inset-0 z-[60] flex items-center justify-center p-4"
                         role="alertdialog"
                         aria-modal="true"
                         aria-label="Confirm duplicate payment success">
                        <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-md" @click="replicaDuplicateOpen = false"></div>
                        <div class="relative z-10 w-full max-w-sm rounded-xl border border-amber-200 dark:border-amber-800/50 bg-white dark:bg-slate-800 shadow-2xl ring-1 ring-black/5 dark:ring-white/10 p-5">
                            <h3 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100 mb-1">Already has payment success</h3>
                            <p class="text-[12px] text-slate-600 dark:text-slate-300 mb-2">
                                This session already has a payment success event. Are you sure you want to create another one?
                            </p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-4" x-show="existingOrderIds.length > 0">
                                Existing order<span x-show="existingOrderIds.length > 1">s</span>:
                                <span class="font-mono" x-text="existingOrderIds.join(', ')"></span>
                            </p>
                            <div class="flex justify-end gap-2">
                                <button type="button"
                                        @click="replicaDuplicateOpen = false"
                                        class="px-3 h-9 text-[13px] rounded-lg border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">
                                    Cancel
                                </button>
                                <button type="button"
                                        @click="confirmDuplicateReplica()"
                                        class="px-3 h-9 text-[13px] font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700">
                                    Yes, create
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                @include('ecom_tracker.partials.visitor-trust-panel', ['session' => $activityUser])

                <div class="section-card">
                    <div class="section-title">Session Summary</div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60 text-[13px]">
                        @foreach ([
                            'Device' => ucfirst($activityUser->device_type ?? '—') . ' · ' . ($activityUser->browser ?? '') . ' · ' . ($activityUser->os ?? ''),
                            'User' => $activityUser->identitySummary(),
                            'Orders' => ($sessionPaymentOrders ?? collect())->isEmpty()
                                ? '—'
                                : ($sessionPaymentOrders->count() === 1
                                    ? '#'.($sessionPaymentOrders->first()['order_id'] ?: '—').' · £'.number_format((float) ($sessionPaymentOrders->first()['amount_paid'] ?? 0), 2)
                                    : $sessionPaymentOrders->count().' orders'),
                            'First seen' => \App\Support\TrackerTime::formatFromStorage($activityUser->created_at, 'd M Y, h:i A'),
                            'Last active' => \App\Support\TrackerTime::formatFromStorage($latestActionAt ?? $activityUser->last_active_at, 'd M Y, h:i A'),
                        ] as $label => $value)
                            <div class="py-2.5 first:pt-0 min-w-0">
                                <div class="text-[11px] uppercase tracking-wide text-slate-400 mb-0.5">{{ $label }}</div>
                                <div class="text-slate-700 dark:text-slate-200 break-words text-[13px]">{{ $value ?: '—' }}</div>
                                @if ($label === 'Orders' && ($sessionPaymentOrders ?? collect())->count() > 1)
                                    <ul class="mt-1.5 space-y-1 text-[12px] text-slate-600 dark:text-slate-300">
                                        @foreach ($sessionPaymentOrders as $order)
                                            <li>
                                                #{{ $order['order_id'] ?: '—' }}
                                                · £{{ number_format((float) ($order['amount_paid'] ?? 0), 2) }}
                                                <span class="text-slate-400">· {{ \App\Support\TrackerTime::formatFromStorage($order['created_at'], 'd M, h:i A') }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                        <div class="py-2.5 min-w-0">
                            <div class="text-[11px] uppercase tracking-wide text-slate-400 mb-0.5">Landing page</div>
                            @if (filled($landingPage ?? null))
                                <a href="{{ $landingPage }}" target="_blank" rel="noopener" class="text-accent-500 hover:underline break-all text-[13px]">{{ $landingPage }}</a>
                            @else
                                <div class="text-slate-700 dark:text-slate-200 break-words text-[13px]">—</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-title">Session traffic</div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60 text-[13px]">
                        @forelse ($sessionTrafficTouches ?? [] as $touch)
                            <div class="py-2.5 first:pt-0 last:pb-0 min-w-0">
                                <div class="text-slate-800 dark:text-slate-100">
                                    <span class="text-slate-400">#{{ $touch['sequence'] }}</span>
                                    {{ $touch['source_label'] }} / {{ $touch['medium'] !== 'none' ? $touch['medium'] : '—' }}
                                    @if ($touch['is_last'])
                                        <span class="text-accent-600 dark:text-accent-400 text-[11px] font-semibold uppercase">· last in session</span>
                                    @endif
                                </div>
                                @if (filled($touch['campaign'] ?? null))
                                    <div class="text-slate-500 text-[12px] break-all min-w-0">{{ $touch['campaign'] }}</div>
                                @endif
                                <div class="text-[11px] text-slate-400">{{ $touch['captured_at'] }}</div>
                            </div>
                        @empty
                            @forelse ($trafficAttribution ?? [] as $label => $value)
                                <div class="py-2.5 first:pt-0 last:pb-0 min-w-0">
                                    <div class="text-[11px] uppercase tracking-wide text-slate-400 mb-0.5">{{ $label }}</div>
                                    <div class="text-slate-700 dark:text-slate-200 break-all">{{ $value }}</div>
                                </div>
                            @empty
                                <p class="text-sm text-slate-400 py-1">No UTM or click tracking data for this session.</p>
                            @endforelse
                        @endforelse
                    </div>
                </div>

                @if (($conversionAttribution ?? []) !== [])
                    <div class="section-card">
                        <div class="section-title">
                            @include('ecom_activity.partials.section-title-with-tip', [
                                'title' => 'Conversion',
                                'tip' => 'Order credit: last non-direct marketing touch for this visitor before payment (skips Direct). Session traffic above is this visit only (last touch in session).',
                            ])
                        </div>
                        <div class="divide-y divide-slate-100 dark:divide-slate-700/60 text-[13px]">
                            @foreach ($conversionAttribution as $label => $value)
                                <div class="py-2.5 first:pt-0 last:pb-0 min-w-0">
                                    <div class="text-[11px] uppercase tracking-wide text-slate-400 mb-0.5">{{ $label }}</div>
                                    <div class="text-slate-700 dark:text-slate-200 break-all text-[13px]">{{ $value }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="section-card">
                    <div class="section-title">Funnel Progress</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($funnelSteps as $step)
                            @php $reached = in_array($step, $reachedSteps, true); @endphp
                            <span class="badge-custom {{ $reached ? ($badgeColors[$step] ?? 'badge-green') : 'bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500' }}">
                                {{ str_replace('_', ' ', $step) }}
                            </span>
                        @endforeach
                    </div>

                    @include('ecom_activity.partials.related-visitor-sessions', [
                        'activityUser' => $activityUser,
                        'relatedVisitorSessions' => $relatedVisitorSessions ?? collect(),
                    ])
                </div>
            </div>
        </div>
    </div>
@endsection
