@php
    $editingEpic = $list['create'] && str_starts_with(old('_epic_form', ''), 'edit-');
    $deletedEpicConflict = session('deleted_epic_conflict');
    $findEpicPayload = fn(mixed $id): ?array => collect($list['rows'])->firstWhere('id', (int) $id)[
        'actions'
    ][0]['epic'] ?? null;
    $hasCommentErrors = $errors->getBag('comment')->any();
    $commentedEpic = $list['create']
        ? $findEpicPayload(
            session('commented_epic_id') ?? ($hasCommentErrors ? old('_comment_epic_id') : null),
        )
        : null;
    $editingEpicPayload = $editingEpic ? $findEpicPayload(old('_epic_id')) : null;
    $initialForm = $list['create']
        ? [
            'id' => $editingEpic ? old('_epic_id') : null,
            'name' => old('name', ''),
            'start_date' => old('start_date', ''),
            'end_date' => old('end_date', ''),
            'project_id' => old('project_id', ''),
            'comments' => $editingEpicPayload['comments'] ?? [],
            'commentsCount' => $editingEpicPayload['commentsCount'] ?? 0,
            'commentAction' => $editingEpicPayload['commentAction'] ?? '',
            'commentBody' => '',
            'context' => $editingEpic ? old('_epic_form') : 'create',
            'method' => $editingEpic ? 'PUT' : 'POST',
            'action' => $editingEpic
                ? route('epics.update', old('_epic_id'))
                : route('epics.store'),
            'title' => $editingEpic ? __('Edit epic') : __('New epic'),
            'subtitle' => $editingEpic
                ? __('Update the epic details.')
                : __('Add an epic to a project.'),
            'submitLabel' => $editingEpic ? __('Update epic') : __('Save epic'),
        ]
        : null;
@endphp

<x-layouts::app :title="$list['resource']">
    <div x-data="{
        form: @js($initialForm),
        confirmation: {
            action: '',
            method: 'DELETE',
            title: '',
            text: '',
            label: '',
            danger: false,
        },
        storeUrl: @js(route('epics.store')),
        updateUrl: @js(route('epics.update', '__EPIC__')),
        createEpic() {
            this.form = {
                id: null,
                name: '',
                start_date: '',
                end_date: '',
                project_id: '',
                comments: [],
                commentsCount: 0,
                commentAction: '',
                commentBody: '',
                context: 'create',
                method: 'POST',
                action: this.storeUrl,
                title: @js(__('New epic')),
                subtitle: @js(__('Add an epic to a project.')),
                submitLabel: @js(__('Save epic')),
            };
        },
        editEpic(epic, commentBody = '') {
            this.form = {
                ...epic,
                project_id: String(epic.project_id),
                commentBody,
                context: `edit-${epic.id}`,
                method: 'PUT',
                action: this.updateUrl.replace('__EPIC__', epic.id),
                title: @js(__('Edit epic')),
                subtitle: @js(__('Update the epic details.')),
                submitLabel: @js(__('Update epic')),
            };
        },
        confirmAction(action) {
            this.confirmation = {
                action: action.action,
                method: action.method,
                title: action.confirmTitle,
                text: action.confirmText,
                label: action.confirmLabel,
                danger: action.danger,
            };
        },
    }"
        @if ($commentedEpic) x-init="editEpic(@js($commentedEpic), @js($hasCommentErrors ? old('body', '') : '')); $nextTick(() => $dispatch('modal-show', { name: 'epic-form' }))"
        @elseif ($errors->any())
            x-init="$nextTick(() => $dispatch('modal-show', { name: 'epic-form' }))"
        @elseif ($deletedEpicConflict)
            x-init="$nextTick(() => $dispatch('modal-show', { name: 'epic-name-conflict' }))" @endif
        class="flex flex-col gap-4">
        <x-list.header :list="$list" prefix="epic" :create-label="__('New epic')"
            create-click="createEpic()" />

        @if ($list['create'] && $availableProjects->isEmpty())
            <flux:callout icon="exclamation-triangle" variant="warning">
                {{ __('No active projects are available. Create a project before adding epics.') }}
            </flux:callout>
        @endif

        <x-list.flash prefix="epic" />

        @fragment('list-results')
        <x-list.searchable-results :search="$list['search']">
            <x-list.search :search="$list['search']" prefix="epic" />

            <x-list.table prefix="epic" :paginator="$epics">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Project') }}</flux:table.column>
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                    <flux:table.column>{{ __('Start date') }}</flux:table.column>
                    <flux:table.column>{{ __('End date') }}</flux:table.column>
                    <flux:table.column>{{ __('Comments') }}</flux:table.column>
                    <flux:table.column class="text-end">
                        <span class="sr-only">{{ __('Actions') }}</span>
                    </flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($list['rows'] as $row)
                        <flux:table.row :key="$row['id']"
                            class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <flux:table.cell class="font-medium">{{ $row['name'] }}
                            </flux:table.cell>
                            <flux:table.cell>{{ $row['project'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['customer'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['startDate'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['endDate'] }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" icon="chat-bubble-left"
                                    :data-test="'epic-comments-count-'.$row['id']">
                                    {{ $row['commentsCount'] }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-end">
                                <x-list.row-actions :actions="$row['actions']" prefix="epic"
                                    payload-key="epic" edit-handler="editEpic" />
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="py-8 text-center text-zinc-500">
                                <div class="flex flex-col items-center gap-3 px-4 py-4">
                                    <flux:icon.magnifying-glass class="size-8 text-zinc-400 dark:text-zinc-500"
                                        aria-hidden="true" />
                                    <span>{{ $list['emptyMessage'] }}</span>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </x-list.table>
        </x-list.searchable-results>
        @endfragment

        @if ($list['create'])
            <flux:modal name="epic-form" variant="flyout" position="right"
                x-on:close="window.clearForm($el.querySelector('form')); window.resetTrackedForms($el)"
                x-on:cancel.prevent="window.requestTrackedModalClose($el)"
                class="form-drawer max-w-none">
                @include('epics.form')
            </flux:modal>

            @if ($deletedEpicConflict)
                <x-name-conflict-modal name="epic-name-conflict" :title="__('Epic name already in trash')" :message="__('A deleted epic in this project already uses the name :name.', [
                    'name' => $deletedEpicConflict['name'],
                ])"
                    :create-action="route('epics.store')" :restore-action="route('epics.trash.restore', $deletedEpicConflict['id'])" :create-fields="[
                        'name' => old('name', $deletedEpicConflict['name']),
                        'start_date' => old('start_date'),
                        'end_date' => old('end_date'),
                        'project_id' => old('project_id'),
                    ]" :create-label="__('Create a new epic')"
                    :restore-label="__('Restore the deleted epic instead')" create-test="epic-conflict-create-new"
                    restore-test="epic-conflict-restore" />
            @endif
        @endif

        <x-list.confirm-modal prefix="epic" />
    </div>
</x-layouts::app>
