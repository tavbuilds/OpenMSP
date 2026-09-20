@php
    $locales = \App\Support\LocaleCatalog::all();
    $current = app()->getLocale();
@endphp
<label class="sr-only" for="locale-switcher">{{ __('Language') }}</label>
<select
    id="locale-switcher"
    onchange="window.location = {{ json_encode(url('/locale')) }} + '/' + this.value"
    class="rounded-lg border border-gray-300 bg-white px-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
    style="min-height: 2.5rem;"
    aria-label="{{ __('Language') }}"
>
    @foreach ($locales as $code => $meta)
        <option value="{{ $code }}" @selected($current === $code)>{{ $meta['native'] }}</option>
    @endforeach
</select>
