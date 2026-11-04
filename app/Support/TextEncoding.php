<?php
namespace App\Support;

class TextEncoding
{
    /**
     * Ensure text is valid UTF-8 for storage and display.
     *
     * Uploaded evidence often arrives in a legacy single-byte encoding
     * (Windows editors default to Windows-1252). Storing those bytes as-is
     * renders as mojibake (e.g. â€” instead of —) in a UTF-8 interface.
     */
    public static function toUtf8(string $text): string
    {
        if ($text === '' || preg_match('//u', $text) === 1) {
            return $text;
        }
        foreach (['Windows-1252', 'ISO-8859-1'] as $from) {
            $converted = @mb_convert_encoding($text, 'UTF-8', $from);
            if (is_string($converted) && $converted !== '' && preg_match('//u', $converted) === 1) {
                return $converted;
            }
        }

        return (string) mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
}
