<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BorrowerController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasAnyRole(['administrator', 'loan_officer', 'finance_officer', 'management']), 403);

        $query = Borrower::query();

        if ($request->user()->role === 'loan_officer') {
            $query->whereHas('applications', fn ($applications) => $applications->where('assigned_user_id', $request->user()->id));
        } elseif ($request->user()->role === 'finance_officer') {
            $query->whereHas('loanAccounts');
        }

        $borrowers = $query
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('full_name', 'like', $term)->orWhere('national_id', 'like', $term)->orWhere('phone', 'like', $term);
                });
            })
            ->withCount('applications')
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.borrowers.index', ['borrowers' => $borrowers]);
    }
}
