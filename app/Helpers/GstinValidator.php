<?php

namespace App\Helpers;

/**
 * Validates Indian GSTIN (Goods and Services Tax Identification Number).
 *
 * GSTIN format: 15 alphanumeric characters
 * - Positions 1-2: State code (01-38)
 * - Positions 3-12: PAN (5 letters + 4 digits + 1 letter)
 * - Position 13: Entity number (0-9 or A-Z)
 * - Position 14: Always 'Z'
 * - Position 15: Check digit (0-9 or A-Z)
 *
 * Uses regex-based format validation for reliability across all Indian GSTINs.
 *
 * @see https://www.gst.gov.in/
 */
class GstinValidator
{
    /**
     * Indian GSTIN format regex (15 chars):
     * - [0-9]{2} = State code
     * - [A-Z]{5} = First 5 chars of PAN (letters)
     * - [0-9]{4} = Next 4 chars of PAN (digits)
     * - [A-Z]{1} = Last char of PAN (letter)
     * - [A-Z0-9]{1} = Entity number
     * - Z = Fixed character
     * - [A-Z0-9]{1} = Check digit
     */
    private const GSTIN_REGEX = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[A-Z0-9]{1}Z[A-Z0-9]{1}$/';

    /**
     * Normalize GSTIN: uppercase, remove spaces and hyphens.
     */
    public static function normalize(string $gstin): string
    {
        $gstin = strtoupper(trim($gstin));
        $gstin = str_replace([' ', '-'], '', $gstin);

        return $gstin;
    }

    /**
     * Check if the given string is a valid Indian GSTIN.
     * Uses regex-based format validation.
     */
    public static function isValid(string $gstin): bool
    {
        $gstin = self::normalize($gstin);

        return strlen($gstin) === 15 && (bool) preg_match(self::GSTIN_REGEX, $gstin);
    }

    /**
     * Extract 2-digit state code from a valid GSTIN (no validation).
     */
    public static function getStateCode(string $gstin): string
    {
        $gstin = self::normalize($gstin);

        return strlen($gstin) >= 2 ? substr($gstin, 0, 2) : '';
    }

    /**
     * Extract PAN (positions 3-12) from a valid GSTIN (no validation).
     */
    public static function getPan(string $gstin): string
    {
        $gstin = self::normalize($gstin);

        return strlen($gstin) >= 12 ? substr($gstin, 2, 10) : '';
    }
}
