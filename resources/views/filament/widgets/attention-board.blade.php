{{--
    Tab strip plus the five watchlist tables, only one of them on screen.
    Each table is its own Livewire widget, so sorting and paging on the tab
    you are looking at never touches the ones behind it.
--}}
<x-filament-widgets::widget class="fi-wi-attention-board">
    @if (empty($panels))
        <x-filament::section
            :heading="__('Needs attention')"
            icon="heroicon-o-check-circle"
            icon-color="success"
        >
            <p class="omsp-prose">
                {{ __('Nothing needs attention in the next 60 days.') }}
            </p>
        </x-filament::section>
    @else
        <div x-data="{ tab: @js($activeKey) }">
            <div class="omsp-board-tabs" role="tablist" aria-label="{{ __('Needs attention') }}">
                @foreach ($panels as $panel)
                    <button
                        type="button"
                        role="tab"
                        class="omsp-board-tab"
                        :class="{ 'omsp-board-tab-on': tab === @js($panel['key']) }"
                        :aria-selected="tab === @js($panel['key']) ? 'true' : 'false'"
                        x-on:click="tab = @js($panel['key'])"
                    >
                        <span>{{ $panel['label'] }}</span>
                        <span class="omsp-board-count omsp-board-count-{{ $panel['tone'] }}">{{ $panel['count'] }}</span>
                    </button>
                @endforeach
            </div>

            @foreach ($panels as $panel)
                <div x-show="tab === @js($panel['key'])" x-cloak>
                    @livewire($panel['widget'], [], key('attention-'.$panel['key']))
                </div>
            @endforeach
        </div>
    @endif
</x-filament-widgets::widget>
