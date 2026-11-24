<?php

namespace Database\Factories;

use App\Enums\DocumentFormat;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The document belongs to the workspace of its uploader.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true).'.md';

        return [
            'user_id' => User::factory(),
            'tenant_id' => fn (array $attributes) => User::findOrFail($attributes['user_id'])->tenant_id,
            'name' => $name,
            'format' => DocumentFormat::Markdown,
            'size' => fake()->numberBetween(1, 100_000),
            'sha256' => hash('sha256', fake()->unique()->uuid()),
            'path' => fn (array $attributes) => "documents/{$attributes['tenant_id']}/".Str::lower((string) Str::ulid()).'.md',
        ];
    }
}
