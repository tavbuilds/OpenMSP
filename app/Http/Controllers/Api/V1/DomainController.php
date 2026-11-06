<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DomainSource;
use App\Http\Controllers\Api\V1\Concerns\AppliesIndexQuery;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAgentApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DomainResource;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DomainController extends Controller
{
    use AppliesIndexQuery;
    use AuthorizesAgentApi;

    /** @var list<string> */
    protected array $sortable = [
        'id',
        'name',
        'extension',
        'expires_at',
        'renewal_date',
        'status',
        'auto_renew',
        'company_id',
        'source',
        'removed_at',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    private array $registrarOwned = [
        'name',
        'extension',
        'expires_at',
        'renewal_date',
        'auto_renew',
        'status',
        'product_id',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRead($request);

        $query = Domain::query()
            ->with(['company', 'product'])
            ->orderByDue();

        $this->applySearch($query, $request, ['name', 'extension', 'notes', 'status'], [
            'company' => ['name'],
        ]);
        $this->applyIntegerEquals($query, $request, 'company_id');
        $this->applyStringEquals($query, $request, 'source');
        $this->applyStringEquals($query, $request, 'extension');
        $this->applyBooleanFilter($query, $request, 'auto_renew');
        $this->applySort($query, $request, $this->sortable);
        $this->applyLifecycle($query, $request);
        $this->applyExpiryFilters($query, $request);

        if ($request->boolean('unassigned')) {
            $query->whereNull('company_id');
        }

        return DomainResource::collection(
            $query->paginate($this->perPage($request))->withQueryString()
        );
    }

    public function upcoming(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRead($request);

        $days = min(max($request->integer('days', 60), 1), 365);

        $domains = Domain::query()
            ->with(['company', 'product'])
            ->present()
            ->expiringWithin($days)
            ->orderByDue()
            ->paginate($this->perPage($request))
            ->withQueryString();

        return DomainResource::collection($domains);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);

        $domain = Domain::create($this->validated($request, creating: true));

        return (new DomainResource($domain->load(['company', 'product'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Domain $domain): DomainResource
    {
        $this->authorizeRead($request);

        return new DomainResource($domain->load(['company', 'product']));
    }

    public function update(Request $request, Domain $domain): DomainResource
    {
        $this->authorizeWrite($request);

        $data = $this->validated($request, creating: false, domain: $domain);
        $this->assertRegistrarFieldsUntouched($domain, $data);
        if ($domain->source === DomainSource::OpenProvider) {
            foreach ($this->registrarOwned as $field) {
                unset($data[$field]);
            }
        }
        $data = $this->resetCycleWhenDatesMove($domain, $data);
        $domain->update($data);

        return new DomainResource($domain->fresh()->load(['company', 'product']));
    }

    public function destroy(Request $request, Domain $domain): Response
    {
        $this->authorizeDelete($request);

        $domain->delete();

        return response()->noContent();
    }

    public function assign(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);

        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'exists:domains,id'],
        ]);

        $assigned = 0;
        Domain::query()->whereIn('id', $data['ids'])->orderBy('id')->each(function (Domain $domain) use ($data, &$assigned): void {
            $domain->company_id = $data['company_id'];
            $domain->save();
            $assigned++;
        });

        return response()->json([
            'data' => [
                'assigned' => $assigned,
                'company_id' => $data['company_id'],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, bool $creating, ?Domain $domain = null): array
    {
        if ($request->exists('name')) {
            $request->merge([
                'name' => Str::lower(trim($request->string('name')->toString())),
            ]);
        }

        $required = $creating ? ['required'] : ['sometimes', 'required'];

        $data = $request->validate([
            'name' => [...$required, 'string', 'max:255', Rule::unique('domains', 'name')->ignore($domain?->id)],
            'extension' => ['nullable', 'string', 'max:64'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'expires_at' => ['nullable', 'date'],
            'renewal_date' => ['nullable', 'date'],
            'auto_renew' => ['sometimes', 'boolean'],
            'status' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
            'notes_visible_to_customer' => ['sometimes', 'boolean'],
            'notify_30' => ['sometimes', 'boolean'],
            'notify_14' => ['sometimes', 'boolean'],
            'notify_7' => ['sometimes', 'boolean'],
            'notify_1' => ['sometimes', 'boolean'],
            'notify_expired' => ['sometimes', 'boolean'],
        ]);

        if ($creating) {
            $data['source'] = DomainSource::Manual->value;
        }

        return $data;
    }

    private function applyLifecycle(Builder $query, Request $request): void
    {
        $lifecycle = $request->string('lifecycle')->trim()->toString();
        if ($lifecycle === '') {
            $lifecycle = 'present';
        }

        if (! in_array($lifecycle, ['present', 'removed', 'all'], true)) {
            throw ValidationException::withMessages([
                'lifecycle' => ['lifecycle must be present, removed, or all.'],
            ]);
        }

        match ($lifecycle) {
            'removed' => $query->removed(),
            'present' => $query->present(),
            default => null,
        };
    }

    private function applyExpiryFilters(Builder $query, Request $request): void
    {
        if ($request->exists('expired') && $request->input('expired') !== '' && $request->input('expired') !== null) {
            if ($request->boolean('expired')) {
                $query->whereRaw(Domain::ACTIONABLE_DATE.' < ?', [now()->toDateString()]);
            } else {
                $query->whereRaw(Domain::ACTIONABLE_DATE.' >= ?', [now()->toDateString()]);
            }
        }

        if ($request->filled('expires_within')) {
            $days = min(max($request->integer('expires_within'), 0), 3650);
            $query->expiringWithin($days);
        }
    }

    /**
     * Openprovider owns expiry, renewal and auto-renew. The customer link,
     * notes and reminder toggles are ours and stay writable.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertRegistrarFieldsUntouched(Domain $domain, array $data): void
    {
        if ($domain->source !== DomainSource::OpenProvider) {
            return;
        }

        $current = [
            'name' => $domain->name,
            'extension' => $domain->extension,
            'expires_at' => $domain->expires_at?->toDateString(),
            'renewal_date' => $domain->renewal_date?->toDateString(),
            'auto_renew' => (bool) $domain->auto_renew,
            'status' => $domain->status,
            'product_id' => $domain->product_id,
        ];

        $errors = [];
        foreach ($current as $field => $value) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $incoming = $data[$field];
            if (in_array($field, ['expires_at', 'renewal_date'], true)) {
                $incoming = $incoming ? Carbon::parse($incoming)->toDateString() : null;
            }
            if ($field === 'auto_renew') {
                $incoming = (bool) $incoming;
            }
            if ($field === 'product_id') {
                $incoming = $incoming === null ? null : (int) $incoming;
            }

            if ($incoming !== $value) {
                $errors[$field] = ['Owned by Openprovider. A sync overwrites it. Change the customer, notes, or reminders instead.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Either date moving starts a new reminder cycle. Registrars do not always move both.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resetCycleWhenDatesMove(Domain $domain, array $data): array
    {
        foreach (['expires_at', 'renewal_date'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $incoming = $data[$field] ? Carbon::parse($data[$field])->toDateString() : null;
            if ($incoming !== $domain->{$field}?->toDateString()) {
                return [...$data, ...$domain->freshExpiryCycle()];
            }
        }

        return $data;
    }
}
