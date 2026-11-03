<?php

namespace App\Services;

use App\Models\LoanTermsSetting;
use App\Support\Money;

class LoanRepaymentCalculator
{
    /**
     * @return array{
     *     principal: string,
     *     term_months: int,
     *     interest_rate: string,
     *     interest_basis: string,
     *     processing_fee: string,
     *     legal_fee: string,
     *     fees_withheld: string,
     *     net_disbursement: string,
     *     total_interest: string,
     *     total_repayment: string,
     *     total_cost: string,
     *     installments: array<int, array{number: int, principal: string, interest: string, total: string}>
     * }
     */
    public function calculate(string|int $principal, int $termMonths, LoanTermsSetting $settings): array
    {
        $principalCents = Money::toCents($principal);
        $basePrincipalCents = intdiv($principalCents, $termMonths);
        $outstandingPrincipalCents = $principalCents;
        $totalInterestCents = 0;
        $installments = [];

        for ($installmentNumber = 1; $installmentNumber <= $termMonths; $installmentNumber++) {
            $installmentPrincipalCents = $installmentNumber === $termMonths
                ? $outstandingPrincipalCents
                : $basePrincipalCents;
            $interestBaseCents = $settings->interest_basis === 'flat_monthly_equal_principal'
                ? $principalCents
                : $outstandingPrincipalCents;
            $interestCents = Money::percentage($interestBaseCents, (string) $settings->interest_rate);

            $installments[] = [
                'number' => $installmentNumber,
                'principal' => Money::fromCents($installmentPrincipalCents),
                'interest' => Money::fromCents($interestCents),
                'total' => Money::fromCents($installmentPrincipalCents + $interestCents),
            ];

            $outstandingPrincipalCents -= $installmentPrincipalCents;
            $totalInterestCents += $interestCents;
        }

        $processingFeeCents = Money::percentage($principalCents, (string) $settings->processing_fee_rate);
        $legalFeeCents = Money::toCents((string) $settings->legal_fee_amount);
        $feesWithheldCents = $processingFeeCents + $legalFeeCents;

        return [
            'principal' => Money::fromCents($principalCents),
            'term_months' => $termMonths,
            'interest_rate' => (string) $settings->interest_rate,
            'interest_basis' => $settings->interest_basis,
            'processing_fee' => Money::fromCents($processingFeeCents),
            'legal_fee' => Money::fromCents($legalFeeCents),
            'fees_withheld' => Money::fromCents($feesWithheldCents),
            'net_disbursement' => Money::fromCents(max(0, $principalCents - $feesWithheldCents)),
            'total_interest' => Money::fromCents($totalInterestCents),
            'total_repayment' => Money::fromCents($principalCents + $totalInterestCents),
            'total_cost' => Money::fromCents($totalInterestCents + $feesWithheldCents),
            'installments' => $installments,
        ];
    }
}
