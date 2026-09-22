@extends('portal.layouts.app')

@section('title', 'Services')

@section('content')
<h1>Active services</h1>
<p class="muted lede">{{ $company?->name }} — the services and licenses we currently run for you.</p>

@if ($services->isEmpty())
    <div class="card"><p class="muted empty">There are no active services or licenses right now.</p></div>
@else
    {{-- Phone and small tablet: one card per service. --}}
    <div class="stack">
        @foreach ($services as $service)
            <article class="card stack-card">
                <h2>{{ $service->name }}</h2>
                @if ($service->quantity > 1)
                    <div class="muted cell-sub">Quantity: {{ $service->quantity }}</div>
                @endif

                <dl class="stack-meta">
                    <div>
                        <dt>Price</dt>
                        <dd><strong>{{ $service->portalSalePriceFormatted() }}</strong></dd>
                    </div>
                    <div>
                        <dt>Billing</dt>
                        <dd>{{ $service->billing_cycle?->getLabel() }}</dd>
                    </div>
                    <div>
                        <dt>Term</dt>
                        <dd>{{ $service->portalRenewalDescription() }}</dd>
                    </div>
                </dl>

                <div class="stack-actions">
                    @if ($service->auto_collect && $service->stripe_payment_status === 'active')
                        <span class="badge badge-ok">Auto-collect on</span>
                    @elseif ($service->stripe_payment_status === 'pending')
                        <span class="badge badge-pending">Activating…</span>
                    @elseif ($service->stripe_payment_status === 'past_due')
                        <span class="badge badge-err">Payment failed</span>
                        <a class="btn" href="{{ route('portal.auto-collect', $service) }}">Set up payment again</a>
                    @else
                        <a class="btn" href="{{ route('portal.auto-collect', $service) }}">Enable auto-collect</a>
                        <span class="muted cell-sub">via iDEAL → SEPA mandate</span>
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
                        <th scope="col">Service / license</th>
                        <th scope="col" class="num">Price</th>
                        <th scope="col">Term / renewal</th>
                        <th scope="col">Collection</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($services as $service)
                    <tr>
                        <td>
                            <strong>{{ $service->name }}</strong>
                            @if ($service->quantity > 1)
                                <div class="muted cell-sub">Quantity: {{ $service->quantity }}</div>
                            @endif
                        </td>
                        <td class="num">
                            {{ $service->portalSalePriceFormatted() }}
                            <div class="muted cell-sub">{{ $service->billing_cycle?->getLabel() }}</div>
                        </td>
                        <td>{{ $service->portalRenewalDescription() }}</td>
                        <td>
                            @if ($service->auto_collect && $service->stripe_payment_status === 'active')
                                <span class="badge badge-ok">Auto-collect on</span>
                            @elseif ($service->stripe_payment_status === 'pending')
                                <span class="badge badge-pending">Activating…</span>
                            @elseif ($service->stripe_payment_status === 'past_due')
                                <span class="badge badge-err">Payment failed</span>
                                <div class="cell-sub">
                                    <a class="btn btn-sm" href="{{ route('portal.auto-collect', $service) }}">Set up payment again</a>
                                </div>
                            @else
                                <a class="btn btn-sm" href="{{ route('portal.auto-collect', $service) }}">Enable auto-collect</a>
                                <div class="muted cell-sub">via iDEAL → SEPA mandate</div>
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
