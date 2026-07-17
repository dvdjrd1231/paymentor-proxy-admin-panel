<?php

namespace Paymenter\Extensions\Others\BrazilianRegistration\Support;

/**
 * CPF / CNPJ helpers: sanitising, checksum validation, and display masking.
 *
 * Pure functions, no framework dependencies — easy to unit test.
 */
class Documents
{
    /** Strip everything that isn't a digit. */
    public static function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    /**
     * Validate a Brazilian CPF (individual taxpayer id) by its check digits.
     */
    public static function isValidCpf(?string $value): bool
    {
        $cpf = self::digits($value);

        if (strlen($cpf) !== 11) {
            return false;
        }

        // Reject known invalid sequences (all identical digits).
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        // Two check digits, computed over the preceding digits.
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate a Brazilian CNPJ (company taxpayer id) by its check digits.
     */
    public static function isValidCnpj(?string $value): bool
    {
        $cnpj = self::digits($value);

        if (strlen($cnpj) !== 14) {
            return false;
        }

        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $weightsFirst = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weightsSecond = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $checkDigit = function (array $weights) use ($cnpj): int {
            $sum = 0;
            foreach ($weights as $i => $weight) {
                $sum += (int) $cnpj[$i] * $weight;
            }
            $remainder = $sum % 11;

            return $remainder < 2 ? 0 : 11 - $remainder;
        };

        if ((int) $cnpj[12] !== $checkDigit($weightsFirst)) {
            return false;
        }

        if ((int) $cnpj[13] !== $checkDigit($weightsSecond)) {
            return false;
        }

        return true;
    }

    /** Format 11 digits as CPF: 000.000.000-00 (best-effort). */
    public static function maskCpf(?string $value): string
    {
        $cpf = self::digits($value);
        if (strlen($cpf) !== 11) {
            return (string) $value;
        }

        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }

    /** Format 14 digits as CNPJ: 00.000.000/0000-00 (best-effort). */
    public static function maskCnpj(?string $value): string
    {
        $cnpj = self::digits($value);
        if (strlen($cnpj) !== 14) {
            return (string) $value;
        }

        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }
}
