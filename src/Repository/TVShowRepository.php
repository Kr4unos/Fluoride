<?php

namespace App\Repository;

use App\Entity\People;
use App\Entity\TVEpisode;
use App\Entity\TVSeason;
use App\Entity\TVShow;
use App\Utils\Constants;
use App\Utils\TMDbWrapper;
use App\Utils\Utils;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Rooxie\Exception\ApiErrorException;
use Rooxie\Exception\IncorrectImdbIdException;
use Rooxie\Exception\InvalidApiKeyException;
use Rooxie\Exception\InvalidResponseException;
use Rooxie\Exception\MovieNotFoundException;
use Rooxie\OMDb;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use VfacTmdb\Factory;
use VfacTmdb\Item;

/**
 * @method TVShow|null find($id, $lockMode = null, $lockVersion = null)
 * @method TVShow|null findOneBy(array $criteria, array $orderBy = null)
 * @method TVShow[]    findAll()
 * @method TVShow[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TVShowRepository extends ServiceEntityRepository
{
    // Movies / shows / peoples APIs
    private $tmdbApi;
    private $omdbApi;

    /**
     * TVShowRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TVShow::class);

        // Init APIs
        $this->tmdbApi = Factory::create()->getTmdb(Constants::TMDB_API_KEY);
        $this->omdbApi = new OMDb(Constants::OMDB_API_KEY);
    }

    /**
     * @param string $query
     * @return mixed
     */
    public function search(string $query)
    {
        $queryBuilder = $this->createQueryBuilder('s')
            ->andWhere('s.name LIKE :query OR s.original_name LIKE :query OR s.year_span LIKE :query OR s.genre LIKE :query'
                . ' OR s.writer LIKE :query OR s.actors LIKE :query OR s.network LIKE :query')
            ->orderBy('s.release_date', 'desc');

        $queryFinal = $queryBuilder->setParameter('query', "%" . $query . "%")
            ->setMaxResults(52)
            ->getQuery();

        return $queryFinal->getResult();
    }

    /**
     * @param TVShow $tvShow
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function checkCompletion(TVShow $tvShow)
    {
        $isDownloaded = true;
        $isSeen = true;

        foreach($tvShow->getSeasons() as $season)
        {
            foreach($season->getEpisodes() as $episode)
            {
                if(!$episode->getDownloaded())
                    $isDownloaded = false;

                if(!$episode->getSeen())
                    $isSeen = false;

                if(!$isDownloaded || !$isSeen)
                    break;
            }
            if(!$isDownloaded || !$isSeen)
                break;
        }

        $tvShow->setSeen($isSeen);
        $tvShow->setDownloaded($isDownloaded);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string $imdbId
     * @param string $destination_path
     * @param string $season_destination_path
     * @param string $episode_destination_path
     * @param string $people_destination_path
     * @return TVShow
     * @throws ApiErrorException
     * @throws IncorrectImdbIdException
     * @throws InvalidApiKeyException
     * @throws InvalidResponseException
     * @throws MovieNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Exception
     */
    public function insertOrUpdate(string $imdbId, string $destination_path, string $season_destination_path,
                                   string $episode_destination_path, string $people_destination_path)
    {
        ini_set('max_execution_time', 0);
        ini_set( 'error_reporting', E_ALL );

        $em = $this->getEntityManager();
        $tmdbId = TMDbWrapper::getTmdbIdFromImdbIdForShow($imdbId);

        if($tmdbId == null)
            throw new NotFoundHttpException();

        // TMDb search
        $item = new Item($this->tmdbApi);
        $tmdbResponse = $item->getTVShow($tmdbId, ['language' => 'fr-FR']);

        // OMDb search
        $omdbResponse = $this->omdbApi->getByImdbId($imdbId);

        // Check if show already exist in our database
        $tvShow = $this->find($tmdbResponse->getId());
        $alreadyExist = true;

        if($tvShow == null)
        {
            $alreadyExist = false;
            $tvShow = new TVShow();
            $fileName = Constants::DEFAULT_IMAGE_NAME;
        }

        // Poster handling
        if(Utils::isNotNullOrUnknown($omdbResponse->getPosterUrl()))
        {
            $fileName = Utils::downloadFileTo($destination_path, $omdbResponse->getPosterUrl());

            // Prevent losing the current image if new image is not found
            if($fileName == Constants::DEFAULT_IMAGE_NAME && Utils::isNotNullOrDefaultImage($tvShow->getPoster()))
                unset($fileName);

            // Remove old poster
            if ($alreadyExist && Utils::isNotNullOrDefaultImage($tvShow->getPoster()))
                Utils::removeFile($destination_path . $tvShow->getPoster());
        }

        $networks = [];
        foreach($tmdbResponse->getNetworks() AS $network)
            $networks[] = $network->name;

        $tvShow->setId($tmdbResponse->getId())
            ->setName($tmdbResponse->getTitle())
            ->setOriginalName($tmdbResponse->getOriginalTitle())
            ->setYearSpan($omdbResponse->getYear())
            ->setRated($omdbResponse->getRated())
            ->setReleaseDate(new DateTime($tmdbResponse->getReleaseDate()))
            ->setEpisodeRunTime(($omdbResponse->getRuntime() != 0) ? $omdbResponse->getRuntime() : "??")
            ->setGenre(implode(", ", $omdbResponse->getGenre()))
            ->setDirector(implode(", ", $omdbResponse->getDirector()))
            ->setWriter((implode(", ", $omdbResponse->getWriter()) != "N/A") ? implode(", ", $omdbResponse->getWriter()) : "??")
            ->setActors((implode(", ", $omdbResponse->getActors()) != "N/A") ? implode(", ", $omdbResponse->getActors()) : "??")
            ->setOverview($tmdbResponse->getOverview())
            ->setLanguage(implode(", ", $omdbResponse->getLanguage()))
            ->setAwards($omdbResponse->getAwards())
            ->setImdbRating(($omdbResponse->getImdbRating() != 0) ? $omdbResponse->getImdbRating() : "??")
            ->setImdbId($imdbId)
            ->setStatus($tmdbResponse->getStatus())
            ->setNetwork(implode(", ", $networks))
            ->setComments(null)
            ->setUpdatedAt(new DateTime())
            ->setSeen(($alreadyExist) ? $tvShow->getSeen() : false)
            ->setDownloaded(($alreadyExist) ? $tvShow->getDownloaded() : false);

        if(isset($fileName))
            $tvShow->setPoster($fileName);

        if(!$alreadyExist)
            $em->persist($tvShow);

        foreach($tmdbResponse->getSeasons() AS $seasonResult)
        {
            $season = $em->getRepository(TVSeason::class)
                ->insertOrUpdate(
                    $tvShow,
                    $seasonResult->getSeasonNumber(),
                    $season_destination_path,
                    $episode_destination_path,
                    $this->tmdbApi
                );

            if($season != null && !$tvShow->getSeasons()->contains($season))
                $tvShow->addSeason($season);
        }

        foreach ($omdbResponse->getActors() AS $actor)
        {
            $people = $this->getEntityManager()->getRepository(People::class)->insertOrUpdate($actor, $people_destination_path);
            if($people != null && !$tvShow->getPeoples()->contains($people))
                $tvShow->addPeople($people);
        }

        foreach ($omdbResponse->getWriter() AS $writer)
        {
            $people = $this->getEntityManager()->getRepository(People::class)->insertOrUpdate($writer, $people_destination_path);
            if($people != null && !$tvShow->getPeoples()->contains($people))
                $tvShow->addPeople($people);
        }

        $em->flush();

        return $tvShow;
    }
}
