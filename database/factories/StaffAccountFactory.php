<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;

/** @extends Factory<StaffAccount> */
final class StaffAccountFactory extends Factory
{
    protected $model = StaffAccount::class;

    protected static ?string $password;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'role' => SystemStaffRole::Administrator,
            'permission_overrides' => [],
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function withRole(SystemStaffRole $role): static
    {
        return $this->state(fn (array $attributes): array => ['role' => $role]);
    }
}
