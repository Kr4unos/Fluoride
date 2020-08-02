<?php

namespace App\Repository;

use App\Entity\Movie;
use App\Entity\People;
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
 * @method Movie|null find($id, $lockMode = null, $lockVersion = null)
 * @method Movie|null findOneBy(array $criteria, array $orderBy = null)
 * @method Movie[]    findAll()
 * @method Movie[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MovieRepository extends ServiceEntityRepository
{
    // Movies / shows / peoples APIs
    private $tmdbApi;
    private $omdbApi;

    /**
     * MovieRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Movie::class);

        // Init APIs
        $this->tmdbApi = Factory::create()->getTmdb(Constants::TMDB_API_KEY);
        $this->omdbApi = new OMDb(Constants::OMDB_API_KEY);
    }

    /**
     * @param $id
     * @return bool
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function changeSeenStatus($id)
    {
        $movie = $this->find($id);
        if($movie)
        {
            $movie->setSeen(!$movie->getSeen());
            $this->getEntityManager()->flush();
            return true;
        }
        return false;
    }

    public function search(string $query)
    {
        $queryBuilder = $this->createQueryBuilder('m')
            ->andWhere('m.title LIKE :query OR m.original_title LIKE :query OR m.release_date LIKE :query OR m.genre LIKE :query'
                . ' OR m.director LIKE :query OR m.writer LIKE :query OR m.actors LIKE :query OR m.production LIKE :query')
            ->orderBy('m.release_date', 'desc');

        $queryFinal = $queryBuilder->setParameter('query', "%" . $query . "%")
            ->setMaxResults(52)
            ->getQuery();

        return $queryFinal->getResult();
    }

    /**
     * Insert or update a movie in the database
     * @param string $imdbId
     * @param string $destination_path
     * @param string $people_destination_path
     * @return Movie|object|null
     * @throws ApiErrorException
     * @throws IncorrectImdbIdException
     * @throws InvalidApiKeyException
     * @throws InvalidResponseException
     * @throws MovieNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Exception
     */
    public function insertOrUpdate(string $imdbId, string $destination_path, string $people_destination_path) : Movie
    {
        // It can take some time so enable infinite execution time
        ini_set('max_execution_time', 0);

        $em = $this->getEntityManager();

        // Convert IMDb id to TMDb id
        $tmdbId = TMDbWrapper::getTmdbIdFromImdbIdForMovie($imdbId);

        // if result is null, throw exception
        if($tmdbId == null)
            throw new NotFoundHttpException();

        // TMDb search
        $item = new Item($this->tmdbApi);
        $tmdbResponse = $item->getMovie($tmdbId, ['language' => 'fr-FR']);

        // OMDb search
        $omdbResponse = $this->omdbApi->getByImdbId($imdbId);

        // Check if movie already exist in our database
        $movie = $this->find($tmdbResponse->getId());
        $alreadyExist = true;

        // If it's a new one, initialize it
        if($movie == null)
        {
            $alreadyExist = false;
            $movie = new Movie();
            $fileName = Constants::DEFAULT_IMAGE_NAME;
        }

        // Poster handling
        if(Utils::isNotNullOrUnknown($omdbResponse->getPosterUrl()))
        {
            $fileName = Utils::downloadFileTo($destination_path, $omdbResponse->getPosterUrl());

            // Prevent losing the current image if new image is not found
            if($fileName == Constants::DEFAULT_IMAGE_NAME && Utils::isNotNullOrDefaultImage($movie->getPoster()))
                unset($fileName);

            // Remove old poster if movie already exist
            if($alreadyExist && Utils::isNotNullOrDefaultImage($movie->getPoster()))
                Utils::removeFile($destination_path . $movie->getPoster());
        }

        $movie->setId($tmdbResponse->getId())
            ->setTitle($tmdbResponse->getTitle())
            ->setOriginalTitle($tmdbResponse->getOriginalTitle())
            ->setRated($omdbResponse->getRated())
            ->setReleaseDate(new DateTime($tmdbResponse->getReleaseDate()))
            ->setRuntime($omdbResponse->getRuntime())
            ->setGenre(implode(", ", $omdbResponse->getGenre()))
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
            ->setUpdatedAt(new DateTime());

        // If poster changed
        if(isset($fileName))
            $movie->setPoster($fileName);

        // Persist if it's a new movie
        if(!$alreadyExist)
            $em->persist($movie);

        foreach ($omdbResponse->getActors() AS $actor)
        {
            $people = $this->getEntityManager()->getRepository(People::class)->insertOrUpdate($actor, $people_destination_path);
            if($people != null && !$movie->getPeoples()->contains($people))
                $movie->addPeople($people);
        }

        foreach ($omdbResponse->getDirector() AS $director)
        {
            $people = $this->getEntityManager()->getRepository(People::class)->insertOrUpdate($director, $people_destination_path);
            if($people != null && !$movie->getPeoples()->contains($people))
                $movie->addPeople($people);
        }

        $em->flush();

        return $movie;
    }
}
