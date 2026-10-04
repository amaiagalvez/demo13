<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Support\Validation\MaxLength;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The info icon next to a field label lists the validations the request applies to that field, so
 * that what the form promises and what the server asks for are the same thing.
 */
class ValidationNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_customer_name_notice_lists_every_rule_of_the_field(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-test="customer-name-info"',
                __('Use at least :min characters.', ['min' => 4]),
                __('Use at most :max characters.', ['max' => MaxLength::string()]),
                __('Must be unique.'),
            ], false);
    }

    public function test_the_project_end_date_notice_explains_the_rule_against_the_start_date(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-test="project-end-date-info"',
                __('Must be on or after :field.', ['field' => mb_strtolower(__('Start date'))]),
            ], false);
    }

    public function test_the_epic_start_date_notice_says_when_it_is_asked_for(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('epics.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-test="epic-start-date-info"',
                __('Required when :field is filled in.', ['field' => mb_strtolower(__('End date'))]),
            ], false);
    }

    public function test_every_field_with_something_to_announce_carries_its_notice(): void
    {
        $this->actingAs(User::factory()->create());

        $forms = [
            'customers' => [route('customers.index'), [
                'customer-name-info',
                'customer-notes-info',
            ]],
            'projects' => [route('projects.index'), [
                'project-name-info',
                'project-end-date-info',
                'project-notes-info',
            ]],
            'epics' => [route('epics.index'), [
                'epic-name-info',
                'epic-start-date-info',
                'epic-end-date-info',
                'epic-notes-info',
                'epic-comment-body-info',
                'epic-comment-notes-info',
            ]],
        ];

        foreach ($forms as $resource => [$url, $notices]) {
            $content = $this->get($url)->assertOk()->getContent() ?: '';

            foreach ($notices as $notice) {
                $this->assertStringContainsString(
                    'data-test="'.$notice.'"',
                    $content,
                    "The {$resource} form is missing the notice of a field.",
                );
            }
        }
    }

    /**
     * A start date asked for with the asterisk, and a selector that only offers the records it
     * accepts, have nothing left to announce, so no icon is drawn next to their labels.
     */
    public function test_a_field_without_anything_to_announce_carries_no_notice(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee('data-test="project-start-date-info"', false)
            ->assertDontSee('data-test="project-customer-info"', false);
    }
}
