<?php

namespace Tests\Unit\Users;

use Tests\TestCase;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;

class UserTest extends TestCase
{
    public function test_only_declared_user_attributes_are_mass_assignable(): void
    {
        $user = new User;

        $user->fill([
            'name' => 'Ane Bezeroa',
            'email' => 'ane@example.test',
            'password' => 'sekretua',
            'notes' => 'Admin dela familia',
            'id' => 42,
            'active' => false,
        ]);

        $this->assertSame('Ane Bezeroa', $user->name);
        $this->assertSame('ane@example.test', $user->email);
        // Hashed on the way in, so only its presence is asserted: the cast is what turns it into a hash.
        $this->assertArrayHasKey('password', $user->getAttributes());
        $this->assertTrue($user->active);
        $this->assertFalse($user->wasRecentlyCreated);
        $this->assertArrayNotHasKey('id', $user->getAttributes());
    }

    public function test_the_secrets_are_hidden_from_the_serialized_user(): void
    {
        $user = new User;

        $user->forceFill([
            'name' => 'Ane Bezeroa',
            'password' => 'sekretua',
            'remember_token' => 'remember-me',
            'two_factor_secret' => 'secret',
        ]);

        $serialized = $user->toArray();

        $this->assertArrayNotHasKey('password', $serialized);
        $this->assertArrayNotHasKey('remember_token', $serialized);
        $this->assertArrayNotHasKey('two_factor_secret', $serialized);
        $this->assertSame('Ane Bezeroa', $serialized['name'] ?? null);
    }

    /**
     * @param  non-empty-string  $name
     */
    #[DataProvider('names')]
    public function test_initials_name_the_user_in_the_avatar(string $name, string $expected): void
    {
        $user = new User;

        $user->name = $name;

        $this->assertSame($expected, $user->initials());
    }

    /**
     * @return array<string, array{0: non-empty-string, 1: non-empty-string}>
     */
    public static function names(): array
    {
        return [
            'one word' => ['Ane', 'A'],
            'two words' => ['Ane Bezeroa', 'AB'],
            'three words keep the outer ones' => ['Jean Luc Picard', 'JP'],
            'hyphenated first name counts once' => ['Jean-Luc Picard', 'JP'],
        ];
    }
}
