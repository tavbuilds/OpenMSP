@php
    /** @var \App\Models\Contract $contract */
    $cancellation = \App\Support\ContractCancellation::for($contract);
    $dialogId = 'cancel-'.$contract->getKey();
@endphp

@if ($cancellation->isCancellable())
    @if ($cancellation->customerMayCancel())
        {{-- A native dialog: no framework on this side of the app, and the
             browser handles focus and Escape better than we would. --}}
        <button type="button" class="btn btn-sm btn-quiet"
                onclick="document.getElementById('{{ $dialogId }}').showModal()">
            {{ $label ?? __('Cancel this service') }}
        </button>

        <dialog id="{{ $dialogId }}" class="omsp-dialog">
            <form method="POST" action="{{ route('portal.contracts.cancel', $contract) }}">
                @csrf
                {{-- Sent back so a page left open overnight cannot cancel
                     on dates that have since moved. --}}
                <input type="hidden" name="confirmed_ends_on" value="{{ $cancellation->endsOn()?->toDateString() }}">

                <h2>{{ __('Cancel :name?', ['name' => $contract->name]) }}</h2>

                <p>{{ $cancellation->summaryForCustomer() }}</p>

                @if ($contract->isGroup() && $contract->services->isNotEmpty())
                    <p class="muted">
                        {{ __('This ends everything in this contract: :names.', [
                            'names' => $contract->services->pluck('name')->join(', '),
                        ]) }}
                    </p>
                @endif

                @if ($contract->auto_collect)
                    <p class="muted">{{ __('The direct debit for it stops as well.') }}</p>
                @endif

                <div class="stack-actions">
                    <button type="submit" class="btn btn-danger">{{ __('Yes, cancel it') }}</button>
                    <button type="button" class="btn btn-quiet"
                            onclick="document.getElementById('{{ $dialogId }}').close()">
                        {{ __('Keep it') }}
                    </button>
                </div>
            </form>
        </dialog>
    @else
        <span class="muted cell-sub">{{ $cancellation->summaryForCustomer() }}</span>
    @endif
@endif
