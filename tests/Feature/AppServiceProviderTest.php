<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AppServiceProviderTest extends TestCase
{
    public function test_production_requires_strong_passwords(): void
    {
        // `uncompromised()` asks an external service; a faked empty answer means "not leaked".
        Http::fake();
        $this->app['env'] = 'production';

        $rule = Password::default();

        $this->assertTrue(Validator::make(['password' => 'weakpassword'], ['password' => $rule])->fails());
        $this->assertFalse(Validator::make(['password' => 'Str0ng!Passphrase'], ['password' => $rule])->fails());
    }

    public function test_other_environments_keep_the_framework_password_default(): void
    {
        $this->assertFalse(Validator::make(['password' => 'weakpassword'], ['password' => Password::default()])->fails());
    }
}
