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

<x-layouts::app :title="$list['title']">
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
        @if ($errors->any())
            x-init="$nextTick(() => $dispatch('modal-show', { name: 'project-form' }))"
        @elseif ($deletedProjectConflict)
            x-init="$nextTick(() => $dispatch('modal-show', { name: 'project-name-conflict' }))"
        @endif
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
                    <flux:modal.trigger name="project-form">
                        <flux:button variant="primary" icon="plus" x-on:click="createProject()"
                            data-test="project-create-button">
                            {{ __('New project') }}
                        </flux:button>
                    </flux:modal.trigger>
                @endif
            </div>
        </div>

        @if ($list['create'] && $availableCustomers->isEmpty())
            <flux:callout icon="exclamation-triangle" variant="warning">
                {{ __('No active customers are available. Create one from the project form.') }}
            </flux:callout>
        @endif

        @if (session('status'))
            <flux:callout icon="check-circle" variant="success" x-data="{ visible: true }"
                x-init="setTimeout(() => visible = false, 10000)" x-show="visible" x-transition.opacity
                data-test="project-status">{{ session('status') }}</flux:callout>
        @endif

        @if (session('error'))
            <flux:callout icon="exclamation-triangle" variant="danger" data-test="project-error">
                {{ session('error') }}</flux:callout>
        @endif

        <form method="GET" action="{{ $list['search']['action'] }}"
            class="flex w-full items-end gap-2 sm:max-w-xl">
            <flux:input name="search" :label="__('Search')"
                :placeholder="$list['search']['placeholder']" :value="$list['search']['value']"
                maxlength="255" icon="magnifying-glass" data-test="project-search" />
            <flux:button type="submit" variant="primary" icon="magnifying-glass"
                data-test="project-search-submit">
                {{ __('Search') }}
            </flux:button>
            @if ($list['search']['value'] !== '')
                <flux:button :href="$list['search']['action']" variant="ghost" icon="x-mark"
                    wire:navigate data-test="project-search-clear">
                    {{ __('Clear') }}
                </flux:button>
            @endif
        </form>

        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                    <flux:table.column>{{ __('Start date') }}</flux:table.column>
                    <flux:table.column>{{ __('End date') }}</flux:table.column>
                    <flux:table.column class="text-end">
                        <span class="sr-only">{{ __('Actions') }}</span>
                    </flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($list['rows'] as $row)
                        <flux:table.row :key="$row['id']">
                            <flux:table.cell class="font-medium">{{ $row['name'] }}
                            </flux:table.cell>
                            <flux:table.cell>{{ $row['customer'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['startDate'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['endDate'] }}</flux:table.cell>
                            <flux:table.cell class="text-end">
                                <div class="flex justify-end gap-2">
                                    @foreach ($row['actions'] as $action)
                                        @if ($action['type'] === 'form-modal')
                                            <flux:modal.trigger name="project-form">
                                                <flux:tooltip :content="$action['label']">
                                                    <flux:button size="sm" variant="ghost"
                                                        :icon="$action['icon']"
                                                        :aria-label="$action['label']"
                                                        x-on:click="editProject(JSON.parse($el.dataset.project))"
                                                        data-project="{{ json_encode($action['project']) }}"
                                                        :data-test="$action['test']" />
                                                </flux:tooltip>
                                            </flux:modal.trigger>
                                        @else
                                            <flux:modal.trigger name="project-confirm">
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
                            <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">
                                {{ $list['emptyMessage'] }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($projects->hasPages())
            <nav aria-label="{{ __('Pagination') }}" data-test="project-pagination">
                {{ $projects->links() }}
            </nav>
        @endif

        @if ($list['create'])
            <flux:modal name="project-form" variant="flyout" position="right"
                x-on:close="window.clearForm($el.querySelector('form'))"
                class="project-drawer max-w-none">
                @include('projects.form')
            </flux:modal>

            @if ($deletedProjectConflict)
                <x-name-conflict-modal
                    name="project-name-conflict"
                    :title="__('Project name already in trash')"
                    :message="__('A deleted project already uses this name.', ['name' => $deletedProjectConflict['name']])"
                    :create-action="route('projects.store')"
                    :restore-action="route('projects.trash.restore', $deletedProjectConflict['id'])"
                    :create-fields="[
                        'name' => old('name', $deletedProjectConflict['name']),
                        'start_date' => old('start_date'),
                        'end_date' => old('end_date'),
                        'customer_id' => old('customer_id'),
                    ]"
                    :create-label="__('Create a new project')"
                    :restore-label="__('Restore the deleted project instead')"
                    create-test="project-conflict-create-new"
                    restore-test="project-conflict-restore" />
            @endif
        @endif

        <flux:modal name="project-confirm" class="max-w-md">
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
                                x-bind:disabled="isSubmitting" data-test="project-confirm-submit">
                                <span x-text="confirmation.label"></span>
                            </flux:button>
                        </template>
                        <template x-if="!confirmation.danger">
                            <flux:button variant="primary" type="submit"
                                x-bind:disabled="isSubmitting" data-test="project-confirm-submit">
                                <span x-text="confirmation.label"></span>
                            </flux:button>
                        </template>
                    </form>
                </div>
            </div>
        </flux:modal>
    </div>
</x-layouts::app>
