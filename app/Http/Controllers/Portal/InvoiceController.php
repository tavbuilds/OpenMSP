<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $contact = Auth::guard('portal')->user();

        $invoices = Invoice::query()
            ->where('company_id', $contact->company_id)
            ->orderByDesc('stripe_created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('portal.invoices.index', [
            'contact' => $contact,
            'company' => $contact->company,
            'invoices' => $invoices,
        ]);
    }

    public function download(Request $request, Invoice $invoice): RedirectResponse
    {
        $contact = Auth::guard('portal')->user();
        abort_unless((int) $invoice->company_id === (int) $contact->company_id, 404);

        $url = $invoice->invoice_pdf ?: $invoice->hosted_invoice_url;
        abort_unless(filled($url), 404);

        return redirect()->away($url);
    }
}
