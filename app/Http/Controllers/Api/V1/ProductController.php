<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillingCycle;
use App\Enums\ProductType;
use App\Http\Controllers\Api\V1\Concerns\AppliesIndexQuery;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAgentApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use AppliesIndexQuery;
    use AuthorizesAgentApi;

    /** @var list<string> */
    protected array $sortable = [
        'id',
        'name',
        'sku',
        'type',
        'vendor_id',
        'active',
        'default_cost_price',
        'default_sale_price',
        'billing_cycle',
        'created_at',
        'updated_at',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRead($request);

        $query = Product::query()->with('vendor')->orderBy('name');

        $this->applySearch(
            $query,
            $request,
            ['name', 'sku', 'description'],
            ['vendor' => ['name']],
        );

        $this->applyIntegerEquals($query, $request, 'vendor_id');
        $this->applyStringEquals($query, $request, 'type');
        $this->applyStringEquals($query, $request, 'billing_cycle');
        $this->applyBooleanFilter($query, $request, 'active');

        $this->applyNumericRange($query, $request, 'default_sale_price', 'sale_min', 'sale_max');
        $this->applyNumericRange($query, $request, 'default_cost_price', 'cost_min', 'cost_max');

        $this->applySort($query, $request, $this->sortable);

        return ProductResource::collection(
            $query->paginate($this->perPage($request))->withQueryString()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);

        $data = $request->validate([
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(ProductType::class)],
            'default_cost_price' => ['nullable', 'numeric', 'min:0'],
            'default_sale_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'billing_cycle' => ['nullable', Rule::enum(BillingCycle::class)],
            'description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        $product = Product::create($data);

        return (new ProductResource($product->load('vendor')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Product $product): ProductResource
    {
        $this->authorizeRead($request);

        return new ProductResource($product->load('vendor')->loadCount('contracts'));
    }

    public function update(Request $request, Product $product): ProductResource
    {
        $this->authorizeWrite($request);

        $data = $request->validate([
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(ProductType::class)],
            'default_cost_price' => ['nullable', 'numeric', 'min:0'],
            'default_sale_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'billing_cycle' => ['nullable', Rule::enum(BillingCycle::class)],
            'description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);

        $product->update($data);

        return new ProductResource($product->fresh()->load('vendor'));
    }

    public function destroy(Request $request, Product $product): Response
    {
        $this->authorizeDelete($request);

        $product->delete();

        return response()->noContent();
    }
}
