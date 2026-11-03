<?php

namespace App\Support;

class KenyanMoneyWords
{
    /**
     * Convert a non-negative KSh amount into the wording used in the signed application agreement.
     */
    public function spell(string|int $amount): string
    {
        $cents = Money::toCents((string) $amount);
        $shillings = intdiv($cents, 100);
        $fraction = $cents % 100;
        $words = ucfirst($this->spellNumber($shillings)).' Kenya '.($shillings === 1 ? 'Shilling' : 'Shillings');

        if ($fraction > 0) {
            $words .= ' and '.$this->spellNumber($fraction).' '.($fraction === 1 ? 'Cent' : 'Cents');
        }

        return $words.' only';
    }

    private function spellNumber(int $number): string
    {
        $ones = [
            'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
            'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen',
            'eighteen', 'nineteen',
        ];
        $tens = [2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty', 6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety'];

        if ($number < 20) {
            return $ones[$number];
        }

        if ($number < 100) {
            return $tens[intdiv($number, 10)].($number % 10 > 0 ? '-'.$ones[$number % 10] : '');
        }

        foreach ([1_000_000_000 => 'billion', 1_000_000 => 'million', 1_000 => 'thousand', 100 => 'hundred'] as $scale => $label) {
            if ($number >= $scale) {
                $whole = intdiv($number, $scale);
                $remainder = $number % $scale;
                $joiner = $scale === 100 && $remainder > 0 ? ' and ' : ' ';

                return $this->spellNumber($whole).' '.$label.($whole > 1 && $scale !== 100 ? 's' : '').($remainder > 0 ? $joiner.$this->spellNumber($remainder) : '');
            }
        }

        return '';
    }
}
