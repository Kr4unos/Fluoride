<?php

namespace App\Controller;

use App\Entity\Game;
use App\Entity\Movie;
use App\Entity\OldMovie;
use App\Entity\TVEpisode;
use App\Entity\TVSeason;
use App\Entity\TVShow;
use App\Form\SearchType;
use App\Utils\TMDbWrapper;
use App\Utils\Utils;
use Exception;
use Rooxie\Exception\ApiErrorException;
use Rooxie\Exception\IncorrectImdbIdException;
use Rooxie\Exception\InvalidApiKeyException;
use Rooxie\Exception\InvalidResponseException;
use Rooxie\Exception\MovieNotFoundException;
use Rooxie\OMDb;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use VfacTmdb\Factory;
use VfacTmdb\Item;

const TMDB_API_KEY = "5cc5b11fa6bc7c0c39aa2b4807e8602b";
const OMDB_API_KEY = "5144452c";

class MainController extends AbstractController
{
    private $tmdbApi;
    private $omdbApi;

    public function __construct()
    {
        $this->tmdbApi = Factory::create()->getTmdb(TMDB_API_KEY);
        $this->omdbApi = new OMDb(OMDB_API_KEY);
    }

    /**
     * @Route("/", name="index")
     */
    public function index()
    {
        $em = $this->getDoctrine()->getManager();

        $totalMovies = $em->getRepository(Movie::class)->createQueryBuilder('m')->select('count(m.id)')->getQuery()->getSingleScalarResult();
        $totalShows = $em->getRepository(TVShow::class)->createQueryBuilder('s')->select('count(s.id)')->getQuery()->getSingleScalarResult();
        $totalGames = 0;//$em->getRepository(Game::class)->createQueryBuilder('g')->select('count(g.id)')->getQuery()->getSingleScalarResult();

        $seenMovies = $em->getRepository(Movie::class)->createQueryBuilder('m')->select('count(m.id)')->where('m.seen = 1')->getQuery()->getSingleScalarResult();
        $seenShows = $em->getRepository(TVShow::class)->createQueryBuilder('s')->select('count(s.id)')->where('s.seen = 1')->getQuery()->getSingleScalarResult();

        $latestMovies = $em->getRepository(Movie::class)->findBy([], ['updated_at' => 'desc'], 10);
        $latestShows = $em->getRepository(TVShow::class)->findBy([], ['updated_at' => 'desc'], 10);

        return $this->render('main/index.html.twig', [
            'totalMovies' => $totalMovies,
            'totalShows' => $totalShows,
            'totalGames' => $totalGames,
            'seenMovies' => $seenMovies,
            'seenShows' => $seenShows,
            'latestMovies' => $latestMovies,
            'latestShows' => $latestShows
        ]);
    }

    /**
     * @Route("/movies", name="movies")
     */
    public function movies()
    {
        $movies = $this->getDoctrine()->getRepository(Movie::class)->findBy([], ['release_date' => 'desc'], 1000);

        return $this->render('movies/movies.html.twig', [
            'movies' => $movies
        ]);
    }

    /**
     * @Route("/movie/{id}", name="movie")
     * @param int $id
     * @return Response
     */
    public function movie(int $id)
    {
        $movie = $this->getDoctrine()->getRepository(Movie::class)->find($id);

        if($movie == null)
            throw new NotFoundHttpException("This movie does not exist");

        return $this->render('movies/movie.html.twig', [
            'movie' => $movie,
        ]);
    }

    /**
     * @Route("/movies/add/{imdbId}", name="addMovie")
     * @param $imdbId
     * @return RedirectResponse|Response
     * @throws Exception
     */
    public function addMovie($imdbId)
    {
        $movie = $this->insertOrUpdateMovie($imdbId);
        return $this->redirectToRoute('movie', ['id' => $movie->getId()]);
    }

    public function insertOrUpdateMovie($imdbId)
    {
        ini_set('max_execution_time', 0);

        $em = $this->getDoctrine()->getManager();
        $result = TMDbWrapper::findFromImdbId($imdbId);

        if(isset($result['movie_results'])
            && !empty($result['movie_results'])
            && isset($result['movie_results'][0]['id']))
        {
            $tmdbId = $result['movie_results'][0]['id'];
        }

        if(!isset($tmdbId))
            throw new NotFoundHttpException();

        $item = new Item($this->tmdbApi);
        $omdbResponse = $this->omdbApi->getByImdbId($imdbId);
        $tmdbResponse = $item->getMovie($tmdbId, ['language' => 'fr-FR']);

        $movie = $this->getDoctrine()->getRepository(Movie::class)->find($tmdbResponse->getId());
        $alreadyExist = true;

        if($movie == null)
        {
            $alreadyExist = false;
            $movie = new Movie();
        }

        // Poster handling
        if(!empty($omdbResponse->getPosterUrl()) && $omdbResponse->getPosterUrl() != "N/A")
        {
            $fileName = md5(microtime(true)) . ".jpg";
            file_put_contents( $this->getParameter('movies_upload_folder') . $fileName, file_get_contents($omdbResponse->getPosterUrl()));

            // Remove old poster
            if($alreadyExist && !empty($movie->getPoster()) && $movie->getPoster() != "default.jpg")
                unlink($this->getParameter('movies_upload_folder') . $movie->getPoster());
        }
        else if(!$alreadyExist)
            $fileName = "default.jpg";

        $movie->setId($tmdbResponse->getId())
            ->setTitle($tmdbResponse->getTitle())
            ->setOriginalTitle($tmdbResponse->getOriginalTitle())
            ->setRated($omdbResponse->getRated())
            ->setReleaseDate(new \DateTime($tmdbResponse->getReleaseDate()))
            ->setRuntime($omdbResponse->getRuntime());

        if(isset($fileName))
            $movie->setPoster($fileName);

        $movie->setGenre(implode(", ", $omdbResponse->getGenre()))
            ->setDirector(implode(", ", $omdbResponse->getDirector()))
            ->setWriter(implode(", ", $omdbResponse->getWriter()))
            ->setActors(implode(", ", $omdbResponse->getActors()))
            ->setOverview($tmdbResponse->getOverview())
            ->setLanguage(implode(", ", $omdbResponse->getLanguage()))
            ->setCountry(implode(", ", $omdbResponse->getCountry()))
            ->setAwards($omdbResponse->getAwards())
            ->setImdbRating($omdbResponse->getImdbRating())
            ->setImdbId($tmdbResponse->getIMDBId())
            ->setProduction(($omdbResponse->getProduction() != "N/A") ? Utils::removeSpecialChars($omdbResponse->getProduction()) : "??")
            ->setComments(null)
            ->setSeen(($alreadyExist) ? $movie->getSeen() : false)
            ->setUpdatedAt(new \DateTime());

        if(!$alreadyExist)
            $em->persist($movie);

        $em->flush();

        return $movie;
    }

    /**
     * @Route("/shows", name="shows")
     */
    public function shows()
    {
        $shows = $this->getDoctrine()->getRepository(TVShow::class)->findBy([], ['release_date' => 'desc'], 52);

        return $this->render('series/series.html.twig', [
            'shows' => $shows
        ]);
    }

    /**
     * @Route("/shows/add/{imdbId}", name="addTVShow")
     * @param $imdbId
     * @return RedirectResponse
     * @throws Exception
     */
    public function addTVShow($imdbId)
    {
        $tvShow = $this->insertOrUpdateTVShow($imdbId);
        return $this->redirectToRoute('show', ['id' => $tvShow->getId()]);
    }

    public function insertOrUpdateTVShow($imdbId)
    {
        ini_set('max_execution_time', 0);

        $em = $this->getDoctrine()->getManager();
        $result = TMDbWrapper::findFromImdbId($imdbId);

        if(isset($result['tv_results'])
            && !empty($result['tv_results'])
            && isset($result['tv_results'][0]['id']))
        {
            $tmdbId = $result['tv_results'][0]['id'];
        }

        if(!isset($tmdbId))
            throw new NotFoundHttpException();

        $item = new Item($this->tmdbApi);

        $omdbResponse = $this->omdbApi->getByImdbId($imdbId);
        $tmdbResponse = $item->getTVShow($tmdbId, ['language' => 'fr-FR']);

        $tvShow = $this->getDoctrine()->getRepository(TVShow::class)->find($tmdbResponse->getId());
        $alreadyExist = true;

        if($tvShow == null)
        {
            $alreadyExist = false;
            $tvShow = new TVShow();
        }

        // Poster handling
        if(!empty($tmdbResponse->getPosterPath()))
        {
            $fileName = md5(microtime(true)) . ".jpg";
            try {
                file_put_contents( $this->getParameter('series_upload_folder') . $fileName, file_get_contents($omdbResponse->getPosterUrl()));
            } catch (Exception $e)
            {
                if(!$alreadyExist)
                    $fileName = "default.jpg";
            }
            // Remove old poster
            if($alreadyExist && !empty($tvShow->getPoster()) && $tvShow->getPoster() != "default.jpg")
                unlink($this->getParameter('series_upload_folder') . $tvShow->getPoster());

        } else if(!$alreadyExist)
            $fileName = "default.jpg";

        $tvShow->setId($tmdbResponse->getId())
            ->setName($tmdbResponse->getTitle())
            ->setOriginalName($tmdbResponse->getOriginalTitle())
            ->setYearSpan($omdbResponse->getYear())
            ->setRated($omdbResponse->getRated());

        if(isset($fileName))
            $tvShow->setPoster($fileName);

        $tvShow->setReleaseDate(new \DateTime($tmdbResponse->getReleaseDate()))
            ->setEpisodeRunTime(($omdbResponse->getRuntime() != 0) ? $omdbResponse->getRuntime() : "??")
            ->setGenre(implode(", ", $omdbResponse->getGenre()))
            ->setDirector(implode(", ", $omdbResponse->getDirector()))
            ->setWriter(implode(", ", $omdbResponse->getWriter()))
            ->setActors((implode(", ", $omdbResponse->getActors()) != "N/A") ? implode(", ", $omdbResponse->getActors()) : "??")
            ->setOverview($tmdbResponse->getOverview())
            ->setLanguage(implode(", ", $omdbResponse->getLanguage()))
            ->setAwards($omdbResponse->getAwards())
            ->setImdbRating(($omdbResponse->getImdbRating() != 0) ? $omdbResponse->getImdbRating() : "??")
            ->setImdbId($imdbId)
            ->setStatus($tmdbResponse->getStatus());

        $networks = [];
        foreach($tmdbResponse->getNetworks() AS $network)
            $networks[] = $network->name;

        $tvShow->setNetwork(implode(", ", $networks))
            ->setComments(null)
            ->setUpdatedAt(new \DateTime())
            ->setSeen(($alreadyExist) ? $tvShow->getSeen() : false)
            ->setDownloaded(($alreadyExist) ? $tvShow->getDownloaded() : false);

        foreach($tmdbResponse->getSeasons() AS $seasonResult)
        {
            if($seasonResult->getSeasonNumber() == 0)
                continue;

            $tmdbSeason = $item->getTVSeason($tvShow->getId(), $seasonResult->getSeasonNumber(), ['language' => 'fr-FR']);

            $season = $em->getRepository(TVSeason::class)->find($tmdbSeason->getId());
            $seasonAlreadyExist = true;

            if($season == null)
            {
                $seasonAlreadyExist = false;
                $season = new TVSeason();
            }

            $season->setId($tmdbSeason->getId())
                ->setName($tmdbSeason->getName())
                ->setAirDate(new \DateTime($tmdbSeason->getAirDate()))
                ->setSeasonNumber($tmdbSeason->getSeasonNumber())
                ->setOverview($tmdbSeason->getOverview());

            // Poster handling
            if(!empty($tmdbSeason->getPosterPath()))
            {
                $fileName = md5(microtime(true)) . ".jpg";
                try {
                    file_put_contents( $this->getParameter('seasons_upload_folder') . $fileName, file_get_contents(TMDbWrapper::$api_poster_url . $tmdbSeason->getPosterPath()));
                } catch (Exception $e)
                {
                    if(!$alreadyExist)
                        $fileName = "default.jpg";
                }

                // Remove old poster
                if($seasonAlreadyExist && !empty($season->getPoster()) && $season->getPoster() != "default.jpg")
                    unlink($this->getParameter('seasons_upload_folder') . $season->getPoster());
            } else if(!$seasonAlreadyExist)
                $fileName = "default.jpg";

            if(isset($fileName))
                $season->setPoster($fileName);

            foreach($tmdbSeason->getEpisodes() AS $episodeResult)
            {
                $tmdbEpisodeFr = $item->getTVEpisode($tvShow->getId(), $tmdbSeason->getSeasonNumber(), $episodeResult->getEpisodeNumber(), ['language' => 'fr-FR']);
                $tmdbEpisodeOriginalLang = $item->getTVEpisode($tvShow->getId(), $tmdbSeason->getSeasonNumber(), $episodeResult->getEpisodeNumber());

                $episode = $em->getRepository(TVEpisode::class)->find($tmdbEpisodeFr->getId());
                $episodeAlreadyExist = true;

                if($episode == null)
                {
                    $episodeAlreadyExist = false;
                    $episode = new TVEpisode();
                }

                $episode->setId($tmdbEpisodeFr->getId())
                    ->setTitle($tmdbEpisodeFr->getName())
                    ->setOriginalTitle($tmdbEpisodeOriginalLang->getName())
                    ->setOverview($tmdbEpisodeFr->getOverview())
                    ->setAirDate(new \DateTime($tmdbEpisodeFr->getAirDate()))
                    ->setRating($tmdbEpisodeFr->getNote())
                    ->setEpisodeNumber($tmdbEpisodeFr->getEpisodeNumber());

                // Poster handling
                if(!empty($tmdbEpisodeFr->getStillPath()))
                {
                    $fileName = md5(microtime(true)) . ".jpg";
                    try {
                        file_put_contents( $this->getParameter('episodes_upload_folder') . $fileName, file_get_contents(TMDbWrapper::$api_thumbnail_url . $tmdbEpisodeFr->getStillPath()));
                    } catch (Exception $e)
                    {
                        if(!$alreadyExist)
                            $fileName = "default.jpg";
                    }
                    // Remove old poster
                    if($episodeAlreadyExist && !empty($episode->getPoster()) && $episode->getPoster() != "default.jpg")
                        unlink($this->getParameter('episodes_upload_folder') . $episode->getPoster());

                } else if(!$episodeAlreadyExist)
                    $fileName = "default.jpg";

                if(isset($fileName))
                    $episode->setPoster($fileName);

                $episode->setSeen(($alreadyExist) ? $episode->getSeen() : false)
                    ->setDownloaded(($alreadyExist) ? $episode->getDownloaded() : false);

                if(!$episodeAlreadyExist)
                    $season->addEpisode($episode);
            }
            if(!$seasonAlreadyExist)
                $tvShow->addSeason($season);
        }
        if(!$alreadyExist)
            $em->persist($tvShow);

        $em->flush();
        return $tvShow;
    }

    /**
     * @Route("/show/{id}", name="show")
     * @param int $id
     * @return Response
     */
    public function show(int $id)
    {
        $show = $this->getDoctrine()->getRepository(TVShow::class)->find($id);

        if($show == null)
            throw new NotFoundHttpException("This serie does not exist");

        $this->checkTVShowCompletion($show);

        return $this->render('series/serie.html.twig', [
            'show' => $show,
        ]);
    }

    public function checkTVShowCompletion(TVShow $tvShow)
    {
        $isDownloaded = true;
        $isSeen = true;
        foreach($tvShow->getSeasons() as $season)
        {
            foreach($season->getEpisodes() as $episode)
            {
                if(!$episode->getDownloaded())
                {
                    $isDownloaded = false;
                }

                if(!$episode->getSeen())
                {
                    $isSeen = false;
                }

                if(!$isDownloaded || !$isSeen)
                    break;
            }

            if(!$isDownloaded || !$isSeen)
                break;
        }

        $tvShow->setSeen($isSeen);
        $tvShow->setDownloaded($isDownloaded);
        $this->getDoctrine()->getManager()->flush();
    }

    public function searchBar()
    {
        $form = $this->createForm(SearchType::class);

        return $this->render('main/searchbar.html.twig', [
            'form' =>$form->createView()
        ]);
    }

    /**
     * @Route("/searchWithForm", name="searchWithForm")
     * @param Request $request
     * @return Response
     */
    public function searchWithForm(Request $request)
    {
        $form = $this->createForm(SearchType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $query = $form->get('query')->getData();
            return $this->redirectToRoute('search', ['query' => $query]);
        }
        throw new NotFoundHttpException();
    }

    /**
     * @Route("/search/{query}", name="search")
     * @param string $query
     * @return Response
     */
    public function search(string $query)
    {
        if(!empty($query))
        {
            $movies = $this->getDoctrine()->getRepository(Movie::class)->search($query);
            $shows = $this->getDoctrine()->getRepository(TVShow::class)->search($query);
            //$games = $this->getDoctrine()->getRepository(Game::class)->search($query);

            return $this->render('main/search.html.twig', [
                'query' => $query,
                'movies' => $movies,
                'shows' => $shows,
                //'games' => $games
            ]);
        }
        throw new NotFoundHttpException();
    }

    /**
     * @Route("/doomsday", name="doomsday")
     */
    public function doomsday()
    {
        ini_set('max_execution_time', 0);
        $movies = $this->getDoctrine()->getRepository(Movie::class)->findAll();
        $dir = $this->getParameter('movies_upload_folder');
        $cdir = scandir($dir);
        foreach ($cdir as $key => $value)
        {
            $isUseless = true;
            if (!in_array($value,array(".","..")))
            {
                if (!is_dir($dir . $value))
                {
                    foreach ($movies AS $movie)
                    {
                        if($movie->getPoster() == $value)
                        {
                            $isUseless = false;
                            break;
                        }
                    }
                    if($isUseless)
                        unlink($dir . $value);
                }
            }
        }
        return new Response("Ok");
    }

    /**************************************************
     ******************* AJAX CALLS *******************
     **************************************************/
    /**
     * @Route("/movies/seen/{id}", name="ajax-seen-movie", options={"expose"=true})
     * @param int $id
     * @return JsonResponse
     */
    public function ajaxMovieSeenStatus(int $id)
    {
        return new JsonResponse(['response' => $this->getDoctrine()->getRepository(Movie::class)->changeSeenStatus($id)]);
    }

    /**
     * @Route("/series/seasons/downloaded/{id}", name="ajax-downloaded-season", options={"expose"=true})
     * @param int $id
     * @return JsonResponse
     */
    public function ajaxSeasonDownloadedStatus(int $id)
    {
        return new JsonResponse(['response' => $this->getDoctrine()->getRepository(TVSeason::class)->changeDownloadedStatus($id)]);
    }

    /**
     * @Route("/series/seasons/seen/{id}", name="ajax-seen-season", options={"expose"=true})
     * @param int $id
     * @return JsonResponse
     */
    public function ajaxSeasonSeenStatus(int $id)
    {
        return new JsonResponse(['response' => $this->getDoctrine()->getRepository(TVSeason::class)->changeSeenStatus($id)]);
    }


    /**
     * @Route("/series/episodes/downloaded/{id}", name="ajax-downloaded-episode", options={"expose"=true})
     * @param int $id
     * @return JsonResponse
     */
    public function ajaxEpisodeDownloadedStatus(int $id)
    {
        return new JsonResponse(['response' => $this->getDoctrine()->getRepository(TVEpisode::class)->changeDownloadedStatus($id)]);
    }

    /**
     * @Route("/series/episodes/seen/{id}", name="ajax-seen-episode", options={"expose"=true})
     * @param int $id
     * @return JsonResponse
     */
    public function ajaxEpisodeSeenStatus(int $id)
    {
        return new JsonResponse(['response' => $this->getDoctrine()->getRepository(TVEpisode::class)->changeSeenStatus($id)]);
    }
}
