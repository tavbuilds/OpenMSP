<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PlannedTaskKind;
use App\Enums\PlannedTaskPriority;
use App\Enums\PlannedTaskStatus;
use App\Http\Controllers\Api\V1\Concerns\AppliesIndexQuery;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesAgentApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlannedTaskResource;
use App\Models\PlannedTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class PlannedTaskController extends Controller
{
    use AppliesIndexQuery;
    use AuthorizesAgentApi;

    /** @var list<string> */
    protected array $sortable = [
        'id',
        'title',
        'due_on',
        'kind',
        'status',
        'priority',
        'created_at',
        'updated_at',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRead($request);

        $query = PlannedTask::query()
            ->with(['company', 'assignedUser'])
            ->orderBy('due_on');

        $this->applySearch(
            $query,
            $request,
            ['title', 'location_from', 'location_to', 'notes'],
            ['company' => ['name']],
        );
        $this->applyIntegerEquals($query, $request, 'company_id');
        $this->applyIntegerEquals($query, $request, 'assigned_user_id');
        $this->applyStringEquals($query, $request, 'kind');
        $this->applyStringEquals($query, $request, 'status');
        $this->applyStringEquals($query, $request, 'priority');
        $this->applyDateRange($query, $request, 'due_on', 'due_after', 'due_before');
        $this->applySort($query, $request, $this->sortable);

        if ($request->boolean('open')) {
            $query->open();
        }

        if ($request->boolean('overdue')) {
            $query->open()->whereDate('due_on', '<', now()->toDateString());
        }

        return PlannedTaskResource::collection(
            $query->paginate($this->perPage($request))->withQueryString()
        );
    }

    public function upcoming(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRead($request);

        $days = min(max($request->integer('days', 60), 1), 365);

        $tasks = PlannedTask::query()
            ->with(['company', 'assignedUser'])
            ->open()
            ->whereDate('due_on', '<=', now()->addDays($days)->toDateString())
            ->orderBy('due_on')
            ->limit(50)
            ->get();

        return PlannedTaskResource::collection($tasks);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);

        $task = PlannedTask::create($this->validated($request, creating: true));

        return (new PlannedTaskResource($task->load(['company', 'assignedUser'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, PlannedTask $plannedTask): PlannedTaskResource
    {
        $this->authorizeRead($request);

        return new PlannedTaskResource($plannedTask->load(['company', 'assignedUser']));
    }

    public function update(Request $request, PlannedTask $plannedTask): PlannedTaskResource
    {
        $this->authorizeWrite($request);

        $plannedTask->update($this->validated($request, creating: false));

        return new PlannedTaskResource($plannedTask->fresh()->load(['company', 'assignedUser']));
    }

    public function destroy(Request $request, PlannedTask $plannedTask): Response
    {
        $this->authorizeDelete($request);

        $plannedTask->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, bool $creating): array
    {
        $required = $creating ? ['required'] : ['sometimes', 'required'];

        return $request->validate([
            'title' => [...$required, 'string', 'max:180'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'kind' => ['nullable', Rule::enum(PlannedTaskKind::class)],
            'status' => ['nullable', Rule::enum(PlannedTaskStatus::class)],
            'priority' => ['nullable', Rule::enum(PlannedTaskPriority::class)],
            'due_on' => [...$required, 'date'],
            'location_from' => ['nullable', 'string', 'max:180'],
            'location_to' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string'],
            'notify_30' => ['sometimes', 'boolean'],
            'notify_14' => ['sometimes', 'boolean'],
            'notify_7' => ['sometimes', 'boolean'],
            'notify_1' => ['sometimes', 'boolean'],
            'notify_expired' => ['sometimes', 'boolean'],
        ]);
    }
}
