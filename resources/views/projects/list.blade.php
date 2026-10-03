@php
    $editingProject = $list['create'] && str_starts_with(old('_project_form', ''), 'edit-');
    $deletedProjectConflict = session('deleted_project_conflict');
    $initialForm = $list['create']
        ? [
            'id' => $editingProject ? old('_project_id') : null,
            'name' => old('name', ''),
            'start_date' => old('start_date', ''),
            'end_date' => old('end_date', ''),
            'customer_id' => old('customer_id', ''),
            'customer_name' => $selectedCustomer?->name ?? '',
            'customerCreateError' => '',
            'customerCreating' => false,
            'context' => $editingProject ? old('_project_form') : 'create',
            'method' => $editingProject ? 'PUT' : 'POST',
            'action' => $editingProject
                ? route('projects.update', old('_project_id'))
                : route('projects.store'),
            'title' => $editingProject ? __('Edit project') : __('New project'),
            'subtitle' => $editingProject
                ? __('Update the project details.')
                : __('Add a project to your records.'),
            'submitLabel' => $editingProject ? __('Update project') : __('Save project'),
        ]
        : null;
@endphp

<x-layouts::app :title="$list['resource']">
    <div data-project-form-root x-data="{
        form: @js($initialForm),
        confirmation: {
            action: '',
            method: 'DELETE',
            title: '',
            text: '',
            label: '',
            danger: false,
        },
        storeUrl: @js(route('projects.store')),
        updateUrl: @js(route('projects.update', '__PROJECT__')),
        createProject() {
            this.form = {
                id: null,
                name: '',
                start_date: '',
                end_date: '',
                customer_id: '',
                customer_name: '',
                customerCreateError: '',
                customerCreating: false,
                context: 'create',
                method: 'POST',
                action: this.storeUrl,
                title: @js(__('New project')),
                subtitle: @js(__('Add a project to your records.')),
                submitLabel: @js(__('Save project')),
            };
        },
        editProject(project) {
            this.form = {
                ...project,
                customerCreateError: '',
                customerCreating: false,
                context: `edit-${project.id}`,
                method: 'PUT',
                action: this.updateUrl.replace('__PROJECT__', project.id),
                title: @js(__('Edit project')),
                subtitle: @js(__('Update the project details.')),
                submitLabel: @js(__('Update project')),
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
        @if ($errors->any()) x-init="$nextTick(() => $dispatch('modal-show', { name: 'project-form' }))"
        @elseif ($deletedProjectConflict)
            x-init="$nextTick(() => $dispatch('modal-show', { name: 'project-name-conflict' }))"
        @elseif ($list['create'] && request()->boolean('create'))
            x-init="createProject(); $nextTick(() => $dispatch('modal-show', { name: 'project-form' }))" @endif
        class="flex flex-col gap-y-2 sm:gap-y-3">
        <x-list.page-header :list="$list" prefix="project">
            <x-slot:actions>
                @if ($list['create'])
                    <x-list.create-action prefix="project" :label="__('New project')"
                        click="createProject()" />
                @endif
            </x-slot:actions>
        </x-list.page-header>

        @if ($list['create'] && !$hasCustomers)
            <flux:callout icon="exclamation-triangle" variant="warning">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>{{ __('No active customers are available. Open the customer form to create one.') }}</span>
                    <flux:button size="sm" variant="primary"
                        :href="route('customers.index', ['create' => 1])"
                        data-test="project-no-customer-form-button">
                        {{ __('Open customer form') }}
                    </flux:button>
                </div>
            </flux:callout>
        @endif

        <x-list.flash prefix="project" />

        @fragment('list-results')
            <x-list.searchable-results :search="$list['search']">
                <x-list.search :search="$list['search']" prefix="project" />

                <x-list.table prefix="project" :paginator="$projects">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Customer') }}</flux:table.column>
                        <flux:table.column>{{ __('Start date') }}</flux:table.column>
                        <flux:table.column>{{ __('End date') }}</flux:table.column>
                        <flux:table.column>{{ __('Epics') }}</flux:table.column>
                        <flux:table.column>{{ __('Comments') }}</flux:table.column>
                        @if ($list['extraDateHeading'])
                            <flux:table.column>{{ $list['extraDateHeading'] }}</flux:table.column>
                        @endif
                        <flux:table.column align="end" sticky class="text-end">
                            <span class="sr-only">{{ __('Actions') }}</span>
                        </flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($list['rows'] as $row)
                            <flux:table.row :key="$row['id']"
                                class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <flux:table.cell class="max-w-[16rem]">
                                    <button type="button"
                                        class="block max-w-full truncate rounded text-start font-medium text-zinc-900 focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2 dark:text-zinc-100 dark:focus-visible:ring-brand-400"
                                        title="{{ $row['name'] }}"
                                        data-test="project-name-{{ $row['id'] }}"
                                        x-on:click="editProject(JSON.parse($el.dataset.payload)); $dispatch('modal-show', { name: 'project-form' })"
                                        data-payload="{{ json_encode($row['editPayload']) }}">{{ $row['name'] }}</button>
                                </flux:table.cell>
                                <flux:table.cell class="max-w-[16rem] truncate"
                                    :title="$row['customer']">
                                    {{ $row['customer'] }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <x-list.local-time :datetime="$row['startDate']" format="date" />
                                </flux:table.cell>
                                <flux:table.cell>
                                    <x-list.local-time :datetime="$row['endDate']" format="date" />
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($row['epicsUrl'])
                                        <flux:button :href="$row['epicsUrl']" wire:navigate
                                            size="xs" variant="ghost" icon="flag"
                                            :aria-label="__('View :count epics', ['count' => $row['epicsCount']])"
                                            data-test="project-epics-count-{{ $row['id'] }}"
                                            class="hover:bg-zinc-100 hover:text-brand-700 dark:hover:bg-zinc-800 dark:hover:text-brand-400">
                                            {{ $row['epicsCount'] }}
                                        </flux:button>
                                    @else
                                        <span
                                            data-test="project-epics-count-{{ $row['id'] }}"></span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($row['commentsCount'] > 0)
                                        <flux:badge size="sm" icon="chat-bubble-left"
                                            data-test="project-comments-count-{{ $row['id'] }}"
                                            class="hover:text-brand-700 dark:hover:text-brand-400">
                                            {{ $row['commentsCount'] }}
                                        </flux:badge>
                                    @else
                                        <span
                                            data-test="project-comments-count-{{ $row['id'] }}"></span>
                                    @endif
                                </flux:table.cell>
                                @if ($list['extraDateHeading'])
                                    <flux:table.cell>
                                        <x-list.local-time :datetime="$row['extraDate']" format="datetime" />
                                    </flux:table.cell>
                                @endif
                                <flux:table.cell align="end" sticky
                                    class="bg-white dark:bg-zinc-900">
                                    <x-list.row-actions :actions="$row['actions']" prefix="project"
                                        payload-key="project" edit-handler="editProject"
                                        :blocked-hint="$row['actionHint'] ?? null" />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <x-list.empty-state :colspan="$list['extraDateHeading'] ? 8 : 7" :message="$list['emptyMessage']" />
                        @endforelse
                    </flux:table.rows>
                </x-list.table>
            </x-list.searchable-results>
        @endfragment

        @if ($list['create'])
            <flux:modal name="project-form" aria-labelledby="project-form-heading" variant="flyout"
                position="right"
                x-on:close="window.clearForm($el.querySelector('form')); window.resetTrackedForms($el)"
                x-on:cancel.prevent="window.requestTrackedModalClose($el)"
                class="form-drawer max-w-none">
                @include('projects.form')
            </flux:modal>

            @if ($deletedProjectConflict)
                <x-name-conflict-modal name="project-name-conflict" :title="__('Project name already in trash')"
                    :message="__('A deleted project already uses the name :name.', [
                        'name' => $deletedProjectConflict['name'],
                    ])" :create-action="route('projects.store')" :restore-action="route('projects.trash.restore', $deletedProjectConflict['id'])" :create-fields="[
                        'name' => old('name', $deletedProjectConflict['name']),
                        'start_date' => old('start_date'),
                        'end_date' => old('end_date'),
                        'customer_id' => old('customer_id'),
                    ]"
                    :create-label="__('Create a new project')" :restore-label="__('Restore the deleted project instead')" create-test="project-conflict-create-new"
                    restore-test="project-conflict-restore" />
            @endif
        @endif

        <x-list.confirm-modal prefix="project" />
    </div>
</x-layouts::app>
