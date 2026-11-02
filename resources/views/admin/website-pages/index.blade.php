@extends('layouts.admin')

@section('title', 'Website content')
@section('content')
<div class="admin-page-heading"><div><span class="eyebrow">Content management</span><h1>Website pages</h1><p>Edit the public page copy, contact details and search metadata. Text is escaped on display.</p></div><a class="button button-outline-dark" href="{{ route('home') }}" target="_blank" rel="noopener">Preview public site ↗</a></div>
<div class="website-pages-grid">
    @foreach ($pages as $page)
        <section class="panel product-admin-card">
            <div class="product-admin-heading"><div><span class="product-number">PUBLIC PAGE</span><h2>{{ $page['title'] }}</h2></div><span class="status-badge {{ $page['is_published'] ? 'status-active' : 'status-draft' }}">{{ $page['is_published'] ? 'Published' : 'Unpublished' }}</span></div>
            <form method="post" action="{{ route('admin.website-pages.update', $page['slug']) }}">
                @csrf @method('PUT')
                <div class="field-grid">
                    @foreach ($page['fields'] as $key => $field)
                        <div class="field {{ $field['type'] === 'textarea' ? 'field-full' : '' }}">
                            <label for="{{ $page['slug'] }}-{{ $key }}">{{ $field['label'] }}</label>
                            @if ($field['type'] === 'textarea')
                                <textarea id="{{ $page['slug'] }}-{{ $key }}" name="content[{{ $key }}]" rows="3" required>{{ old('content.'.$key, $page['content'][$key]) }}</textarea>
                            @else
                                <input id="{{ $page['slug'] }}-{{ $key }}" name="content[{{ $key }}]" value="{{ old('content.'.$key, $page['content'][$key]) }}" required>
                            @endif
                            @error('content.'.$key)<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                    @endforeach
                    <div class="field"><label for="{{ $page['slug'] }}-meta-title">SEO title</label><input id="{{ $page['slug'] }}-meta-title" name="meta_title" value="{{ old('meta_title', $page['meta_title']) }}" maxlength="160" required></div>
                    <div class="field field-full"><label for="{{ $page['slug'] }}-meta-description">SEO description</label><textarea id="{{ $page['slug'] }}-meta-description" name="meta_description" rows="2" maxlength="320" required>{{ old('meta_description', $page['meta_description']) }}</textarea></div>
                    <label class="check-row field-full"><input type="hidden" name="is_published" value="0"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $page['is_published']))><span>Publish this content on the public website</span></label>
                </div>
                <div class="product-admin-footer"><span>Page key: {{ $page['slug'] }}</span><button class="button button-dark button-small" type="submit">Save page</button></div>
            </form>
        </section>
    @endforeach
</div>
@endsection
