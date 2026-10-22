@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\ContractBillingChange> $changes */
@endphp

@foreach ($changes as $change)
    @php $dialogId = 'billing-change-'.$change->getKey(); @endphp

    {{-- Shown wherever the customer lands, because an unanswered change
         means we are still collecting the old amount. --}}
    <div class="card omsp-notice">
        <h2>{{ __('Approve a change to your direct debit') }}</h2>

        <p>{{ $change->explanation() }}</p>

        <p>
            <strong>{{ $change->previousFormatted() }}</strong>
            &rarr;
            <strong>{{ $change->newFormatted() }}</strong>
            <span class="muted">{{ __('per period') }}</span>
        </p>

        <p class="muted">
            {{ __('Until you approve it we keep collecting :previous. Your mandate stays as it is — there is nothing to re-authorise.', [
                'previous' => $change->previousFormatted(),
            ]) }}
        </p>

        <div class="stack-actions">
            <button type="button" class="btn"
                    onclick="document.getElementById('{{ $dialogId }}').showModal()">
                {{ __('Review the change') }}
            </button>
        </div>
    </div>

    <dialog id="{{ $dialogId }}" class="omsp-dialog">
        <h2>{{ __('Approve :amount per period?', ['amount' => $change->newFormatted()]) }}</h2>

        <p>{{ $change->explanation() }}</p>
        <p>
            {{ __('Your direct debit for :contract goes from :previous to :new, starting from the next period.', [
                'contract' => $change->contract?->name,
                'previous' => $change->previousFormatted(),
                'new' => $change->newFormatted(),
            ]) }}
        </p>
        <p class="muted">{{ __('Your existing mandate is used; you do not have to authorise anything again.') }}</p>

        <div class="stack-actions">
            <form method="POST" action="{{ route('portal.billing-changes.confirm', $change) }}">
                @csrf
                <button type="submit" class="btn">{{ __('Approve the new amount') }}</button>
            </form>

            <form method="POST" action="{{ route('portal.billing-changes.decline', $change) }}">
                @csrf
                <button type="submit" class="btn btn-quiet">{{ __('Stop the direct debit instead') }}</button>
            </form>

            <button type="button" class="btn btn-quiet"
                    onclick="document.getElementById('{{ $dialogId }}').close()">
                {{ __('Decide later') }}
            </button>
        </div>
    </dialog>
@endforeach
