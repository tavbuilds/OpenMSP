@extends('portal.layouts.app')

@section('title', __('Invoices'))

@section('content')
<h1>{{ __('Invoices') }}</h1>
<p class="muted lede">{{ __(':company — open an invoice to view or download the PDF.', ['company' => $company?->name]) }}</p>

@if ($invoices->isEmpty())
    <div class="card"><p class="muted empty">{{ __('No invoices yet.') }}</p></div>
@else
    {{-- Phone and small tablet: one card per invoice. --}}
    <div class="stack">
        @foreach ($invoices as $invoice)
            <article class="card stack-card">
                <h2>{{ $invoice->displayNumber() }}</h2>

                <dl class="stack-meta">
                    <div>
                        <dt>{{ __('Date') }}</dt>
                        <dd>{{ $invoice->displayDate() }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Amount') }}</dt>
                        <dd><strong>{{ $invoice->displayAmountFormatted() }}</strong></dd>
                    </div>
                    <div>
                        <dt>{{ __('Status') }}</dt>
                        <dd><span class="badge badge-{{ $invoice->statusTone() }}">{{ $invoice->statusLabel() }}</span></dd>
                    </div>
                </dl>

                <div class="stack-actions">
                    @if ($invoice->invoice_pdf || $invoice->hosted_invoice_url)
                        <a class="btn btn-outline" href="{{ route('portal.invoices.download', $invoice) }}">{{ __('View PDF') }}</a>
                    @else
                        <span class="muted cell-sub">{{ __('No PDF available yet') }}</span>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    {{-- Tablet and up: the same invoices as a table. --}}
    <div class="data-table">
        <div class="card card-flush">
            <table>
                <thead>
                    <tr>
                        <th scope="col">{{ __('Number') }}</th>
                        <th scope="col">{{ __('Date') }}</th>
                        <th scope="col" class="num">{{ __('Amount') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice->displayNumber() }}</td>
                        <td>{{ $invoice->displayDate() }}</td>
                        <td class="num">{{ $invoice->displayAmountFormatted() }}</td>
                        <td><span class="badge badge-{{ $invoice->statusTone() }}">{{ $invoice->statusLabel() }}</span></td>
                        <td class="num">
                            @if ($invoice->invoice_pdf || $invoice->hosted_invoice_url)
                                <a class="btn btn-outline btn-sm" href="{{ route('portal.invoices.download', $invoice) }}">{{ __('View PDF') }}</a>
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

    {{ $invoices->onEachSide(1)->links('portal.pagination') }}
@endif
@endsection
