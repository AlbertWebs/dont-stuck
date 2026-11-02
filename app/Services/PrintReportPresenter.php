<?php

namespace App\Services;

class PrintReportPresenter
{
    /** @param array<string, mixed> $filters */
    public function subtitle(array $filters): string
    {
        $parts = [];
        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $parts[] = 'Dates: '.($filters['date_from'] ?? 'earliest').' to '.($filters['date_to'] ?? 'today');
        }
        if (! empty($filters['product_name'])) {
            $parts[] = 'Product: '.$filters['product_name'];
        }
        if (! empty($filters['borrower_name'])) {
            $parts[] = 'Borrower: '.$filters['borrower_name'];
        }

        return $parts === [] ? 'All records available to your role' : implode(' · ', $parts);
    }
}
