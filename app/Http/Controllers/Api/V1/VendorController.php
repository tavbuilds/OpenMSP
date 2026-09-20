<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesIndexQuery;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAgentApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VendorResource;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class VendorController extends Controller
{
    use AppliesIndexQuery;
    use AuthorizesAgentApi;

    /** @var list<string> */
    protected array $sortable = [
        'id',
        'name',
        'email',
        'website',
        'created_at',
        'updated_at',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRead($request);

        $query = Vendor::query()->orderBy('name');

        $this->applySearch(
            $query,
            $request,
            ['name', 'email', 'website', 'phone', 'notes'],
        );

        $this->applySort($query, $request, $this->sortable);

        return VendorResource::collection(
            $query->paginate($this->perPage($request))->withQueryString()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $vendor = Vendor::create($data);

        return (new VendorResource($vendor))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Vendor $vendor): VendorResource
    {
        $this->authorizeRead($request);

        return new VendorResource($vendor->loadCount(['products', 'contracts']));
    }

    public function update(Request $request, Vendor $vendor): VendorResource
    {
        $this->authorizeWrite($request);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $vendor->update($data);

        return new VendorResource($vendor->fresh());
    }

    public function destroy(Request $request, Vendor $vendor): Response
    {
        $this->authorizeDelete($request);

        $vendor->delete();

        return response()->noContent();
    }
}
