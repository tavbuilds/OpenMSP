<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The customer's own domains. No cost, no margin, and a note only when it was
 * deliberately shared.
 */
class DomainController extends Controller
{
    public function __invoke(Request $request): View
    {
        $contact = Auth::guard('portal')->user();

        // A domain the registrar let go is not one the customer still has.
        $domains = Domain::query()
            ->where('company_id', $contact->company_id)
            ->present()
            ->orderByDue()
            ->orderBy('name')
            ->get();

        return view('portal.domains', [
            'contact' => $contact,
            'company' => $contact->company,
            'domains' => $domains,
        ]);
    }
}
