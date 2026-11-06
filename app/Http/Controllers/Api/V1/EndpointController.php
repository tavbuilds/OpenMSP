<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EndpointKind;
use App\Http\Controllers\Api\V1\Concerns\AppliesIndexQuery;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAgentApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EndpointResource;
use App\Models\Endpoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class EndpointController extends Controller
{
    use AppliesIndexQuery;
    use AuthorizesAgentApi;

    /** @var list<string> */
    protected array $sortable = [
        'id',
        'name',
        'kind',
        'hostname',
        'expires_at',
        'source',
        'last_status',
        'company_id',
        'created_at',
        'updated_at',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRead($request);

        $query = Endpoint::query()
            ->with('company')
            ->orderByRaw('expires_at is null')
            ->orderBy('expires_at');

        $this->applySearch($query, $request, ['name', 'hostname', 'url', 'notes'], [
            'company' => ['name'],
        ]);
        $this->applyIntegerEquals($query, $request, 'company_id');
        $this->applyStringEquals($query, $request, 'kind');
        $this->applyStringEquals($query, $request, 'source');
        $this->applyStringEquals($query, $request, 'status', 'last_status');
        $this->applySort($query, $request, $this->sortable);
        $this->applyExpiryFilters($query, $request);

        return EndpointResource::collection(
            $query->paginate($this->perPage($request))->withQueryString()
        );
    }

    public function upcoming(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRead($request);

        $days = min(max($request->integer('days', 60), 1), 365);

        $endpoints = Endpoint::query()
            ->with('company')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', now()->addDays($days)->toDateString())
            ->orderBy('expires_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return EndpointResource::collection($endpoints);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);

        $endpoint = Endpoint::create($this->validated($request, creating: true));
        $endpoint->forceFill(['last_status' => $endpoint->computeStatus()])->save();

        return (new EndpointResource($endpoint->load('company')))
            ->showingWebhook()
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Endpoint $endpoint): EndpointResource
    {
        $this->authorizeRead($request);

        return (new EndpointResource($endpoint->load('company')))->showingWebhook();
    }

    public function update(Request $request, Endpoint $endpoint): EndpointResource
    {
        $this->authorizeWrite($request);

        $data = $this->validated($request, creating: false);
        if (array_key_exists('expires_at', $data)) {
            $incoming = $data['expires_at'] ? Carbon::parse($data['expires_at'])->toDateString() : null;
            if ($incoming !== $endpoint->expires_at?->toDateString()) {
                $data = [...$data, ...$endpoint->freshExpiryCycle()];
            }
        }

        $endpoint->update($data);
        $endpoint->forceFill(['last_status' => $endpoint->computeStatus()])->save();

        return (new EndpointResource($endpoint->fresh()->load('company')))->showingWebhook();
    }

    public function destroy(Request $request, Endpoint $endpoint): Response
    {
        $this->authorizeDelete($request);

        $endpoint->delete();

        return response()->noContent();
    }

    public function rotateWebhook(Request $request, Endpoint $endpoint): EndpointResource
    {
        $this->authorizeWrite($request);

        $endpoint->rotateWebhookToken();

        return (new EndpointResource($endpoint->fresh()->load('company')))->showingWebhook();
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, bool $creating): array
    {
        $required = $creating ? ['required'] : ['sometimes', 'required'];

        return $request->validate([
            'name' => [...$required, 'string', 'max:120'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'kind' => ['nullable', Rule::enum(EndpointKind::class)],
            'hostname' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'notify_30' => ['sometimes', 'boolean'],
            'notify_14' => ['sometimes', 'boolean'],
            'notify_7' => ['sometimes', 'boolean'],
            'notify_1' => ['sometimes', 'boolean'],
            'notify_expired' => ['sometimes', 'boolean'],
            'notify_customer' => ['sometimes', 'boolean'],
        ]);
    }

    private function applyExpiryFilters(Builder $query, Request $request): void
    {
        if ($request->exists('expired') && $request->input('expired') !== '' && $request->input('expired') !== null) {
            if ($request->boolean('expired')) {
                $query->whereDate('expires_at', '<', now()->toDateString());
            } else {
                $query->whereDate('expires_at', '>=', now()->toDateString());
            }
        }

        if ($request->filled('expires_within')) {
            $days = min(max($request->integer('expires_within'), 0), 3650);
            $query->whereNotNull('expires_at')
                ->whereDate('expires_at', '<=', now()->addDays($days)->toDateString());
        }
    }
}
