<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebsitePage;
use App\Services\AuditLogger;
use App\Services\WebsitePageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebsitePageController extends Controller
{
    public function index(Request $request, WebsitePageCatalog $catalog): View
    {
        abort_unless($request->user()->role === 'administrator', 403);

        return view('admin.website-pages.index', ['pages' => $catalog->managementPages()]);
    }

    public function update(Request $request, string $pageSlug, WebsitePageCatalog $catalog, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $definition = $catalog->definition($pageSlug);
        abort_if($definition === [], 404);

        $rules = [
            'content' => ['required', 'array:'.implode(',', array_keys($definition['fields']))],
            'meta_title' => ['required', 'string', 'max:160'],
            'meta_description' => ['required', 'string', 'max:320'],
            'is_published' => ['required', 'boolean'],
        ];

        foreach ($definition['fields'] as $key => $field) {
            $rules['content.'.$key] = ['required', 'string', 'max:'.($field['type'] === 'textarea' ? 2000 : 160)];
        }

        if ($pageSlug === 'contact') {
            $rules['content.email'] = ['required', 'email', 'max:255'];
            $rules['content.phone'] = ['required', 'string', 'max:32'];
        }

        $data = $request->validate($rules);
        $page = WebsitePage::query()->updateOrCreate(
            ['slug' => $pageSlug],
            [
                'title' => $definition['title'],
                'content' => $data['content'],
                'meta_title' => $data['meta_title'],
                'meta_description' => $data['meta_description'],
                'is_published' => $data['is_published'],
            ],
        );

        $auditLogger->record($request->user(), 'website_page.updated', $page, [
            'slug' => $pageSlug,
            'published' => $page->is_published,
            'changed_fields' => array_keys($data['content']),
        ], $request);

        return back()->with('success', $definition['title'].' content saved.');
    }
}
