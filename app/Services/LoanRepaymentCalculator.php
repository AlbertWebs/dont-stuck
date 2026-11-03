<?php

namespace App\Services;

use App\Models\LoanTermsSetting;
use App\Support\KenyanMoneyWords;
use App\Support\Money;

class LoanRepaymentCalculator
{
    public function __construct(private readonly KenyanMoneyWords $moneyWords) {}

    /**
     * @return array{
     *     principal: string,
     *     principal_words: string,
     *     term_months: int,
     *     interest_rate: string,
     *     interest_basis: string,
     *     processing_fee_rate: string,
     *     processing_fee: string,
     *     legal_fee: string,
     *     fees_withheld: string,
     *     net_disbursement: string,
     *     net_disbursement_words: string,
     *     total_interest: string,
     *     total_repayment: string,
     *     total_repayment_words: string,
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

        $principal = Money::fromCents($principalCents);
        $netDisbursement = Money::fromCents(max(0, $principalCents - $feesWithheldCents));
        $totalRepayment = Money::fromCents($principalCents + $totalInterestCents);

        return [
            'principal' => $principal,
            'principal_words' => $this->moneyWords->spell($principal),
            'term_months' => $termMonths,
            'interest_rate' => (string) $settings->interest_rate,
            'interest_basis' => $settings->interest_basis,
            'processing_fee_rate' => (string) $settings->processing_fee_rate,
            'processing_fee' => Money::fromCents($processingFeeCents),
            'legal_fee' => Money::fromCents($legalFeeCents),
            'fees_withheld' => Money::fromCents($feesWithheldCents),
            'net_disbursement' => $netDisbursement,
            'net_disbursement_words' => $this->moneyWords->spell($netDisbursement),
            'total_interest' => Money::fromCents($totalInterestCents),
            'total_repayment' => $totalRepayment,
            'total_repayment_words' => $this->moneyWords->spell($totalRepayment),
            'total_cost' => Money::fromCents($totalInterestCents + $feesWithheldCents),
            'installments' => $installments,
        ];
    }
}
