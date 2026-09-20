@extends('portal.layouts.app')

@section('title', 'Invoices')

@section('content')
<h1>Invoices</h1>
<p class="muted lede">Stripe invoices for {{ $company?->name }}. PDF via the Stripe download link.</p>

@if ($invoices->isEmpty())
    <div class="card"><p class="muted">No invoices yet.</p></div>
@else
    <div class="stack">
        @foreach ($invoices as $invoice)
            <article class="card stack-card">
                <h2>{{ $invoice->number ?: $invoice->stripe_invoice_id }}</h2>
                <div class="stack-meta">
                    <div>
                        <dt>Date</dt>
                        <span>{{ $invoice->stripe_created_at?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}</span>
                    </div>
                    <div>
                        <dt>Amount</dt>
                        <strong>{{ $invoice->status === 'paid' ? $invoice->amountPaidFormatted() : $invoice->amountDueFormatted() }}</strong>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <span class="badge">{{ $invoice->status }}</span>
                    </div>
                </div>
                <div class="stack-actions">
                    @if ($invoice->invoice_pdf || $invoice->hosted_invoice_url)
                        <a class="btn btn-outline btn-block" href="{{ route('portal.invoices.download', $invoice) }}">PDF / view</a>
                    @else
                        <span class="muted">No PDF available</span>
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
                        <th>Number</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice->number ?: $invoice->stripe_invoice_id }}</td>
                        <td>{{ $invoice->stripe_created_at?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}</td>
                        <td>{{ $invoice->status === 'paid' ? $invoice->amountPaidFormatted() : $invoice->amountDueFormatted() }}</td>
                        <td><span class="badge">{{ $invoice->status }}</span></td>
                        <td>
                            @if ($invoice->invoice_pdf || $invoice->hosted_invoice_url)
                                <a class="btn btn-outline" href="{{ route('portal.invoices.download', $invoice) }}">PDF / view</a>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div>{{ $invoices->onEachSide(1)->links() }}</div>
@endif
@endsection
