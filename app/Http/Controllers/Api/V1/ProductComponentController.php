<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesIndexQuery;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAgentApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductComponentResource;
use App\Models\Product;
use App\Models\ProductComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ProductComponentController extends Controller
{
    use AppliesIndexQuery;
    use AuthorizesAgentApi;

    /** @var list<string> */
    protected array $sortable = [
        'id',
        'product_id',
        'component_id',
        'quantity',
        'created_at',
        'updated_at',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRead($request);

        $query = ProductComponent::query()
            ->with(['product', 'component'])
            ->orderBy('id');

        $this->applyIntegerEquals($query, $request, 'product_id');
        $this->applyIntegerEquals($query, $request, 'component_id');
        $this->applySort($query, $request, $this->sortable);

        return ProductComponentResource::collection(
            $query->paginate($this->perPage($request))->withQueryString()
        );
    }

    public function indexByProduct(Request $request, Product $product): AnonymousResourceCollection
    {
        $request->merge(['product_id' => $product->id]);

        return $this->index($request);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);

        $data = $this->validated($request);
        $link = ProductComponent::create($data);

        return (new ProductComponentResource($link->load(['product', 'component'])))
            ->response()
            ->setStatusCode(201);
    }

    public function storeByProduct(Request $request, Product $product): JsonResponse
    {
        $request->merge(['product_id' => $product->id]);

        return $this->store($request);
    }

    public function show(Request $request, ProductComponent $productComponent): ProductComponentResource
    {
        $this->authorizeRead($request);

        return new ProductComponentResource($productComponent->load(['product', 'component']));
    }

    public function update(Request $request, ProductComponent $productComponent): ProductComponentResource
    {
        $this->authorizeWrite($request);

        $data = $this->validated($request, updating: true, existing: $productComponent);
        $productComponent->update($data);

        return new ProductComponentResource($productComponent->fresh()->load(['product', 'component']));
    }

    public function destroy(Request $request, ProductComponent $productComponent): Response
    {
        $this->authorizeDelete($request);

        $productComponent->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, bool $updating = false, ?ProductComponent $existing = null): array
    {
        $productRule = $updating
            ? ['sometimes', 'required', 'integer', 'exists:products,id']
            : ['required', 'integer', 'exists:products,id'];
        $componentRule = $updating
            ? ['sometimes', 'required', 'integer', 'exists:products,id']
            : ['required', 'integer', 'exists:products,id'];

        $productId = $request->input('product_id', $existing?->product_id);
        $componentId = $request->input('component_id', $existing?->component_id);

        $unique = Rule::unique('product_components', 'component_id')
            ->where(fn ($q) => $q->where('product_id', $productId));

        if ($existing) {
            $unique = $unique->ignore($existing->id);
        }

        $data = $request->validate([
            'product_id' => $productRule,
            'component_id' => array_merge($componentRule, [
                Rule::notIn([(int) $productId]),
                $unique,
            ]),
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! isset($data['quantity'])) {
            if (! $updating) {
                $data['quantity'] = 1;
            }
        }

        $finalProduct = (int) ($data['product_id'] ?? $existing?->product_id);
        $finalComponent = (int) ($data['component_id'] ?? $existing?->component_id);
        if ($finalProduct > 0 && $finalProduct === $finalComponent) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'component_id' => ['A product cannot be a component of itself.'],
            ]);
        }

        return $data;
    }
}
