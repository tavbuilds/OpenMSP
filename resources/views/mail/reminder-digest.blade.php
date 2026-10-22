@component('mail::message')
{{ $salutation }}

{{ $heading }}

@foreach ($items as $item)
@component('mail::panel')
**{{ $item->title }}** — {{ $item->status }}

{{ __('Date: :when', ['when' => $item->when]) }}
@if ($item->meta)
{{ $item->meta }}
@endif
@if ($item->url)

[{{ __('Open') }}]({{ $item->url }})
@endif
@endcomponent
@endforeach
@if ($closing)

{{ $closing }}
@endif
@endcomponent
