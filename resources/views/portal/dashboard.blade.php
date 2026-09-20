@extends('portal.layouts.app')

@section('title', 'Services')

@section('content')
<h1>Active services</h1>
<p class="muted lede">{{ $company?->name }} — services for your organization only.</p>

@if ($services->isEmpty())
    <div class="card"><p class="muted">There are no active services or licenses right now.</p></div>
@else
    <div class="stack">
        @foreach ($services as $service)
            <article class="card stack-card">
                <h2>{{ $service->name }}</h2>
                @if ($service->quantity > 1)
                    <div class="muted">Quantity: {{ $service->quantity }}</div>
                @endif
                <div class="stack-meta">
                    <div>
                        <dt>Price</dt>
                        <strong>{{ $service->portalSalePriceFormatted() }}</strong>
                    </div>
                    <div>
                        <dt>Billing</dt>
                        <span>{{ $service->billing_cycle?->getLabel() }}</span>
                    </div>
                    <div>
                        <dt>Term</dt>
                        <span>{{ $service->portalRenewalDescription() }}</span>
                    </div>
                </div>
                <div class="stack-actions">
                    @if ($service->auto_collect && $service->stripe_payment_status === 'active')
                        <span class="badge badge-ok">Auto-collect on</span>
                    @elseif ($service->stripe_payment_status === 'pending')
                        <span class="badge badge-pending">Activating…</span>
                    @elseif ($service->stripe_payment_status === 'past_due')
                        <span class="badge badge-pending">Payment failed</span>
                        <div style="margin-top:.65rem">
                            <a class="btn btn-block" href="{{ route('portal.auto-collect', $service) }}">Set up payment again</a>
                        </div>
                    @else
                        <a class="btn btn-block" href="{{ route('portal.auto-collect', $service) }}">Enable auto-collect</a>
                        <div class="muted" style="font-size:.8rem;margin-top:.4rem">via iDEAL → SEPA mandate</div>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    <div class="data-table">
        <div class="card" style="padding:0; overflow:auto">
            <table>
                <thead>
                    <tr>
                        <th>Service / license</th>
                        <th>Price</th>
                        <th>Term / renewal</th>
                        <th>Collection</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($services as $service)
                    <tr>
                        <td>
                            <strong>{{ $service->name }}</strong>
                            @if ($service->quantity > 1)
                                <div class="muted">Quantity: {{ $service->quantity }}</div>
                            @endif
                        </td>
                        <td>{{ $service->portalSalePriceFormatted() }}
                            <div class="muted">{{ $service->billing_cycle?->getLabel() }}</div>
                        </td>
                        <td>{{ $service->portalRenewalDescription() }}</td>
                        <td>
                            @if ($service->auto_collect && $service->stripe_payment_status === 'active')
                                <span class="badge badge-ok">Auto-collect on</span>
                            @elseif ($service->stripe_payment_status === 'pending')
                                <span class="badge badge-pending">Activating…</span>
                            @elseif ($service->stripe_payment_status === 'past_due')
                                <span class="badge badge-pending">Payment failed</span>
                                <div style="margin-top:.5rem">
                                    <a class="btn" href="{{ route('portal.auto-collect', $service) }}">Set up payment again</a>
                                </div>
                            @else
                                <a class="btn" href="{{ route('portal.auto-collect', $service) }}">Enable auto-collect</a>
                                <div class="muted" style="font-size:.8rem;margin-top:.35rem">via iDEAL → SEPA mandate</div>
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
