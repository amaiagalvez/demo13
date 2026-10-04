<?php

namespace App\Transformers;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends ListTransformer<Project>
 */
class ProjectListTransformer extends ListTransformer
{
    /**
     * @param  LengthAwarePaginator<int, Project>  $projects
     * @param  array{active: int, inactive: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function active(LengthAwarePaginator $projects, string $search, ?array $counts = null): array
    {
        return [
            ...$this->envelope('active', $search, $counts),
            'rows' => collect($projects->items())->map(fn (Project $project): array => [
                ...$this->columns($project),
                'extraDate' => $project->created_at?->toIso8601String(),
                'actionHint' => $project->epics_exists
                    ? __('Cannot be deleted while it has related records.')
                    : null,
                'actions' => [
                    $this->editAction($project),
                    $project->epics_exists
                        ? $this->deactivateAction($project)
                        : $this->deleteAction($project),
                ],
            ])->all(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Project>  $projects
     * @param  array{active: int, inactive: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function inactive(LengthAwarePaginator $projects, string $search, ?array $counts = null): array
    {
        return [
            ...$this->envelope('inactive', $search, $counts),
            'rows' => collect($projects->items())->map(fn (Project $project): array => [
                ...$this->columns($project),
                'extraDate' => $project->updated_at?->toIso8601String(),
                'actions' => [$this->reactivateAction($project)],
            ])->all(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Project>  $projects
     * @param  array{active: int, inactive: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function trash(LengthAwarePaginator $projects, string $search, ?array $counts = null): array
    {
        return [
            ...$this->envelope('trash', $search, $counts),
            'rows' => collect($projects->items())->map(fn (Project $project): array => [
                ...$this->columns($project),
                'extraDate' => $project->deleted_at?->toIso8601String(),
                'actions' => [
                    $this->restoreAction($project, $project->active),
                    $this->forceDeleteAction($project),
                ],
            ])->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, customer: string, startDate: string, endDate: string, epicsCount: int, epicsUrl: string|null, commentsCount: int, editPayload: array{id: int, name: string, notes: string|null, start_date: string, end_date: string, customer_id: int, customer_name: string}}
     */
    private function columns(Project $project): array
    {
        $epicsCount = (int) $project->epics_count;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'customer' => $project->customer->name,
            'startDate' => $project->start_date->format('Y-m-d'),
            'endDate' => $project->end_date?->format('Y-m-d') ?? '',
            'epicsCount' => $epicsCount,
            // The epics list searches project names, so the row count can link to its own slice.
            'epicsUrl' => $epicsCount > 0
                ? route('epics.index', ['search' => $project->name])
                : null,
            'commentsCount' => (int) $project->comments_count,
            'editPayload' => $this->editPayload($project),
        ];
    }

    /**
     * @param  Project  $record
     * @return array{id: int, name: string, notes: string|null, start_date: string, end_date: string, customer_id: int, customer_name: string}
     */
    protected function editPayload(Model $record): array
    {
        return [
            'id' => $record->id,
            'name' => $record->name,
            'notes' => $record->notes,
            'start_date' => $record->start_date->toDateString(),
            'end_date' => $record->end_date?->toDateString() ?? '',
            'customer_id' => $record->customer_id,
            'customer_name' => $record->customer->name,
        ];
    }

    protected function resourceLabel(): string
    {
        return __('Projects');
    }

    protected function noMatchMessage(): string
    {
        return __('No projects match your search.');
    }

    protected function noRecordsMessage(): string
    {
        return __('No projects yet.');
    }

    protected function resourceKey(): string
    {
        return 'project';
    }

    /**
     * @return array{active: string, inactive: string, trash: string, destroy: string, deactivate: string, reactivate: string, restore: string, trashDestroy: string}
     */
    protected function routes(): array
    {
        return [
            'active' => 'projects.index',
            'inactive' => 'projects.inactive.index',
            'trash' => 'projects.trash.index',
            'destroy' => 'projects.destroy',
            'deactivate' => 'projects.deactivate',
            'reactivate' => 'projects.inactive.reactivate',
            'restore' => 'projects.trash.restore',
            'trashDestroy' => 'projects.trash.destroy',
        ];
    }
}
