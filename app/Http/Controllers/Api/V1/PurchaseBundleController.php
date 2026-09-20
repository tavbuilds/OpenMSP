<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillingCycle;
use App\Http\Controllers\Api\V1\Concerns\AppliesIndexQuery;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAgentApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PurchaseBundleResource;
use App\Models\PurchaseBundle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class PurchaseBundleController extends Controller
{
    use AppliesIndexQuery;
    use AuthorizesAgentApi;

    /** @var list<string> */
    protected array $sortable = [
        'id',
        'name',
        'reference',
        'vendor_id',
        'total_cost',
        'currency',
        'billing_cycle',
        'allocation_method',
        'start_date',
        'renewal_date',
        'created_at',
        'updated_at',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRead($request);

        $query = PurchaseBundle::query()->with('vendor')->orderBy('name');

        $this->applySearch(
            $query,
            $request,
            ['name', 'reference', 'notes'],
            ['vendor' => ['name']],
        );

        $this->applyIntegerEquals($query, $request, 'vendor_id');
        $this->applyStringEquals($query, $request, 'billing_cycle');
        $this->applyStringEquals($query, $request, 'allocation_method');
        $this->applyStringEquals($query, $request, 'currency');

        $this->applyNumericRange($query, $request, 'total_cost', 'cost_min', 'cost_max');
        $this->applyDateRange($query, $request, 'start_date', 'starts_after', 'starts_before');
        $this->applyDateRange($query, $request, 'renewal_date', 'renews_after', 'renews_before');

        $this->applySort($query, $request, $this->sortable);

        return PurchaseBundleResource::collection(
            $query->paginate($this->perPage($request))->withQueryString()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);

        $bundle = PurchaseBundle::create($this->validated($request));

        return (new PurchaseBundleResource($bundle->load('vendor')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, PurchaseBundle $purchaseBundle): PurchaseBundleResource
    {
        $this->authorizeRead($request);

        return new PurchaseBundleResource(
            $purchaseBundle->load('vendor')->loadCount('contracts')
        );
    }

    public function update(Request $request, PurchaseBundle $purchaseBundle): PurchaseBundleResource
    {
        $this->authorizeWrite($request);

        $purchaseBundle->update($this->validated($request, updating: true));

        return new PurchaseBundleResource($purchaseBundle->fresh()->load('vendor'));
    }

    public function destroy(Request $request, PurchaseBundle $purchaseBundle): Response
    {
        $this->authorizeDelete($request);

        $purchaseBundle->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, bool $updating = false): array
    {
        $nameRule = $updating
            ? ['sometimes', 'required', 'string', 'max:255']
            : ['required', 'string', 'max:255'];

        return $request->validate([
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'name' => $nameRule,
            'reference' => ['nullable', 'string', 'max:255'],
            'total_cost' => [$updating ? 'sometimes' : 'nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'billing_cycle' => ['nullable', Rule::enum(BillingCycle::class)],
            'allocation_method' => ['nullable', 'string', Rule::in(['even'])],
            'start_date' => ['nullable', 'date'],
            'renewal_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
