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

        $services = Contract::query()
            ->where('company_id', $companyId)
            ->where('status', ContractStatus::Active->value)
            ->orderBy('name')
            ->get();

        return view('portal.dashboard', [
            'contact' => $contact,
            'company' => $contact->company,
            'services' => $services,
        ]);
    }
}
