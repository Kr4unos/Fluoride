<?php

namespace App\Utils;

use const App\Controller\TMDB_API_KEY;

class TMDbWrapper
{
    private static $_api_key = TMDB_API_KEY;
    private static $_api_url_tmdb = 'https://api.themoviedb.org/3/';
    public static $api_poster_url = 'https://image.tmdb.org/t/p/original';
    public static $api_thumbnail_url = 'https://image.tmdb.org/t/p/w300';

    public static function findFromImdbId($imdbID)
    {
        $params = [
            'api_key' => self::$_api_key,
            'language' => 'fr-FR',
            'external_source' => 'imdb_id'
        ];

        $query_url = self::$_api_url_tmdb . 'find/' . $imdbID . '?' . http_build_query($params);
        return self::search($query_url);
    }

    public static function search($query)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $query);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }
}