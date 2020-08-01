<?php


namespace App\Utils;


class Utils
{
    public static function removeSpecialChars(string $string)
    {
        return preg_replace('/[^A-Za-z0-9\-]/', '', str_replace('/', ' - ', $string));
    }
}