@extends('portal.layouts.app')

@section('title', __('Domains'))

@section('content')
<h1>{{ __('Domains') }}</h1>
<p class="muted lede">{{ __(':company — the domain names we manage for you.', ['company' => $company?->name]) }}</p>

@if ($domains->isEmpty())
    <div class="card"><p class="muted empty">{{ __('There are no domains registered for you right now.') }}</p></div>
@else
    {{-- Phone and small tablet: one card per domain. --}}
    <div class="stack">
        @foreach ($domains as $domain)
            <article class="card stack-card">
                <h2>{{ $domain->name }}</h2>

                <dl class="stack-meta">
                    <div>
                        <dt>{{ __('Expires') }}</dt>
                        <dd><strong>{{ $domain->expires_at?->translatedFormat('M j, Y') ?? __('—') }}</strong></dd>
                    </div>
                    <div>
                        <dt>{{ __('Auto-renew') }}</dt>
                        <dd>{{ $domain->auto_renew ? __('On') : __('Off') }}</dd>
                    </div>
                </dl>

                @if ($note = $domain->customerVisibleNotes())
                    <p class="muted cell-sub">{{ $note }}</p>
                @endif
            </article>
        @endforeach
    </div>

    {{-- Tablet and up: the same domains as a table. --}}
    <div class="data-table">
        <div class="card card-flush">
            <table>
                <thead>
                    <tr>
                        <th scope="col">{{ __('Domain name') }}</th>
                        <th scope="col">{{ __('Expires') }}</th>
                        <th scope="col">{{ __('Auto-renew') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($domains as $domain)
                    <tr>
                        <td>
                            <strong>{{ $domain->name }}</strong>
                            @if ($note = $domain->customerVisibleNotes())
                                <div class="muted cell-sub">{{ $note }}</div>
                            @endif
                        </td>
                        <td>{{ $domain->expires_at?->translatedFormat('M j, Y') ?? __('—') }}</td>
                        <td>
                            @if ($domain->auto_renew)
                                <span class="badge badge-ok">{{ __('On') }}</span>
                            @else
                                <span class="badge">{{ __('Off') }}</span>
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
