@extends('portal.layouts.app')

@section('title', __('Services'))

@section('content')
<h1>{{ __('Active services') }}</h1>
<p class="muted lede">{{ __(':company — the services and licenses we currently run for you.', ['company' => $company?->name]) }}</p>

@if ($services->isEmpty())
    <div class="card"><p class="muted empty">{{ __('There are no active services or licenses right now.') }}</p></div>
@else
    {{-- Phone and small tablet: one card per service. --}}
    <div class="stack">
        @foreach ($services as $service)
            <article class="card stack-card">
                <h2>{{ $service->name }}</h2>
                @if ($service->quantity > 1)
                    <div class="muted cell-sub">{{ __('Quantity: :count', ['count' => $service->quantity]) }}</div>
                @endif

                <dl class="stack-meta">
                    <div>
                        <dt>{{ __('Price') }}</dt>
                        <dd><strong>{{ $service->portalSalePriceFormatted() }}</strong></dd>
                    </div>
                    <div>
                        <dt>{{ __('Billing') }}</dt>
                        <dd>{{ $service->billing_cycle?->getLabel() }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Term') }}</dt>
                        <dd>{{ $service->portalRenewalDescription() }}</dd>
                    </div>
                </dl>

                <div class="stack-actions">
                    @if ($service->auto_collect && $service->stripe_payment_status === 'active')
                        <span class="badge badge-ok">{{ __('Auto-collect on') }}</span>
                    @elseif ($service->stripe_payment_status === 'pending')
                        <span class="badge badge-pending">{{ __('Activating…') }}</span>
                    @elseif ($service->stripe_payment_status === 'past_due')
                        <span class="badge badge-err">{{ __('Payment failed') }}</span>
                        <a class="btn" href="{{ route('portal.auto-collect', $service) }}">{{ __('Set up payment again') }}</a>
                    @else
                        <a class="btn" href="{{ route('portal.auto-collect', $service) }}">{{ __('Enable auto-collect') }}</a>
                        <span class="muted cell-sub">{{ __('via iDEAL → SEPA mandate') }}</span>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    {{-- Tablet and up: the same services as a table. --}}
    <div class="data-table">
        <div class="card card-flush">
            <table>
                <thead>
                    <tr>
                        <th scope="col">{{ __('Service / license') }}</th>
                        <th scope="col" class="num">{{ __('Price') }}</th>
                        <th scope="col">{{ __('Term / renewal') }}</th>
                        <th scope="col">{{ __('Collection') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($services as $service)
                    <tr>
                        <td>
                            <strong>{{ $service->name }}</strong>
                            @if ($service->quantity > 1)
                                <div class="muted cell-sub">{{ __('Quantity: :count', ['count' => $service->quantity]) }}</div>
                            @endif
                        </td>
                        <td class="num">
                            {{ $service->portalSalePriceFormatted() }}
                            <div class="muted cell-sub">{{ $service->billing_cycle?->getLabel() }}</div>
                        </td>
                        <td>{{ $service->portalRenewalDescription() }}</td>
                        <td>
                            @if ($service->auto_collect && $service->stripe_payment_status === 'active')
                                <span class="badge badge-ok">{{ __('Auto-collect on') }}</span>
                            @elseif ($service->stripe_payment_status === 'pending')
                                <span class="badge badge-pending">{{ __('Activating…') }}</span>
                            @elseif ($service->stripe_payment_status === 'past_due')
                                <span class="badge badge-err">{{ __('Payment failed') }}</span>
                                <div class="cell-sub">
                                    <a class="btn btn-sm" href="{{ route('portal.auto-collect', $service) }}">{{ __('Set up payment again') }}</a>
                                </div>
                            @else
                                <a class="btn btn-sm" href="{{ route('portal.auto-collect', $service) }}">{{ __('Enable auto-collect') }}</a>
                                <div class="muted cell-sub">{{ __('via iDEAL → SEPA mandate') }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
