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

<x-layouts::app :title="$list['title']">
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
        class="flex flex-col gap-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ $list['title'] }}</flux:heading>
                <flux:subheading>{{ $list['subtitle'] }}</flux:subheading>
            </div>
            <div class="flex items-center gap-2">
                <flux:button :href="$list['navigation']['url']" variant="ghost"
                    :icon="$list['navigation']['icon']" wire:navigate
                    :data-test="$list['navigation']['test']">
                    {{ $list['navigation']['label'] }}
                </flux:button>
                @if ($list['create'])
                    <flux:modal.trigger name="epic-form">
                        <flux:button variant="primary" icon="plus" x-on:click="createEpic()"
                            data-test="epic-create-button">
                            {{ __('New epic') }}
                        </flux:button>
                    </flux:modal.trigger>
                @endif
            </div>
        </div>

        @if ($list['create'] && $availableProjects->isEmpty())
            <flux:callout icon="exclamation-triangle" variant="warning">
                {{ __('No active projects are available. Create a project before adding epics.') }}
            </flux:callout>
        @endif

        @if (session('status'))
            <flux:callout icon="check-circle" variant="success" x-data="{ visible: true }"
                x-init="setTimeout(() => visible = false, 10000)" x-show="visible" x-transition.opacity
                data-test="epic-status">{{ session('status') }}</flux:callout>
        @endif

        @if (session('error'))
            <flux:callout icon="exclamation-triangle" variant="danger" data-test="epic-error">
                {{ session('error') }}</flux:callout>
        @endif

        <form method="GET" action="{{ $list['search']['action'] }}"
            class="flex w-full items-end gap-2 sm:max-w-xl">
            <flux:input name="search" :label="__('Search')"
                :placeholder="$list['search']['placeholder']" :value="$list['search']['value']"
                maxlength="255" icon="magnifying-glass" data-test="epic-search" />
            <flux:button type="submit" variant="primary" icon="magnifying-glass"
                data-test="epic-search-submit">
                {{ __('Search') }}
            </flux:button>
            @if ($list['search']['value'] !== '')
                <flux:button :href="$list['search']['action']" variant="ghost" icon="x-mark"
                    wire:navigate data-test="epic-search-clear">
                    {{ __('Clear') }}
                </flux:button>
            @endif
        </form>

        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <flux:table>
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
                        <flux:table.row :key="$row['id']">
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
                                <div class="flex justify-end gap-2">
                                    @foreach ($row['actions'] as $action)
                                        @if ($action['type'] === 'form-modal')
                                            <flux:modal.trigger name="epic-form">
                                                <flux:tooltip :content="$action['label']">
                                                    <flux:button size="sm" variant="ghost"
                                                        :icon="$action['icon']"
                                                        :aria-label="$action['label']"
                                                        x-on:click="editEpic(JSON.parse($el.dataset.epic))"
                                                        data-epic="{{ json_encode($action['epic']) }}"
                                                        :data-test="$action['test']" />
                                                </flux:tooltip>
                                            </flux:modal.trigger>
                                        @else
                                            <flux:modal.trigger name="epic-confirm">
                                                <flux:tooltip :content="$action['label']">
                                                    <flux:button size="sm" variant="ghost"
                                                        :icon="$action['icon']"
                                                        :aria-label="$action['label']"
                                                        :class="$action['danger'] ?? false ?
                                                            'text-red-600' : ''"
                                                        x-on:click="confirmAction(JSON.parse($el.dataset.action))"
                                                        data-action="{{ json_encode($action) }}"
                                                        :data-test="$action['test']" />
                                                </flux:tooltip>
                                            </flux:modal.trigger>
                                        @endif
                                    @endforeach
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="py-8 text-center text-zinc-500">
                                {{ $list['emptyMessage'] }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($epics->hasPages())
            <nav aria-label="{{ __('Pagination') }}" data-test="epic-pagination">
                {{ $epics->links() }}
            </nav>
        @endif

        @if ($list['create'])
            <flux:modal name="epic-form" variant="flyout" position="right"
                x-on:close="window.clearForm($el.querySelector('form'))"
                class="epic-drawer max-w-none">
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

        <flux:modal name="epic-confirm" class="max-w-md">
            <div class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg" x-text="confirmation.title"></flux:heading>
                    <flux:text class="mt-2" x-text="confirmation.text"></flux:text>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <form method="POST" x-bind:action="confirmation.action"
                        x-data="{ isSubmitting: false }"
                        x-on:submit="if (isSubmitting) $event.preventDefault(); isSubmitting = true">
                        @csrf
                        <input type="hidden" name="_method" x-bind:value="confirmation.method">
                        <template x-if="confirmation.danger">
                            <flux:button variant="danger" type="submit"
                                x-bind:disabled="isSubmitting" data-test="epic-confirm-submit">
                                <span x-text="confirmation.label"></span>
                            </flux:button>
                        </template>
                        <template x-if="!confirmation.danger">
                            <flux:button variant="primary" type="submit"
                                x-bind:disabled="isSubmitting" data-test="epic-confirm-submit">
                                <span x-text="confirmation.label"></span>
                            </flux:button>
                        </template>
                    </form>
                </div>
            </div>
        </flux:modal>
    </div>
</x-layouts::app>
