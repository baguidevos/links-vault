@php
    $fieldWrapperView = $getFieldWrapperView();
    $extraAttributeBag = $getExtraAttributeBag();
    $id = $getId();
    $isDisabled = $isDisabled();
    $statePath = $getStatePath();
    $iconSets = $getIconSets();
    $selectedIcon = $getState();
    $selectedIconLabel = $getSelectedIconLabel();
    $isSearchable = $isSearchable();
    $columns = $getGridColumns();
    $placeholder = $getPlaceholder();

    $isPrefixInline = $isPrefixInline();
    $isSuffixInline = $isSuffixInline();
    $prefixActions = $getPrefixActions();
    $prefixIcon = $getPrefixIcon();
    $prefixIconColor = $getPrefixIconColor();
    $prefixLabel = $getPrefixLabel();
    $suffixActions = $getSuffixActions();
    $suffixIcon = $getSuffixIcon();
    $suffixIconColor = $getSuffixIconColor();
    $suffixLabel = $getSuffixLabel();

    $hasInlinePrefix = $isPrefixInline && (count($prefixActions) || $prefixIcon || filled($prefixLabel));
    $hasInlineSuffix = $isSuffixInline && (count($suffixActions) || $suffixIcon || filled($suffixLabel));
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    class="fi-fo-select-wrp"
>
    <x-filament::input.wrapper
        :disabled="$isDisabled"
        :inline-prefix="$isPrefixInline"
        :inline-suffix="$isSuffixInline"
        :prefix="$prefixLabel"
        :prefix-actions="$prefixActions"
        :prefix-icon="$prefixIcon"
        :prefix-icon-color="$prefixIconColor"
        :suffix="$suffixLabel"
        :suffix-actions="$suffixActions"
        :suffix-icon="$suffixIcon"
        :suffix-icon-color="$suffixIconColor"
        :valid="! $errors->has($statePath)"
        x-on:focus-input.stop="$el.querySelector('.fi-select-input-btn')?.focus()"
        :attributes="
            \Filament\Support\prepare_inherited_attributes($extraAttributeBag)
                ->class([
                    'fi-fo-select',
                    'fi-fo-select-has-inline-prefix' => $hasInlinePrefix,
                ])
        "
    >
        <div
            x-data="{
                state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
                isOpen: false,
                isDisabled: @js($isDisabled),
                search: '',
                icons: [],
                iconSets: @js($iconSets),
                endpointUrl: @js(route('filament-icon-picker.icons')),
                perPage: 120,
                offset: 0,
                hasMore: true,
                isLoading: false,
                observer: null,
                selectedIcon: @js($selectedIcon),
                selectedIconLabel: @js($selectedIconLabel),
                statePath: @js($statePath),

                async fetchIcons({ reset = false } = {}) {
                    if (this.isLoading) {
                        return
                    }

                    this.isLoading = true

                    if (reset) {
                        this.offset = 0
                        this.hasMore = true
                        this.icons = []
                    }

                    try {
                        const url = new URL(this.endpointUrl, window.location.origin)

                        url.searchParams.set('q', this.search || '')
                        url.searchParams.set('limit', String(this.perPage))
                        url.searchParams.set('offset', String(this.offset))

                        for (const set of (this.iconSets || [])) {
                            url.searchParams.append('sets[]', set)
                        }

                        const response = await fetch(url.toString(), {
                            headers: {
                                'Accept': 'application/json',
                            },
                        })

                        if (! response.ok) {
                            return
                        }

                        const data = await response.json()
                        const items = data?.items || []

                        this.icons = reset ? items : [...this.icons, ...items]
                        this.offset = data?.meta?.nextOffset ?? this.icons.length
                        this.hasMore = Boolean(data?.meta?.hasMore)
                    } finally {
                        this.isLoading = false
                    }
                },

                refreshIcons() {
                    this.$nextTick(() => {
                        this.$refs.scrollContainer?.scrollTo({ top: 0 })
                    })

                    this.fetchIcons({ reset: true })
                },

                loadMore() {
                    if (this.hasMore && ! this.isLoading) {
                        this.fetchIcons({ reset: false })
                    }
                },

                ensureInfiniteScrollObserver() {
                    if (this.observer || ! this.$refs.scrollContainer || ! this.$refs.infiniteSentinel) {
                        return
                    }

                    this.observer = new IntersectionObserver((entries) => {
                        if (! this.isOpen) {
                            return
                        }

                        const entry = entries?.[0]

                        if (! entry?.isIntersecting) {
                            return
                        }

                        this.loadMore()
                    }, {
                        root: this.$refs.scrollContainer,
                        rootMargin: '120px',
                        threshold: 0,
                    })

                    this.observer.observe(this.$refs.infiniteSentinel)
                },

                init() {
                    this.$nextTick(() => {
                        this.ensureInfiniteScrollObserver()
                    })
                },

                selectIcon(icon) {
                    this.state = icon.name
                    this.$wire.set(this.statePath, icon.name)
                    this.selectedIcon = icon.name
                    this.selectedIconLabel = icon.label
                    this.isOpen = false
                    this.search = ''
                },

                clearSelection() {
                    this.state = null
                    this.$wire.set(this.statePath, null)
                    this.selectedIcon = null
                    this.selectedIconLabel = null
                    this.search = ''
                    this.icons = []
                    this.offset = 0
                    this.hasMore = true
                },

                toggleDropdown() {
                    if (! this.isDisabled) {
                        this.isOpen = ! this.isOpen

                        if (this.isOpen) {
                            if (this.icons.length === 0) {
                                this.fetchIcons({ reset: true })
                            }

                            this.$nextTick(() => {
                                this.ensureInfiniteScrollObserver()
                            })

                            this.$nextTick(() => {
                                this.$refs.searchInput?.focus()
                            })
                        }
                    }
                },
            }"
            wire:ignore
            x-on:click.away="isOpen = false"
            x-on:keydown.escape.window="isOpen = false"
            class="relative"
        >
            <div class="fi-select-input">
                <div
                    class="fi-select-input-ctn"
                    :class="{
                        'fi-disabled': isDisabled,
                        'fi-select-input-ctn-clearable': selectedIcon && ! isDisabled,
                    }"
                    aria-haspopup="listbox"
                >
                    <button
                        class="fi-select-input-btn"
                        type="button"
                        x-on:click="toggleDropdown()"
                        x-bind:aria-expanded="isOpen ? 'true' : 'false'"
                        x-bind:disabled="isDisabled"
                    >
                        <div class="fi-select-input-value-ctn">
                            <template x-if="selectedIconLabel">
                                <span class="fi-select-input-value-label" x-text="selectedIconLabel"></span>
                            </template>

                            <template x-if="selectedIcon && ! selectedIconLabel">
                                <span class="fi-select-input-value-label" x-text="selectedIcon"></span>
                            </template>

                            <template x-if="! selectedIcon && ! selectedIconLabel">
                                <span class="fi-select-input-placeholder">{{ $placeholder }}</span>
                            </template>
                        </div>
                    </button>

                    <template x-if="selectedIcon && ! isDisabled">
                        <button
                            type="button"
                            class="fi-select-input-value-remove-btn"
                            x-on:click.stop="clearSelection()"
                            aria-label="Clear selection"
                        >
                            <svg class="fi-icon fi-size-sm" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </template>
                </div>

                <div
                    x-show="isOpen"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="transform opacity-0 scale-95"
                    x-transition:enter-end="transform opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="transform opacity-100 scale-100"
                    x-transition:leave-end="transform opacity-0 scale-95"
                    class="fi-dropdown-panel mt-2"
                    style="display: none; max-height: none;"
                >
                    <div class="flex flex-col">
                        <!-- Search Input -->
                        @if ($isSearchable)
                            <div class="fi-select-input-search-ctn p-3">
                                <div class="relative">
                                    <input
                                        x-ref="searchInput"
                                        x-model="search"
                                        x-on:input.debounce.300ms="refreshIcons()"
                                        type="text"
                                        placeholder="Search icons..."
                                        class="fi-input w-full ps-10!"
                                        id="searchInput"
                                    />
                                    <svg
                                        class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                            </div>
                        @endif

                        <div class="p-3 overflow-y-auto max-h-96 tl-scrollbar" x-ref="scrollContainer">
                            <div
                                class="grid gap-2"
                                style="grid-template-columns: repeat({{ $columns }}, minmax(0, 1fr));"
                            >
                                <template x-for="icon in icons" :key="icon.name">
                                    <button
                                        type="button"
                                        x-on:click="selectIcon(icon)"
                                        x-bind:title="icon.label"
                                        class="flex items-center justify-center p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-950/20 transition-colors group"
                                        :class="{
                                            'border-primary-500 dark:border-primary-500 bg-primary-50 dark:bg-primary-950/20': state === icon.name,
                                        }"
                                    >
                                        <span
                                            class="text-gray-600 dark:text-gray-300 group-hover:text-primary-600 dark:group-hover:text-primary-400"
                                            :class="{ 'text-primary-600 dark:text-primary-400': state === icon.name }"
                                            x-html="icon.svg"
                                        ></span>
                                    </button>
                                </template>
                            </div>

                            <div x-show="isLoading" class="py-3 text-center text-sm text-gray-500 dark:text-gray-400">
                                Loading icons...
                            </div>

                            <div x-show="hasMore" class="h-px" x-ref="infiniteSentinel"></div>

                            <div x-show="hasMore && ! isLoading" class="pt-2 text-center text-xs text-gray-500 dark:text-gray-400">
                                Scroll to load more
                            </div>

                            <div
                                x-show="! isLoading && icons.length === 0"
                                class="py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                            >
                                No icons found
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </x-filament::input.wrapper>
</x-dynamic-component>
