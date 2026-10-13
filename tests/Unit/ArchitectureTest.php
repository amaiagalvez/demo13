<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use ReflectionClass;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

class ArchitectureTest extends TestCase
{
    public function test_user_accounts_are_soft_deletable_without_a_resource_policy(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(User::class));
        $this->assertSame([], (new ReflectionClass(User::class))->getAttributes(UsePolicy::class));
    }

    public function test_raw_blade_echo_is_limited_to_the_two_factor_qr_code(): void
    {
        $rawEchoesByFile = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            preg_match_all('/\{!!.*?!!\}/s', (string) file_get_contents($file->getPathname()), $matches);

            if ($matches[0] !== []) {
                $rawEchoesByFile[$file->getPathname()] = count($matches[0]);
            }
        }

        $this->assertSame([
            resource_path('views/pages/settings/⚡two-factor-setup-modal.blade.php') => 1,
        ], $rawEchoesByFile);
    }
}
