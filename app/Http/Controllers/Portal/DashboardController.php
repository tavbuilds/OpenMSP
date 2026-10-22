<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ContractStatus;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $contact = Auth::guard('portal')->user();
        $companyId = $contact->company_id;

        // A contract that holds services is listed as one line, not as its
        // parts: that is how the customer was sold it.
        $services = Contract::query()
            ->where('company_id', $companyId)
            ->where('status', ContractStatus::Active->value)
            ->topLevel()
            ->with(['services' => fn ($q) => $q->where('status', ContractStatus::Active->value)])
            ->orderBy('name')
            ->get();

        return view('portal.dashboard', [
            'contact' => $contact,
            'company' => $contact->company,
            'services' => $services,
        ]);
    }
}
