<x-forms.tracked-resource prefix="epic" context-field="_epic_form" id-field="_epic_id">
    <flux:field>
        <flux:label>{{ __('Name') }} <span class="text-red-600" aria-hidden="true">*</span>
        </flux:label>
        <flux:input name="name" x-model="form.name" minlength="4" maxlength="255" required
            autofocus data-test="epic-name" />
        <flux:error name="name" />
    </flux:field>
    <flux:field>
        <flux:label for="epic-project-id">{{ __('Project') }}
            <span class="text-red-600" aria-hidden="true">*</span>
        </flux:label>
        <flux:select id="epic-project-id" name="project_id" x-model="form.project_id" required
            data-test="epic-project">
            <option value="">{{ __('Select a project') }}</option>
            @foreach ($availableProjects as $project)
                <option value="{{ $project->id }}">{{ $project->name }}
                    ({{ $project->customer->name }})</option>
            @endforeach
        </flux:select>
        <flux:error name="project_id" />
    </flux:field>
    <flux:field>
        <flux:label>{{ __('Start date') }}</flux:label>
        <flux:input type="date" name="start_date" x-model="form.start_date"
            x-bind:required="form.end_date !== ''" data-test="epic-start-date" />
        <flux:error name="start_date" />
    </flux:field>
    <flux:field>
        <flux:label>{{ __('End date') }}</flux:label>
        <flux:input type="date" name="end_date" x-model="form.end_date"
            x-bind:min="window.addDays(form.start_date, 1)" data-test="epic-end-date" />
        <flux:description>{{ __('The end date requires a start date and must be after it.') }}
        </flux:description>
        <flux:error name="end_date" />
    </flux:field>

    <x-slot:after>
        <template x-if="form.method === 'PUT'">
            <section class="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60 sm:p-5"
                aria-labelledby="epic-comments-heading" data-test="epic-comments">
                <flux:heading size="lg" id="epic-comments-heading">{{ __('Comments') }}
                </flux:heading>

                <form method="POST" x-bind:action="form.commentAction"
                    x-data="{ isSubmitting: false, isDirty: false }"
                    x-on:form-dirty-change="isDirty = $event.detail.isDirty"
                    x-on:submit="if (!isDirty || isSubmitting) { $event.preventDefault(); return; } isSubmitting = true"
                    data-track-changes class="flex flex-col gap-3">
                    @csrf
                    <input type="hidden" name="_comment_epic_id" x-bind:value="form.id">
                    <flux:field>
                        <flux:label>{{ __('New comment') }}</flux:label>
                        <flux:textarea name="body" x-model="form.commentBody" rows="3"
                            maxlength="5000" required data-test="epic-comment-body" />
                        @error('body', 'comment')
                            <flux:text class="text-sm text-red-600" role="alert">{{ $message }}
                            </flux:text>
                        @enderror
                    </flux:field>
                    <flux:text class="text-xs text-zinc-500">
                        {{ __('Unsaved epic changes are discarded when adding a comment.') }}
                    </flux:text>
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" size="sm"
                            icon="chat-bubble-left" disabled
                            x-bind:disabled="isSubmitting || !isDirty"
                            data-test="epic-comment-submit">
                            {{ __('Add comment') }}
                        </flux:button>
                    </div>
                </form>

                <p x-show="form.comments.length === 0" class="text-sm text-zinc-500"
                    data-test="epic-comments-empty">{{ __('No comments yet.') }}</p>

                <p x-show="form.commentsCount > form.comments.length" class="text-xs text-zinc-500"
                    data-test="epic-comments-truncated"
                    x-text="@js(__('Showing the latest :shown of :total comments.')).replace(':shown', form.comments.length).replace(':total', form.commentsCount)">
                </p>

                <ul class="flex flex-col gap-3" x-show="form.comments.length > 0">
                    <template x-for="comment in form.comments" :key="comment.id">
                        <li class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"
                            data-test="epic-comment">
                            <div class="flex items-center justify-between gap-2 text-xs text-zinc-500">
                                <span class="font-medium text-zinc-700 dark:text-zinc-300"
                                    x-text="comment.author"></span>
                                <time x-text="comment.writtenAt"></time>
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm" x-text="comment.body"></p>
                        </li>
                    </template>
                </ul>
            </section>
        </template>
    </x-slot:after>
</x-forms.tracked-resource>
