<?php

namespace App\Services;

use App\Models\WebsitePage;

class WebsitePageCatalog
{
    /** @return array<string, mixed> */
    public function definition(string $slug): array
    {
        return config("website_pages.pages.{$slug}", []);
    }

    /** @return array<string, mixed> */
    public function publicContent(string $slug): array
    {
        $definition = $this->definition($slug);
        $defaults = collect($definition['fields'] ?? [])->mapWithKeys(
            fn (array $field, string $key): array => [$key => $field['default']],
        )->all();
        $page = WebsitePage::query()->where('slug', $slug)->where('is_published', true)->first();

        return [
            'content' => array_replace($defaults, $page?->content ?? []),
            'meta_title' => $page?->meta_title ?? $definition['meta_title'] ?? '',
            'meta_description' => $page?->meta_description ?? $definition['meta_description'] ?? '',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function managementPages(): array
    {
        $records = WebsitePage::query()->get()->keyBy('slug');

        return collect(config('website_pages.pages', []))->map(function (array $definition, string $slug) use ($records): array {
            $page = $records->get($slug);
            $defaults = collect($definition['fields'])->mapWithKeys(
                fn (array $field, string $key): array => [$key => $field['default']],
            )->all();

            return [
                'slug' => $slug,
                'title' => $definition['title'],
                'fields' => $definition['fields'],
                'content' => array_replace($defaults, $page?->content ?? []),
                'meta_title' => $page?->meta_title ?? $definition['meta_title'],
                'meta_description' => $page?->meta_description ?? $definition['meta_description'],
                'is_published' => $page?->is_published ?? true,
            ];
        })->values()->all();
    }
}
