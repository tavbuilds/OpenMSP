<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ContractStatus;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\StripeBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AutoCollectController extends Controller
{
    public function __invoke(Request $request, Contract $contract, StripeBillingService $stripe): RedirectResponse
    {
        $contact = Auth::guard('portal')->user();

        abort_unless(
            (int) $contract->company_id === (int) $contact->company_id,
            404
        );

        abort_unless($contract->status === ContractStatus::Active, 403);

        // A service inside a collection is billed through that collection.
        // Two mandates for one agreement would debit the customer twice.
        if ($contract->isInGroup()) {
            return redirect()
                ->route('portal.contracts.show', $contract->parent)
                ->withErrors(['stripe' => __('This service is part of :contract. Set up the direct debit on that contract instead.', [
                    'contract' => $contract->parent?->name,
                ])]);
        }

        if ($contract->auto_collect && $contract->stripe_payment_status === 'active') {
            return redirect()
                ->route('portal.dashboard')
                ->with('status', 'Auto-collect is already enabled for this service.');
        }

        try {
            $url = $stripe->createAutoCollectCheckout(
                $contract,
                route('portal.auto-collect.success', ['contract' => $contract->id]).'?session_id={CHECKOUT_SESSION_ID}',
                route('portal.dashboard'),
            );
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('portal.dashboard')
                ->withErrors(['stripe' => 'Could not start Stripe Checkout. Try again later or contact us.']);
        }

        return redirect()->away($url);
    }

    public function success(Request $request, Contract $contract): RedirectResponse
    {
        $contact = Auth::guard('portal')->user();
        abort_unless((int) $contract->company_id === (int) $contact->company_id, 404);

        return redirect()
            ->route('portal.dashboard')
            ->with('status', 'Thanks. Auto-collect turns on once the iDEAL payment and SEPA mandate are confirmed. This can take a moment.');
    }
}
