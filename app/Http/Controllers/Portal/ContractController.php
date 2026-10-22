<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ContractStatus;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractBillingChange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * What is inside one of the customer's contracts.
 *
 * Only a collection has a page of its own; a single service is already a
 * line on the dashboard and has nothing further to show.
 */
class ContractController extends Controller
{
    public function __invoke(Request $request, Contract $contract): View
    {
        $contact = Auth::guard('portal')->user();

        abort_unless($contract->company_id === $contact->company_id, 404);
        abort_unless($contract->isGroup(), 404);

        $contract->load(['services' => fn ($q) => $q
            ->where('status', ContractStatus::Active->value)
            ->orderBy('name')]);

        $billingChanges = ContractBillingChange::query()
            ->with('contract')
            ->where('contract_id', $contract->getKey())
            ->pending()
            ->latest('id')
            ->get();

        return view('portal.contract', [
            'contact' => $contact,
            'company' => $contact->company,
            'contract' => $contract,
            'billingChanges' => $billingChanges,
        ]);
    }
}
