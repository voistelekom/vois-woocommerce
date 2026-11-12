<?php
defined('ABSPATH') || exit;

/** Turkish mobile number helpers. SMS goes only to TR mobiles (905xxxxxxxxx). */
final class Vois_WC_Phone
{
    /** Returns 905xxxxxxxxx, or null when the input is not a Turkish mobile number. */
    public static function normalize($raw)
    {
        $d = preg_replace('/\D+/', '', (string) $raw);
        if (strpos($d, '0090') === 0) {
            $d = substr($d, 2);
        }
        if (strlen($d) === 11 && $d[0] === '0') {
            $d = '9' . $d;
        } elseif (strlen($d) === 10) {
            $d = '90' . $d;
        }

        return preg_match('/^905\d{9}$/', $d) ? $d : null;
    }

    /** Comma, semicolon or newline separated list → unique valid numbers, in order. */
    public static function parse_list($raw)
    {
        $out = [];
        foreach (preg_split('/[,;\r\n]+/', (string) $raw) as $part) {
            $n = self::normalize($part);
            if ($n !== null && ! in_array($n, $out, true)) {
                $out[] = $n;
            }
        }

        return $out;
    }
}
