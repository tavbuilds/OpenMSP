<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Support\ContractCancellation;
use App\Support\ContractTermination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A customer cancelling their own contract.
 *
 * What they may cancel: a service of their own, a whole collection, or one
 * service inside a collection. What they may not: anything else about it.
 * Changing a quantity is a conversation, not a button.
 */
class CancellationController extends Controller
{
    public function __invoke(Request $request, Contract $contract): RedirectResponse
    {
        $contact = Auth::guard('portal')->user();

        abort_unless($contract->company_id === $contact->company_id, 404);

        $cancellation = ContractCancellation::for($contract);

        // Inside the notice window on a yearly term the renewal is already
        // committed, so this is a conversation rather than a button.
        if (! $cancellation->customerMayCancel()) {
            return back()->withErrors([
                'contract' => $cancellation->isInsideNoticeWindow()
                    ? $cancellation->summaryForCustomer()
                    : __('This contract cannot be cancelled here. Contact support and we will help you.'),
            ]);
        }

        // The confirmation screen showed these dates; the form sends them
        // back so a stale page cannot cancel on terms nobody agreed to.
        if ($request->input('confirmed_ends_on') !== $cancellation->endsOn()?->toDateString()) {
            return back()->withErrors([
                'contract' => __('The dates changed while you were reading. Check them and confirm again.'),
            ]);
        }

        ContractTermination::cancel($contract, by: 'customer');

        return redirect()
            ->route('portal.dashboard')
            ->with('status', __('Cancelled. :summary', [
                'summary' => $cancellation->summaryForCustomer(),
            ]));
    }
}
