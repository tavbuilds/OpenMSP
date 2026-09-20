<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesIndexQuery;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAgentApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CompanyController extends Controller
{
    use AppliesIndexQuery;
    use AuthorizesAgentApi;

    /** @var list<string> */
    protected array $sortable = [
        'id',
        'name',
        'city',
        'country',
        'email',
        'created_at',
        'updated_at',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRead($request);

        $query = Company::query()->orderBy('name');

        $this->applySearch(
            $query,
            $request,
            ['name', 'email', 'city', 'kvk_number', 'vat_number', 'phone', 'notes'],
        );

        if ($city = $request->string('city')->trim()->toString()) {
            $query->whereRaw('LOWER(city) LIKE ?', ['%'.mb_strtolower($city).'%']);
        }

        if ($country = $request->string('country')->trim()->toString()) {
            $query->where('country', $country);
        }

        $this->applySort($query, $request, $this->sortable);

        return CompanyResource::collection(
            $query->paginate($this->perPage($request))->withQueryString()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'kvk_number' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $company = Company::create($data);

        return (new CompanyResource($company))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Company $company): CompanyResource
    {
        $this->authorizeRead($request);

        return new CompanyResource($company->loadCount(['contacts', 'contracts']));
    }

    public function update(Request $request, Company $company): CompanyResource
    {
        $this->authorizeWrite($request);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'kvk_number' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $company->update($data);

        return new CompanyResource($company->fresh());
    }

    public function destroy(Request $request, Company $company): Response
    {
        $this->authorizeDelete($request);

        $company->delete();

        return response()->noContent();
    }
}
