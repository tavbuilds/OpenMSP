@php
    $locales = \App\Support\LocaleCatalog::all();
    $current = app()->getLocale();
@endphp
<select
    id="locale-switcher"
    onchange="window.location = {{ json_encode(url('/locale')) }} + '/' + this.value"
    class="omsp-locale"
    aria-label="{{ __('Language') }}"
>
    @foreach ($locales as $code => $meta)
        <option value="{{ $code }}" @selected($current === $code)>{{ $meta['native'] }}</option>
    @endforeach
</select>
