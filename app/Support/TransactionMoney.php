<?php

namespace App\Support;

use InvalidArgumentException;
use OverflowException;

final class TransactionMoney
{
    private const MAX_LINE_SUBTOTAL_MINOR = 99_999_999_999_999;

    public static function toMinorUnits(string|int|float $amount): int
    {
        $amount = (string) $amount;

        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $amount) !== 1) {
            throw new InvalidArgumentException('Nilai uang harus memiliki maksimal dua angka desimal.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';

        $minorUnits = ltrim($whole.str_pad($fraction, 2, '0'), '0') ?: '0';
        $maxMinorUnits = (string) PHP_INT_MAX;
        if (strlen($minorUnits) > strlen($maxMinorUnits)
            || (strlen($minorUnits) === strlen($maxMinorUnits) && strcmp($minorUnits, $maxMinorUnits) > 0)) {
            throw new OverflowException('Nilai uang melebihi batas perhitungan.');
        }

        return (int) $minorUnits;
    }

    public static function lineSubtotalMinorUnits(int $unitPriceMinorUnits, int $quantity): int
    {
        if ($quantity < 1 || $unitPriceMinorUnits < 0) {
            throw new InvalidArgumentException('Harga dan kuantitas transaksi tidak valid.');
        }

        if ($unitPriceMinorUnits > 0 && $quantity > intdiv(self::MAX_LINE_SUBTOTAL_MINOR, $unitPriceMinorUnits)) {
            throw new OverflowException('Subtotal item melebihi batas penyimpanan.');
        }

        return $unitPriceMinorUnits * $quantity;
    }

    public static function fromMinorUnits(int $amount): string
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Nilai uang tidak boleh negatif.');
        }

        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function format(string|int|float $amount): string
    {
        $minorUnits = self::toMinorUnits($amount);

        return number_format(intdiv($minorUnits, 100), 0, ',', '.')
            .','.str_pad((string) ($minorUnits % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  iterable<string|int|float>  $amounts
     */
    public static function sum(iterable $amounts): string
    {
        $total = 0;

        foreach ($amounts as $amount) {
            $minorUnits = self::toMinorUnits($amount);

            if ($minorUnits > PHP_INT_MAX - $total) {
                throw new OverflowException('Total transaksi melebihi batas perhitungan.');
            }

            $total += $minorUnits;
        }

        return self::fromMinorUnits($total);
    }
}
