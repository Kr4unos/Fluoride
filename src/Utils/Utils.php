<?php


namespace App\Utils;

class Utils
{
    public static function removeSpecialChars(string $string) : string
    {
        return preg_replace('/[^A-Za-z0-9\-]/', '', str_replace('/', ' - ', $string));
    }

    public static function isNotNullOrEqualsTo(?string $string, string $notEquals) : bool
    {
        return $string != null && !empty($string) && $string != $notEquals;
    }

    public static function isNotNullOrUnknown(?string $string) : bool
    {
        return self::isNotNullOrEqualsTo($string, "N/A");
    }

    public static function isNotNullOrDefaultImage(?string $string) : bool
    {
        return self::isNotNullOrEqualsTo($string, Constants::DEFAULT_IMAGE_NAME);
    }

    public static function generateRandomImageName() : string
    {
        return md5(microtime(true)) . ".jpg";
    }

    public static function downloadFileTo(string $destination_path, string $url) : string
    {
        $fileName = self::generateRandomImageName();
        try
        {
            file_put_contents( $destination_path . $fileName, file_get_contents($url));
        }
        catch (\Exception $e)
        {
            return Constants::DEFAULT_IMAGE_NAME;
        }

        return $fileName;
    }

    public static function removeFile(string $path) : bool
    {
        if(file_exists($path))
            return unlink($path);
        return true;
    }
}