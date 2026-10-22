<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ContractBillingChange;
use App\Services\StripeBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The customer answering a change to what their direct debit collects.
 *
 * Approving re-prices the existing subscription: the mandate was given to
 * us rather than to an amount, so nothing is re-authorised and the
 * collection carries on. Declining stops it instead, and they go back to
 * invoices they pay by hand.
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
            app(StripeBillingService::class)->updateSubscriptionAmount($change->contract);
        } catch (Throwable $e) {
            Log::warning('Stripe subscription re-price failed', [
                'change_id' => $change->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'billing' => __('We could not update the direct debit just now. Nothing has changed and we will look into it.'),
            ]);
        }

        $change->forceFill(['status' => 'confirmed', 'confirmed_at' => now()])->save();

        return back()->with('status', __('Thank you. From the next period we will collect :amount.', [
            'amount' => $change->newFormatted(),
        ]));
    }

    public function decline(Request $request, ContractBillingChange $change): RedirectResponse
    {
        $this->assertTheirs($change);

        if (! $change->isPending()) {
            return back();
        }

        // They did not agree to the new amount, so we stop collecting
        // rather than keep taking one they no longer want to pay.
        $contract = $change->contract;

        try {
            app(StripeBillingService::class)->cancelSubscription($contract);
        } catch (Throwable $e) {
            Log::warning('Stripe subscription cancel failed after a declined change', [
                'change_id' => $change->id,
                'error' => $e->getMessage(),
            ]);
        }

        // That call keeps its failures to the log, so read the result
        // rather than the return: telling someone their direct debit has
        // stopped while it is still running is the one outcome we cannot
        // have here.
        if ($contract?->fresh()?->collectsByDirectDebit()) {
            return back()->withErrors([
                'billing' => __('We could not stop the direct debit just now. Nothing has changed and we will look into it.'),
            ]);
        }

        $change->forceFill(['status' => 'declined', 'declined_at' => now()])->save();

        return back()->with('status', __('The direct debit has been stopped. You will receive an invoice to pay yourself, and you can set up a new direct debit whenever you like.'));
    }

    private function assertTheirs(ContractBillingChange $change): void
    {
        $contact = Auth::guard('portal')->user();

        abort_unless((int) $change->company_id === (int) $contact->company_id, 404);
    }
}
