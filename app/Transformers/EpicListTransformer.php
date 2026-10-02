<?php

namespace App\Transformers;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Customer;
use App\Models\EpicComment;
use Illuminate\Pagination\LengthAwarePaginator;

class EpicListTransformer
{
    /**
     * @param  LengthAwarePaginator<int, Epic>  $epics
     * @return array<string, mixed>
     */
    public function active(LengthAwarePaginator $epics, string $search): array
    {
        return [
            'resource' => __('Epics'),
            'emptyMessage' => $search === ''
                ? __('No epics yet.')
                : __('No epics match your search.'),
            'search' => $this->search(route('epics.index'), $search),
            'navigation' => [
                'label' => __('Trash'),
                'url' => route('epics.trash.index'),
                'icon' => 'trash',
                'test' => 'epic-trash-link',
            ],
            'create' => true,
            'rows' => collect($epics->items())->map(fn (Epic $epic): array => [
                ...$this->columns($epic),
                'actions' => [
                    [
                        'type' => 'form-modal',
                        'label' => __('Edit'),
                        'icon' => 'pencil-square',
                        'test' => 'epic-edit-'.$epic->id,
                        'epic' => [
                            'id' => $epic->id,
                            'name' => $epic->name,
                            'start_date' => $epic->start_date?->toDateString() ?? '',
                            'end_date' => $epic->end_date?->toDateString() ?? '',
                            'project_id' => $epic->project_id,
                            'commentAction' => route('epics.comments.store', $epic),
                            'commentsCount' => (int) $epic->comments_count,
                            'comments' => $epic->comments->map(function (EpicComment $comment): array {
                                $author = $comment->user?->name;

                                return [
                                    'id' => $comment->id,
                                    'author' => $author ?? __('Deleted user'),
                                    'writtenAt' => $comment->created_at?->format('Y-m-d H:i'),
                                    'body' => $comment->body,
                                ];
                            })->all(),
                        ],
                    ],
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Delete'),
                        'icon' => 'trash',
                        'test' => 'epic-delete-'.$epic->id,
                        'danger' => true,
                        'action' => route('epics.destroy', $epic),
                        'method' => 'DELETE',
                        'confirmTitle' => __('Delete epic?'),
                        'confirmText' => __('You can restore it from the trash.'),
                        'confirmLabel' => __('Delete'),
                    ],
                ],
            ])->all(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Epic>  $epics
     * @return array<string, mixed>
     */
    public function trash(LengthAwarePaginator $epics, string $search): array
    {
        return [
            'resource' => __('Epics'),
            'emptyMessage' => $search === ''
                ? __('Trash is empty.')
                : __('No epics match your search.'),
            'search' => $this->search(route('epics.trash.index'), $search),
            'navigation' => [
                'label' => __('Epics'),
                'url' => route('epics.index'),
                'icon' => 'arrow-left',
                'test' => null,
            ],
            'create' => false,
            'rows' => collect($epics->items())->map(fn (Epic $epic): array => [
                ...$this->columns($epic),
                'deletedAt' => $epic->deleted_at?->format('Y-m-d'),
                'actions' => [
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Restore'),
                        'icon' => 'arrow-path',
                        'test' => 'epic-restore-'.$epic->id,
                        'danger' => false,
                        'action' => route('epics.trash.restore', $epic->id),
                        'method' => 'PATCH',
                        'confirmTitle' => __('Restore epic?'),
                        'confirmText' => __('The epic will return to the active list.'),
                        'confirmLabel' => __('Restore'),
                    ],
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Delete permanently'),
                        'icon' => 'trash',
                        'test' => 'epic-force-delete-'.$epic->id,
                        'danger' => true,
                        'action' => route('epics.trash.destroy', $epic->id),
                        'method' => 'DELETE',
                        'confirmTitle' => __('Permanently delete epic?'),
                        'confirmText' => __('This action cannot be undone.'),
                        'confirmLabel' => __('Delete permanently'),
                    ],
                ],
            ])->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, project: string, customer: string, startDate: string, endDate: string, commentsCount: int}
     */
    private function columns(Epic $epic): array
    {
        /** @var Project $project */
        $project = $epic->project;
        /** @var Customer $customer */
        $customer = $project->customer;

        return [
            'id' => $epic->id,
            'name' => $epic->name,
            'project' => $project->name,
            'customer' => $customer->name,
            'startDate' => $epic->start_date?->format('Y-m-d') ?? '',
            'endDate' => $epic->end_date?->format('Y-m-d') ?? '',
            'commentsCount' => (int) $epic->comments_count,
        ];
    }

    /**
     * @return array{action: string, value: string, placeholder: string}
     */
    private function search(string $action, string $value): array
    {
        return [
            'action' => $action,
            'value' => $value,
            'placeholder' => __('Search epics...'),
        ];
    }
}
