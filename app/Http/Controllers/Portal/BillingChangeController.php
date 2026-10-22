<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ContractBillingChange;
use App\Support\ContractMoves;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The customer answering a change to their contract.
 *
 * Their approval is what carries the change out: the service is not in
 * the contract, or out of it, until they say so here. Declining leaves
 * everything exactly as it was — there is nothing to undo, because
 * nothing was done.
 */
class BillingChangeController extends Controller
{
    public function confirm(Request $request, ContractBillingChange $change): RedirectResponse
    {
        $this->assertTheirs($change);

        if (! $change->isPending()) {
            return back();
        }

        try {
            $applied = DB::transaction(fn () => ContractMoves::apply($change));
        } catch (Throwable $e) {
            Log::warning('Approved contract change could not be applied', [
                'change_id' => $change->id,
                'error' => $e->getMessage(),
            ]);

            $applied = false;
        }

        if (! $applied) {
            // Either the contracts moved on underneath this request or the
            // amount is no longer the one they are approving. Leaving it
            // pending is the safe end: nothing has changed, and it is
            // still on screen for whoever has to sort it out.
            return back()->withErrors([
                'billing' => __('We could not put this change through. Nothing has changed and we will look into it.'),
            ]);
        }

        $change->forceFill(['status' => 'confirmed', 'confirmed_at' => now()])->save();

        return back()->with('status', $change->isAddition()
            ? __('Thank you. :service is part of your contract, and from the next period we will collect :amount.', [
                'service' => $change->service_name,
                'amount' => $change->newFormatted(),
            ])
            : __('Thank you. :service has been taken out, and from the next period we will collect :amount.', [
                'service' => $change->service_name,
                'amount' => $change->newFormatted(),
            ]));
    }

    public function decline(Request $request, ContractBillingChange $change): RedirectResponse
    {
        $this->assertTheirs($change);

        if (! $change->isPending()) {
            return back();
        }

        // Nothing to undo and nothing to tell Stripe: the change was never
        // made. Their contract and their direct debit are as they were.
        $change->forceFill(['status' => 'declined', 'declined_at' => now()])->save();

        return back()->with('status', $change->isAddition()
            ? __(':service has not been added and your direct debit is unchanged. Let us know if you would like it after all.', [
                'service' => $change->service_name,
            ])
            : __(':service stays in your contract and your direct debit is unchanged.', [
                'service' => $change->service_name,
            ]));
    }

    private function assertTheirs(ContractBillingChange $change): void
    {
        $contact = Auth::guard('portal')->user();

        abort_unless((int) $change->company_id === (int) $contact->company_id, 404);
    }
}
