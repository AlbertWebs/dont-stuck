<?php

namespace App\Http\Controllers;

use App\Models\ContactEnquiry;
use App\Models\LoanProduct;
use App\Services\WebsitePageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicSiteController extends Controller
{
    public function home(WebsitePageCatalog $pages): View
    {
        return view('public.home', [
            'products' => LoanProduct::query()->where('is_active', true)->where(fn ($query) => $query->whereNull('repayment_months')->orWhereBetween('repayment_months', [1, 3]))->orderBy('name')->get(),
            'pageContent' => $pages->publicContent('home'),
        ]);
    }

    public function about(WebsitePageCatalog $pages): View
    {
        return view('public.about', ['pageContent' => $pages->publicContent('about')]);
    }

    public function products(): View
    {
        return view('public.products', ['products' => LoanProduct::query()->where('is_active', true)->where(fn ($query) => $query->whereNull('repayment_months')->orWhereBetween('repayment_months', [1, 3]))->orderBy('name')->get()]);
    }

    public function contact(WebsitePageCatalog $pages): View
    {
        return view('public.contact', ['pageContent' => $pages->publicContent('contact')]);
    }

    public function submitContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ]);

        ContactEnquiry::create($data);

        return back()->with('success', 'Thank you. Your enquiry has been sent to our team.');
    }
}
