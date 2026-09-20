<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesIndexQuery;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAgentApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ContactResource;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ContactController extends Controller
{
    use AppliesIndexQuery;
    use AuthorizesAgentApi;

    /** @var list<string> */
    protected array $sortable = [
        'id',
        'name',
        'email',
        'job_title',
        'is_primary',
        'company_id',
        'created_at',
        'updated_at',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRead($request);

        $query = Contact::query()
            ->with('company')
            ->orderByDesc('is_primary')
            ->orderBy('name');

        $this->applySearch(
            $query,
            $request,
            ['name', 'email', 'phone', 'job_title'],
            ['company' => ['name']],
        );

        $this->applyIntegerEquals($query, $request, 'company_id');
        $this->applyBooleanFilter($query, $request, 'is_primary');
        $this->applySort($query, $request, $this->sortable);

        return ContactResource::collection(
            $query->paginate($this->perPage($request))->withQueryString()
        );
    }

    public function indexByCompany(Request $request, Company $company): AnonymousResourceCollection
    {
        $request->merge(['company_id' => $company->id]);

        return $this->index($request);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);

        $data = $this->validated($request);
        $contact = Contact::create($data);

        return (new ContactResource($contact->load('company')))
            ->response()
            ->setStatusCode(201);
    }

    public function storeByCompany(Request $request, Company $company): JsonResponse
    {
        $request->merge(['company_id' => $company->id]);

        return $this->store($request);
    }

    public function show(Request $request, Contact $contact): ContactResource
    {
        $this->authorizeRead($request);

        return new ContactResource($contact->load('company'));
    }

    public function update(Request $request, Contact $contact): ContactResource
    {
        $this->authorizeWrite($request);

        $data = $this->validated($request, updating: true);
        $contact->update($data);

        return new ContactResource($contact->fresh()->load('company'));
    }

    public function destroy(Request $request, Contact $contact): Response
    {
        $this->authorizeDelete($request);

        $contact->delete();

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

        return $request->validate([
            'company_id' => $companyRule,
            'name' => $nameRule,
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
        ]);
    }
}
