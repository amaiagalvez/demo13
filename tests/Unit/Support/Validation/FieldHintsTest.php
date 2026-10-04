<?php

namespace Tests\Unit\Support\Validation;

use Tests\TestCase;
use App\Http\Requests\EpicRequest;
use App\Http\Requests\ProjectRequest;
use App\Support\Validation\MaxLength;
use App\Http\Requests\CustomerRequest;
use App\Support\Validation\FieldHints;
use App\Http\Requests\EpicCommentRequest;

class FieldHintsTest extends TestCase
{
    public function test_the_name_notice_spells_out_every_rule_the_request_applies(): void
    {
        $hints = (new FieldHints(CustomerRequest::class))->for('name');

        $this->assertSame([
            __('Use at least :min characters.', ['min' => 4]),
            __('Use at most :max characters.', ['max' => MaxLength::string()]),
            __('Must be unique.'),
        ], $hints);
    }

    public function test_an_optional_field_only_mentions_the_rules_a_person_can_act_on(): void
    {
        $hints = (new FieldHints(CustomerRequest::class))->for('notes');

        $this->assertSame([
            __('Use at most :max characters.', ['max' => MaxLength::longText()]),
        ], $hints);
    }

    public function test_a_selector_only_offers_the_records_it_accepts_so_it_announces_nothing(): void
    {
        $hints = new FieldHints(ProjectRequest::class);

        $this->assertSame([], $hints->for('customer_id'));
        $this->assertTrue($hints->isRequired('customer_id'), 'the asterisk still marks it as asked for.');
    }

    public function test_a_date_notice_names_the_other_date_the_way_the_form_labels_it(): void
    {
        $hints = (new FieldHints(ProjectRequest::class, ['start_date' => 'Hasiera-data']))->for('end_date');

        $this->assertSame([
            __('Must be on or after :field.', ['field' => 'hasiera-data']),
        ], $hints);
    }

    public function test_a_date_can_be_required_only_together_with_another_one(): void
    {
        $hints = (new FieldHints(EpicRequest::class, ['end_date' => 'Amaiera-data']))->for('start_date');

        $this->assertSame([
            __('Required when :field is filled in.', ['field' => 'amaiera-data']),
        ], $hints);
    }

    public function test_the_end_date_of_an_epic_must_be_later_than_its_start_date(): void
    {
        $hints = (new FieldHints(EpicRequest::class, ['start_date' => 'Hasiera-data']))->for('end_date');

        $this->assertSame([
            __('Must be later than :field.', ['field' => 'hasiera-data']),
        ], $hints);
    }

    public function test_the_asterisk_marks_the_fields_that_are_always_asked_for(): void
    {
        $epic = new FieldHints(EpicRequest::class);

        $this->assertTrue($epic->isRequired('name'));
        $this->assertTrue($epic->isRequired('project_id'));
        $this->assertFalse($epic->isRequired('start_date'), 'required_with is not asked for every time.');
        $this->assertFalse($epic->isRequired('end_date'));
        $this->assertFalse($epic->isRequired('notes'));
    }

    public function test_a_field_the_request_does_not_validate_has_nothing_to_announce(): void
    {
        $hints = new FieldHints(EpicCommentRequest::class);

        $this->assertSame([], $hints->for('unknown_field'));
        $this->assertFalse($hints->isRequired('unknown_field'));
    }

    public function test_an_unlabelled_field_is_announced_with_a_readable_name(): void
    {
        $hints = (new FieldHints(ProjectRequest::class))->for('end_date');

        $this->assertContains(
            __('Must be on or after :field.', ['field' => 'start date']),
            $hints,
        );
    }
}
