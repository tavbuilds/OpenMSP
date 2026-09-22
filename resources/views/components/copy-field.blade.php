@props([
    'label' => null,
    'value' => '',
    'placeholder' => null,
])

{{-- A read-only value with a copy button. `placeholder` renders instead of a
     copy button when there is nothing to copy (e.g. "leave empty"). --}}
<div x-data="{ copied: false }">
    @if (filled($label))
        <div class="omsp-label">{{ $label }}</div>
    @endif

    <div class="omsp-field">
        <code
            class="omsp-code @if (filled($placeholder)) omsp-code-muted @endif"
            x-ref="value"
        >{{ filled($placeholder) ? $placeholder : $value }}</code>

        @if (blank($placeholder))
            <x-filament::button
                type="button"
                size="sm"
                color="gray"
                x-on:click="
                    navigator.clipboard.writeText($refs.value.textContent.trim());
                    copied = true;
                    setTimeout(() => copied = false, 1500);
                "
            >
                <span x-show="!copied">{{ __('Copy') }}</span>
                <span x-cloak x-show="copied">{{ __('Copied') }}</span>
            </x-filament::button>
        @endif
    </div>
</div>
