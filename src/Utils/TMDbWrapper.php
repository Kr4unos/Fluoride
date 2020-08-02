<?php

namespace App\Utils;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TMDbWrapper
{
    private static $_api_url_tmdb = 'https://api.themoviedb.org/3/';

    public static $api_poster_url = 'https://image.tmdb.org/t/p/original';
    public static $api_thumbnail_url = 'https://image.tmdb.org/t/p/w300';

    public static function findFromImdbId(string $imdbId)
    {
        $params = [
            'api_key' => Constants::TMDB_API_KEY,
            'language' => 'fr-FR',
            'external_source' => 'imdb_id'
        ];

        $query_url = self::$_api_url_tmdb . 'find/' . $imdbId . '?' . http_build_query($params);
        return self::search($query_url);
    }

    public static function getTmdbIdFromImdbIdForMovie(string $imdbId)
    {
        $result = self::findFromImdbId($imdbId);

        if(isset($result['movie_results'])
            && !empty($result['movie_results'])
            && isset($result['movie_results'][0]['id']))
        {
            return $result['movie_results'][0]['id'];
        }

        return null;
    }

    public static function getTmdbIdFromImdbIdForShow(string $imdbId)
    {
        $result = self::findFromImdbId($imdbId);

        if(isset($result['tv_results'])
            && !empty($result['tv_results'])
            && isset($result['tv_results'][0]['id']))
        {
            return $result['tv_results'][0]['id'];
        }

        return null;
    }

    public static function search(string $query)
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