@props([
    'subscribeRoute' => null,
])
@php
    $t = function($key, $default) {
        $trans = __($key);
        return $trans === $key ? $default : $trans;
    };
    $tc = function($key, $number, $default) {
        $trans = trans_choice($key, $number);
        return $trans === $key ? $default : $trans;
    };

    $plans = config('subbase.models.plan', \Nafiswatsiq\Subbase\Models\Plan::class)::active()
        ->with(['features', 'discounts'])
        ->orderBy('sort_order')
        ->get();

    $intervals = $plans->pluck('invoice_interval')->filter()->unique()->values();
@endphp

<div class="w-full px-4 py-20 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-6xl">

        {{-- ── Header ─────────────────────────────────────────────────── --}}
        <div class="text-center mb-14">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-600 ring-1 ring-blue-100 mb-4">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                {{ $t('subbase::plan.pricing.label', 'Pricing') }}
            </span>
            <h2 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                {{ $t('subbase::plan.pricing.title', 'Simple, transparent pricing') }}
            </h2>
            <p class="mt-4 text-lg text-gray-500 max-w-xl mx-auto">
                {{ $t('subbase::plan.pricing.subtitle', 'Choose the plan that fits your needs. No hidden fees.') }}
            </p>
        </div>

        @if($plans->isEmpty())
            <div class="flex flex-col items-center justify-center py-24 text-gray-400">
                <svg class="w-12 h-12 mb-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4"/></svg>
                <p class="text-sm">{{ $t('subbase::plan.pricing.no_plans', 'No active plans available at the moment.') }}</p>
            </div>
        @else

            {{-- ── Interval toggle ─────────────────────────────────────── --}}
            @if($intervals->count() > 1)
                <div class="flex justify-center mb-12">
                    <div class="relative inline-flex rounded-xl bg-gray-100 p-1 gap-1" role="tablist">
                        @foreach($intervals as $index => $interval)
                            <button
                                role="tab"
                                aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                                data-target-interval="{{ $interval }}"
                                class="tab-btn relative z-10 px-6 py-2 text-sm font-medium rounded-lg transition-all duration-200
                                       {{ $index === 0
                                           ? 'bg-white text-gray-900 shadow-sm ring-1 ring-gray-200'
                                           : 'text-gray-500 hover:text-gray-800' }}">
                                {{ $tc("subbase::plan.pricing.interval.{$interval}", 1, ucfirst($interval)) }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ── Cards ───────────────────────────────────────────────── --}}
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 items-end">
                @foreach($plans as $plan)
                    @php
                        $isFeatured = $plan->featured;
                        $currency   = \Nafiswatsiq\Subbase\Models\Plan::currencyFromLocale(app()->getLocale());
                        $pricing    = \Nafiswatsiq\Subbase\Helpers\PlanPriceHelper::formatWithDiscounts($plan, $currency);
                        $hasDiscount = $pricing['discount_info'] !== null;

                        if ($subscribeRoute) {
                            $subscribeUrl = route($subscribeRoute, ['plan' => $plan->slug]);
                        } else {
                            $subscribeUrl = \Illuminate\Support\Facades\Route::has('subbase.subscribe')
                                ? route('subbase.subscribe', ['plan' => $plan->slug])
                                : '#';
                        }
                    @endphp

                    <div
                        class="plan-card group relative flex flex-col rounded-2xl transition-all duration-300 bg-white text-gray-900
                               {{ $isFeatured
                                   ? 'shadow-2xl scale-[1.03] ring-2 ring-blue-500 ring-offset-2'
                                   : 'shadow-sm ring-1 ring-gray-200 hover:shadow-lg hover:-translate-y-0.5' }}"
                        data-interval="{{ $plan->invoice_interval }}"
                        @if($intervals->count() > 1 && $plan->invoice_interval !== $intervals[0])
                            style="display: none;"
                        @endif>

                        {{-- Top badges row --}}
                        @if($isFeatured || $hasDiscount)
                            <div class="flex items-center justify-between px-6 pt-5 pb-0 gap-2">
                                <div>
                                    @if($isFeatured)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-[10px] font-bold tracking-widest text-blue-600 uppercase ring-1 ring-blue-200">
                                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                            {{ $t('subbase::plan.pricing.most_popular', 'Most Popular') }}
                                        </span>
                                    @endif
                                </div>
                                <div>
                                    @if($hasDiscount)
                                        <span class="inline-flex items-center rounded-full bg-red-500 px-2.5 py-0.5 text-[10px] font-bold tracking-wider text-white uppercase">
                                            {{ $pricing['discount_info']['formatted_value'] }} {{ $t('subbase::plan.pricing.off', 'OFF') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="flex flex-col flex-1 p-6 {{ ($isFeatured || $hasDiscount) ? 'pt-4' : 'pt-6' }}">

                            {{-- Plan name & description --}}
                            <div class="mb-6">
                                <h3 class="text-lg font-bold text-gray-900">
                                    {{ $plan->name }}
                                </h3>
                                @if($plan->description)
                                    <p class="mt-1.5 text-sm leading-relaxed text-gray-500">
                                        {{ $plan->description }}
                                    </p>
                                @endif
                            </div>

                            {{-- Price block --}}
                            <div class="mb-6">
                                @if($hasDiscount)
                                    <div class="flex items-baseline gap-2 flex-wrap">
                                        <span class="text-4xl font-extrabold tracking-tight text-gray-900">
                                            {{ $pricing['final_price'] }}
                                        </span>
                                        <span class="text-base text-gray-400 line-through">
                                            {{ $pricing['original_price'] }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-4xl font-extrabold tracking-tight text-gray-900">
                                        {{ $pricing['final_price'] }}
                                    </span>
                                @endif
                                @if($plan->invoice_interval)
                                    <p class="mt-1 text-sm text-gray-400">
                                        {{ $t('subbase::plan.pricing.per', 'per') }}
                                        {{ $plan->invoice_period > 1 ? $plan->invoice_period . ' ' : '' }}{{ $tc("subbase::plan.pricing.interval.{$plan->invoice_interval}", $plan->invoice_period, $plan->invoice_interval) }}
                                    </p>
                                @endif
                            </div>

                            {{-- Divider --}}
                            <div class="h-px w-full bg-gray-100 mb-6"></div>

                            {{-- Features --}}
                            <ul role="list" class="flex flex-col gap-3 flex-1 mb-8">
                                @forelse($plan->features as $feature)
                                    <li class="flex items-start gap-3">
                                        <span class="mt-0.5 flex-shrink-0 flex h-5 w-5 items-center justify-center rounded-full bg-blue-50">
                                            <svg class="h-3 w-3 text-blue-600" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="1.5,6.5 4.5,9.5 10.5,2.5"/>
                                            </svg>
                                        </span>
                                        <span>
                                            <span class="text-sm font-medium text-gray-800">
                                                {{ $feature->name }}
                                            </span>
                                            @if($feature->description)
                                                <span class="block text-xs mt-0.5 text-gray-400">
                                                    {{ $feature->description }}
                                                </span>
                                            @endif
                                        </span>
                                    </li>
                                @empty
                                    <li class="text-sm italic text-gray-400">
                                        {{ $t('subbase::plan.pricing.no_features', 'No features listed.') }}
                                    </li>
                                @endforelse
                            </ul>

                            {{-- CTA --}}
                            <a href="{{ $subscribeUrl }}"
                               class="mt-auto flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold transition-all duration-150
                                      {{ $isFeatured
                                          ? 'bg-blue-500 text-white hover:bg-blue-400 shadow-lg shadow-blue-500/30'
                                          : 'bg-gray-900 text-white hover:bg-gray-700' }}">
                                {{ $t('subbase::plan.pricing.subscribe_button', 'Get started') }}
                                <svg class="w-4 h-4 transition-transform duration-150 group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                            </a>

                        </div>
                    </div>
                @endforeach
            </div>

        @endif
    </div>
</div>

@if(!empty($intervals) && $intervals->count() > 1)
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tabs  = document.querySelectorAll('.tab-btn');
        const cards = document.querySelectorAll('.plan-card');

        function switchTab(targetInterval) {
            tabs.forEach(tab => {
                const active = tab.getAttribute('data-target-interval') === targetInterval;
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.classList.toggle('bg-white',       active);
                tab.classList.toggle('text-gray-900',  active);
                tab.classList.toggle('shadow-sm',      active);
                tab.classList.toggle('ring-1',         active);
                tab.classList.toggle('ring-gray-200',  active);
                tab.classList.toggle('text-gray-500',  !active);
                tab.classList.toggle('hover:text-gray-800', !active);
            });

            cards.forEach(card => {
                const show = card.getAttribute('data-interval') === targetInterval;
                if (show) {
                    card.style.display = 'flex';
                    card.style.opacity = '0';
                    requestAnimationFrame(() => {
                        card.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                        card.style.opacity    = '1';
                    });
                } else {
                    card.style.display = 'none';
                }
            });
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', () => switchTab(tab.getAttribute('data-target-interval')));
        });
    });
</script>
@endif