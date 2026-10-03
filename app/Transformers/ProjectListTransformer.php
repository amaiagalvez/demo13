<?php

namespace App\Transformers;

use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectListTransformer extends ListTransformer
{
    /**
     * @param  LengthAwarePaginator<int, Project>  $projects
     * @return array<string, mixed>
     */
    public function active(LengthAwarePaginator $projects, string $search): array
    {
        return [
            'resource' => __('Projects'),
            'state' => 'active',
            'extraDateHeading' => null,
            'inactiveUrl' => route('projects.inactive.index'),
            'emptyMessage' => $search === ''
                ? __('No projects yet.')
                : __('No projects match your search.'),
            'search' => $this->search(route('projects.index'), $search, __('Search projects...')),
            'navigation' => [
                'label' => __('Trash'),
                'url' => route('projects.trash.index'),
                'icon' => 'trash',
                'test' => 'project-trash-link',
            ],
            'create' => true,
            'rows' => collect($projects->items())->map(fn(Project $project): array => [
                ...$this->columns($project),
                'actions' => [
                    [
                        'type' => 'form-modal',
                        'label' => __('Edit'),
                        'icon' => 'pencil-square',
                        'test' => 'project-edit-' . $project->id,
                        'project' => [
                            'id' => $project->id,
                            'name' => $project->name,
                            'start_date' => $project->start_date->toDateString(),
                            'end_date' => $project->end_date?->toDateString() ?? '',
                            'customer_id' => $project->customer_id,
                            'customer_name' => $project->customer?->name ?? '—',
                        ],
                    ],
                    $project->epics_exists ? [
                        'type' => 'confirm-modal',
                        'label' => __('Deactivate'),
                        'icon' => 'lock-closed',
                        'test' => 'project-deactivate-' . $project->id,
                        'danger' => true,
                        'action' => route('projects.deactivate', $project),
                        'method' => 'PATCH',
                        'confirmTitle' => __('Deactivate record?'),
                        'confirmText' => __('You can reactivate it from the inactive list.'),
                        'confirmLabel' => __('Deactivate'),
                    ] : [
                        'type' => 'confirm-modal',
                        'label' => __('Delete'),
                        'icon' => 'trash',
                        'test' => 'project-delete-' . $project->id,
                        'danger' => true,
                        'action' => route('projects.destroy', $project),
                        'method' => 'DELETE',
                        'confirmTitle' => __('Delete project?'),
                        'confirmText' => __('You can restore it from the trash.'),
                        'confirmLabel' => __('Delete'),
                    ],
                ],
            ])->all(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Project>  $projects
     * @return array<string, mixed>
     */
    public function inactive(LengthAwarePaginator $projects, string $search): array
    {
        return [
            'resource' => __('Projects'),
            'state' => 'inactive',
            'extraDateHeading' => __('Updated at'),
            'emptyMessage' => $search === '' ? __('No inactive records.') : __('No projects match your search.'),
            'search' => $this->search(route('projects.inactive.index'), $search, __('Search projects...')),
            'navigation' => [
                'label' => __('Projects'),
                'url' => route('projects.index'),
                'icon' => 'arrow-left',
                'test' => null,
            ],
            'create' => false,
            'rows' => collect($projects->items())->map(fn(Project $project): array => [
                ...$this->columns($project),
                'extraDate' => $project->updated_at?->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Reactivate'),
                        'icon' => 'lock-open',
                        'test' => 'project-reactivate-' . $project->id,
                        'danger' => false,
                        'action' => route('projects.inactive.reactivate', $project),
                        'method' => 'PATCH',
                        'confirmTitle' => __('Reactivate record?'),
                        'confirmText' => __('The record will return to the active list.'),
                        'confirmLabel' => __('Reactivate'),
                    ],
                ],
            ])->all(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Project>  $projects
     * @return array<string, mixed>
     */
    public function trash(LengthAwarePaginator $projects, string $search): array
    {
        return [
            'resource' => __('Projects'),
            'state' => 'trash',
            'extraDateHeading' => __('Deleted at'),
            'emptyMessage' => $search === ''
                ? __('Trash is empty.')
                : __('No projects match your search.'),
            'search' => $this->search(route('projects.trash.index'), $search, __('Search projects...')),
            'navigation' => [
                'label' => __('Projects'),
                'url' => route('projects.index'),
                'icon' => 'arrow-left',
                'test' => 'project-list-link',
            ],
            'create' => false,
            'rows' => collect($projects->items())->map(fn(Project $project): array => [
                ...$this->columns($project),
                'extraDate' => $project->deleted_at?->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Restore'),
                        'icon' => 'arrow-path',
                        'test' => 'project-restore-' . $project->id,
                        'danger' => false,
                        'action' => route('projects.trash.restore', $project->id),
                        'method' => 'PATCH',
                        'confirmTitle' => __('Restore project?'),
                        'confirmText' => $project->active
                            ? __('The project will return to the active list.')
                            : __('The record will return to the inactive list.'),
                        'confirmLabel' => __('Restore'),
                    ],
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Delete permanently'),
                        'icon' => 'trash',
                        'test' => 'project-force-delete-' . $project->id,
                        'danger' => true,
                        'action' => route('projects.trash.destroy', $project->id),
                        'method' => 'DELETE',
                        'confirmTitle' => __('Permanently delete project?'),
                        'confirmText' => __('This action cannot be undone.'),
                        'confirmLabel' => __('Delete permanently'),
                    ],
                ],
            ])->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, customer: string, startDate: string, endDate: string, epicsCount: int, commentsCount: int}
     */
    private function columns(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'customer' => $project->customer?->name ?? '—',
            'startDate' => $project->start_date->format('Y-m-d'),
            'endDate' => $project->end_date?->format('Y-m-d') ?? '',
            'epicsCount' => (int) $project->epics_count,
            'commentsCount' => (int) $project->comments_count,
        ];
    }
}
