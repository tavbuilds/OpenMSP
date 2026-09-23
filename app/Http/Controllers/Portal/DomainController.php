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

        $domains = Domain::query()
            ->where('company_id', $contact->company_id)
            ->orderBy('expires_at')
            ->orderBy('name')
            ->get();

        return view('portal.domains', [
            'contact' => $contact,
            'company' => $contact->company,
            'domains' => $domains,
        ]);
    }
}
