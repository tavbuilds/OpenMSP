@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\ContractBillingChange> $changes */
@endphp

@foreach ($changes as $change)
    @php $dialogId = 'billing-change-'.$change->getKey(); @endphp

    {{-- Shown wherever the customer lands: this is the step the change is
         waiting on, and nothing happens until they take it. --}}
    <div class="card omsp-notice">
        <h2>
            {{ $change->isAddition()
                ? __('Approve an addition to your contract')
                : __('Approve a change to your contract') }}
        </h2>

        <p>{{ $change->explanation() }}</p>

        <p>
            <strong>{{ $change->previousFormatted() }}</strong>
            &rarr;
            <strong>{{ $change->newFormatted() }}</strong>
            <span class="muted">{{ __('per period') }}</span>
        </p>

        <p class="muted">{{ $change->consequence() }}</p>

        <div class="stack-actions">
            <button type="button" class="btn"
                    onclick="document.getElementById('{{ $dialogId }}').showModal()">
                {{ __('Review the change') }}
            </button>
        </div>
    </div>

    <dialog id="{{ $dialogId }}" class="omsp-dialog">
        <h2>
            {{ $change->isAddition()
                ? __('Add :service for :amount per period?', ['service' => $change->service_name, 'amount' => $change->newFormatted()])
                : __('Take :service out and pay :amount per period?', ['service' => $change->service_name, 'amount' => $change->newFormatted()]) }}
        </h2>

        <p>{{ $change->explanation() }}</p>
        <p>
            {{ $change->isAddition()
                ? __('Approving adds it to :contract and takes your direct debit from :previous to :new, starting from the next period.', [
                    'contract' => $change->contract?->name,
                    'previous' => $change->previousFormatted(),
                    'new' => $change->newFormatted(),
                ])
                : __('Approving takes it out of :contract and takes your direct debit from :previous to :new, starting from the next period.', [
                    'contract' => $change->contract?->name,
                    'previous' => $change->previousFormatted(),
                    'new' => $change->newFormatted(),
                ]) }}
        </p>
        <p>{{ $change->consequence() }}</p>
        <p class="muted">{{ __('Your existing mandate is used; you do not have to authorise anything again.') }}</p>

        <div class="stack-actions">
            <form method="POST" action="{{ route('portal.billing-changes.confirm', $change) }}">
                @csrf
                <button type="submit" class="btn">
                    {{ $change->isAddition() ? __('Approve and add it') : __('Approve the change') }}
                </button>
            </form>

            <form method="POST" action="{{ route('portal.billing-changes.decline', $change) }}">
                @csrf
                <button type="submit" class="btn btn-quiet">{{ __('No, leave it as it is') }}</button>
            </form>

            <button type="button" class="btn btn-quiet"
                    onclick="document.getElementById('{{ $dialogId }}').close()">
                {{ __('Decide later') }}
            </button>
        </div>
    </dialog>
@endforeach
