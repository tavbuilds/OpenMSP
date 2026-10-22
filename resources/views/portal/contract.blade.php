@extends('portal.layouts.app')

@section('title', $contract->name)

@section('content')
<p class="muted"><a href="{{ route('portal.dashboard') }}">&larr; {{ __('Back to services') }}</a></p>

<h1>{{ $contract->name }}</h1>
<p class="muted lede">
    {{ trans_choice(
        '{0} This contract has no active services right now.|{1} One service in this contract.|[2,*] :count services in this contract.',
        $contract->services->count(),
        ['count' => $contract->services->count()],
    ) }}
</p>

<div class="card stack-card">
    <dl class="stack-meta">
        <div>
            <dt>{{ __('Total') }}</dt>
            <dd><strong>{{ $contract->portalSalePriceFormatted() }}</strong></dd>
        </div>
        <div>
            <dt>{{ __('Billing') }}</dt>
            <dd>{{ $contract->billing_cycle?->getLabel() }}</dd>
        </div>
        <div>
            <dt>{{ __('Term') }}</dt>
            <dd>{{ $contract->portalRenewalDescription() }}</dd>
        </div>
    </dl>

    <div class="stack-actions">
        @include('portal.partials.cancel', [
            'contract' => $contract,
            'label' => __('Cancel this whole contract'),
        ])
    </div>
</div>

@if ($contract->services->isNotEmpty())
    {{-- Phone: one card per service. --}}
    <div class="stack">
        @foreach ($contract->services as $service)
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
                </dl>

                <div class="stack-actions">
                    @include('portal.partials.cancel', [
                        'contract' => $service,
                        'label' => __('Cancel this service'),
                    ])
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
                        <th scope="col"><span class="sr-only">{{ __('Cancel') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($contract->services as $service)
                    <tr>
                        <td>
                            <strong>{{ $service->name }}</strong>
                            @if ($service->quantity > 1)
                                <div class="muted cell-sub">{{ __('Quantity: :count', ['count' => $service->quantity]) }}</div>
                            @endif
                        </td>
                        <td class="num">{{ $service->portalSalePriceFormatted() }}</td>
                        <td>
                            @include('portal.partials.cancel', [
                                'contract' => $service,
                                'label' => __('Cancel'),
                            ])
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
