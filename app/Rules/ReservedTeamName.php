<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A team's slug is the first URL segment (/{slug}/dashboard), so a team must not
 * take a slug that collides with an application route or a public asset path.
 */
class ReservedTeamName implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $slug = Str::slug($value);

        if ($slug === '' || in_array($slug, $this->reserved(), true)) {
            $fail(__('This team name is reserved or invalid.'));
        }
    }

    /**
     * @return array<int, string>
     */
    private function reserved(): array
    {
        $routePrefixes = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => explode('/', $route->uri)[0])
            ->reject(fn (string $segment) => $segment === '' || str_contains($segment, '{'));

        $publicPaths = collect(glob(public_path('*')) ?: [])
            ->map(fn (string $path) => pathinfo($path, PATHINFO_FILENAME));

        return $routePrefixes->merge($publicPaths)
            ->merge(['api', 'admin', 'storage', 'build', 'vendor'])
            ->map(fn (string $name) => Str::slug($name))
            ->unique()
            ->values()
            ->all();
    }
}
