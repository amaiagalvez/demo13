<?php

namespace Tests\Unit\Epics;

use Tests\TestCase;
use App\Http\Requests\EpicListRequest;
use Illuminate\Support\Facades\Validator;

class EpicListRequestTest extends TestCase
{
    public function test_search_is_optional(): void
    {
        $this->assertTrue($this->validator([])->passes());
    }

    public function test_search_must_be_a_string(): void
    {
        $this->assertTrue($this->validator(['search' => ['Ane']])->fails());
    }

    public function test_search_cannot_exceed_255_characters(): void
    {
        $this->assertTrue($this->validator(['search' => str_repeat('a', 256)])->fails());
    }

    public function test_search_is_trimmed(): void
    {
        $request = EpicListRequest::create('/', 'GET', ['search' => '  Ane  ']);

        $this->assertSame('Ane', $request->search());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validator(array $data): \Illuminate\Validation\Validator
    {
        $request = EpicListRequest::create('/', 'GET', $data);

        return Validator::make($data, $request->rules());
    }
}
