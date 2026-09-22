<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Api\V1\Concerns\AppliesIndexQuery;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAgentApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ContractResource;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ContractController extends Controller
{
    use AppliesIndexQuery;
    use AuthorizesAgentApi;

    /** @var list<string> */
    protected array $sortable = [
        'id',
        'name',
        'reference',
        'status',
        'type',
        'billing_cycle',
        'sale_price',
        'cost_price',
        'quantity',
        'start_date',
        'renewal_date',
        'cancelled_at',
        'next_invoice_date',
        'company_id',
        'product_id',
        'vendor_id',
        'auto_renew',
        'created_at',
        'updated_at',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRead($request);

        $query = Contract::query()
            ->with(['company', 'product', 'vendor'])
            ->orderByDesc('id');

        $this->applySearch(
            $query,
            $request,
            ['name', 'reference', 'notes'],
            [
                'company' => ['name'],
                'product' => ['name', 'sku'],
                'vendor' => ['name'],
            ],
        );

        $this->applyIntegerEquals($query, $request, 'company_id');
        $this->applyIntegerEquals($query, $request, 'product_id');
        $this->applyIntegerEquals($query, $request, 'vendor_id');
        $this->applyIntegerEquals($query, $request, 'purchase_bundle_id');

        $this->applyStringEquals($query, $request, 'status');
        $this->applyStringEquals($query, $request, 'type');
        $this->applyStringEquals($query, $request, 'billing_cycle');

        $this->applyBooleanFilter($query, $request, 'auto_renew');

        // Filament "Verkoopbedrag" filters unit sale_price.
        $this->applyNumericRange($query, $request, 'sale_price', 'sale_min', 'sale_max');
        $this->applyNumericRange($query, $request, 'cost_price', 'cost_min', 'cost_max');

        // Approximate margin (qty * sale - qty * cost). Bundle-allocated cost is not reflected.
        if ($request->filled('margin_min')) {
            $query->whereRaw(
                'CAST((quantity * COALESCE(sale_price, 0)) - (quantity * COALESCE(cost_price, 0)) AS REAL) >= CAST(? AS REAL)',
                [$request->input('margin_min')],
            );
        }
        if ($request->filled('margin_max')) {
            $query->whereRaw(
                'CAST((quantity * COALESCE(sale_price, 0)) - (quantity * COALESCE(cost_price, 0)) AS REAL) <= CAST(? AS REAL)',
                [$request->input('margin_max')],
            );
        }

        $this->applyDateRange($query, $request, 'start_date', 'starts_after', 'starts_before');
        $this->applyDateRange($query, $request, 'renewal_date', 'renews_after', 'renews_before');
        $this->applyDateRange($query, $request, 'cancelled_at', 'cancels_after', 'cancels_before');
        $this->applyDateRange($query, $request, 'next_invoice_date', 'invoices_after', 'invoices_before');

        $this->applySort($query, $request, $this->sortable);

        return ContractResource::collection(
            $query->paginate($this->perPage($request))->withQueryString()
        );
    }

    /**
     * Active contracts renewing within the next N days (default 30).
     * Prefer this over scraping Filament UpcomingRenewals.
     */
    public function upcomingRenewals(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRead($request);

        $days = $request->integer('days', 30);
        $days = max(1, min($days > 0 ? $days : 30, 365));

        $query = Contract::query()
            ->with(['company', 'product', 'vendor'])
            ->withUpcomingRenewalTerm()
            ->whereBetween('renewal_date', [now()->startOfDay(), now()->addDays($days)->endOfDay()])
            ->orderBy('renewal_date');

        return ContractResource::collection(
            $query->paginate($this->perPage($request))->withQueryString()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);

        $data = $this->validated($request);
        $contract = Contract::create($data);

        return (new ContractResource($contract->load(['company', 'product', 'vendor'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Contract $contract): ContractResource
    {
        $this->authorizeRead($request);

        return new ContractResource($contract->load(['company', 'product', 'vendor', 'purchaseBundle']));
    }

    public function update(Request $request, Contract $contract): ContractResource
    {
        $this->authorizeWrite($request);

        $data = $this->validated($request, updating: true);
        $contract->update($data);

        return new ContractResource($contract->fresh()->load(['company', 'product', 'vendor']));
    }

    public function destroy(Request $request, Contract $contract): Response
    {
        $this->authorizeDelete($request);

        $contract->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, bool $updating = false): array
    {
        $nameRule = $updating ? ['sometimes', 'required', 'string', 'max:255'] : ['required', 'string', 'max:255'];
        $companyRule = $updating
            ? ['sometimes', 'required', 'integer', 'exists:companies,id']
            : ['required', 'integer', 'exists:companies,id'];
        $startRule = $updating ? ['sometimes', 'required', 'date'] : ['required', 'date'];

        return $request->validate([
            'company_id' => $companyRule,
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'purchase_bundle_id' => ['nullable', 'integer', 'exists:purchase_bundles,id'],
            'name' => $nameRule,
            'reference' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(ProductType::class)],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'billing_cycle' => ['nullable', Rule::enum(BillingCycle::class)],
            'start_date' => $startRule,
            'renewal_date' => ['nullable', 'date'],
            'notice_period_days' => ['nullable', 'integer', 'min:0'],
            'auto_renew' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::enum(ContractStatus::class)],
            'next_invoice_date' => ['nullable', 'date'],
            'cancelled_at' => ['nullable', 'date'],
            // license_keys stay encrypted via model cast; never logged in cleartext (Auditable redacts).
            'license_keys' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
