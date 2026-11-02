<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsitePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsitePageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_update_public_homepage_content_and_seo_safely(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $content = $this->defaultContent('home');
        $content['hero_heading'] = '<script>alert(1)</script>';
        $payload = [
            'content' => $content,
            'meta_title' => 'Updated DSS homepage',
            'meta_description' => 'Updated search description for the public page.',
            'is_published' => '1',
        ];

        $response = $this->actingAs($administrator)->put(route('admin.website-pages.update', 'home'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('website_pages', ['slug' => 'home', 'meta_title' => 'Updated DSS homepage', 'is_published' => 1]);
        $this->get(route('home'))->assertOk()->assertSee($content['hero_heading'])->assertDontSee($content['hero_heading'], false);
    }

    public function test_unpublished_page_uses_the_safe_default_public_copy(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $content = $this->defaultContent('home');
        $content['hero_heading'] = 'Private draft heading';

        $this->actingAs($administrator)->put(route('admin.website-pages.update', 'home'), [
            'content' => $content,
            'meta_title' => 'Draft SEO title',
            'meta_description' => 'Draft description',
            'is_published' => '0',
        ])->assertSessionHasNoErrors();

        $this->get(route('home'))->assertOk()->assertDontSee('Private draft heading')->assertSee("Make room for what's next.");
    }

    public function test_non_administrator_cannot_manage_website_content(): void
    {
        $financeOfficer = User::factory()->create(['role' => 'finance_officer']);

        $this->actingAs($financeOfficer)->get(route('admin.website-pages.index'))->assertForbidden();
        $this->actingAs($financeOfficer)->put(route('admin.website-pages.update', 'home'), [])->assertForbidden();
        $this->assertDatabaseCount('website_pages', 0);
    }

    public function test_page_editor_rejects_unlisted_content_fields(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $content = $this->defaultContent('home');
        $content['unexpected'] = 'attempted extra field';

        $this->actingAs($administrator)->from(route('admin.website-pages.index'))
            ->put(route('admin.website-pages.update', 'home'), [
                'content' => $content,
                'meta_title' => 'Title',
                'meta_description' => 'Description',
                'is_published' => '1',
            ])
            ->assertSessionHasErrors('content');

        $this->assertDatabaseCount('website_pages', 0);
    }

    public function test_public_about_and_contact_pages_render_persisted_content(): void
    {
        $about = $this->defaultContent('about');
        $contact = $this->defaultContent('contact');
        $about['mission'] = 'A persisted mission statement.';
        $contact['phone'] = '+254 711 000 111';
        WebsitePage::factory()->create(['slug' => 'about', 'content' => $about, 'meta_title' => 'Our mission', 'meta_description' => 'About DSS', 'title' => 'About us']);
        WebsitePage::factory()->create(['slug' => 'contact', 'content' => $contact, 'meta_title' => 'Reach us', 'meta_description' => 'Contact DSS', 'title' => 'Contact us']);

        $this->get(route('about'))->assertOk()->assertSee('A persisted mission statement.')->assertSee('Our mission');
        $this->get(route('contact'))->assertOk()->assertSee('+254 711 000 111')->assertSee('tel:+254711000111')->assertSee('Reach us');
    }

    /** @return array<string, string> */
    private function defaultContent(string $slug): array
    {
        return collect(config("website_pages.pages.{$slug}.fields"))
            ->mapWithKeys(fn (array $field, string $key): array => [$key => $field['default']])
            ->all();
    }
}
