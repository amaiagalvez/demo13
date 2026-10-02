<?php

namespace Tests\Browser\Epics;

use App\Models\Epic;
use App\Models\User;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Support\Str;

class EpicFormTest extends DuskTestCase
{
    public function test_end_date_picker_starts_the_day_after_the_start_date(): void
    {
        $user = User::factory()->create();
        $epic = Epic::factory()->create([
            'name' => 'Dusk dates '.Str::uuid()->toString(),
            'start_date' => '2026-12-31',
            'end_date' => '2027-01-15',
        ]);

        $this->browse(function (Browser $browser) use ($user, $epic): void {
            $browser->loginAs($user)
                ->visit('/epics?search='.urlencode($epic->name))
                ->click('[data-test="epic-edit-'.$epic->id.'"]')
                ->waitFor('dialog[open] [data-test="epic-end-date"]')
                ->assertAttribute('dialog[open] [data-test="epic-end-date"]', 'min', '2027-01-01');
        });
    }
}
