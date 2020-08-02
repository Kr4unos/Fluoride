<?php

namespace App\Repository;

use App\Entity\Movie;
use App\Entity\People;
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
use Rooxie\OMDb;
use VfacTmdb\Factory;
use VfacTmdb\Item;
use VfacTmdb\Search;

/**
 * @method People|null find($id, $lockMode = null, $lockVersion = null)
 * @method People|null findOneBy(array $criteria, array $orderBy = null)
 * @method People[]    findAll()
 * @method People[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PeopleRepository extends ServiceEntityRepository
{
    // Movies / shows / peoples APIs
    private $tmdbApi;
    private $omdbApi;

    /**
     * PeopleRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, People::class);

        // Init APIs
        $this->tmdbApi = Factory::create()->getTmdb(Constants::TMDB_API_KEY);
        $this->omdbApi = new OMDb(Constants::OMDB_API_KEY);
    }

    /**
     * @param $name
     * @param $destination_path
     * @return People|null
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Exception
     */
    public function insertOrUpdate(string $name, string $destination_path) : ?People
    {
        $search = new Search($this->tmdbApi);
        $item = new Item($this->tmdbApi);

        $em = $this->getEntityManager();

        $responses = $search->people($name);
        $foundPeoples = [];

        foreach($responses AS $response)
            $foundPeoples[] = $response;

        // At least one people is found
        if(count($foundPeoples) == 0)
            return null;

        // Sort by highest popularity (avoid picking an homonym)
        usort($foundPeoples, function (\VfacTmdb\Results\People $a, \VfacTmdb\Results\People $b)
        {
            if($a === $b)
                return 0;
            return ($a->getPopularity() < $b->getPopularity()) ? 1 : -1;
        });

        // Choose the one with the hightest popularity
        $chosenPeople = $foundPeoples[0];
        $people = $this->find($chosenPeople->getId());

        // Result from API
        $peopleApi = $item->getPeople($chosenPeople->getId(), ['language' => 'fr-FR']);

        // Update popularity / deathday / biography and set movie and tv show
        if($people != null)
        {
            $people->setPopularity($peopleApi->getPopularity())
                ->setDeathday(($peopleApi->getDeathday() == null) ? null : new DateTime($peopleApi->getDeathday()))
                ->setBiography($peopleApi->getBiography());
        }
        else
        {
            // If we're here, it's a new entry
            $people = new People();

            $people->setId($peopleApi->getId())
                ->setName($peopleApi->getName())
                ->setPopularity($peopleApi->getPopularity())
                ->setNicknames(implode(", ", $peopleApi->getAlsoKnownAs()))
                ->setBiography($peopleApi->getBiography())
                ->setIsAdultMovie($peopleApi->getAdult())
                ->setBirthday(($peopleApi->getBirthday() == null) ? null : new DateTime($peopleApi->getBirthday()))
                ->setDeathday(($peopleApi->getDeathday() == null) ? null : new DateTime($peopleApi->getDeathday()))
                ->setGender($peopleApi->getGender())
                ->setImdbId($peopleApi->getImdbId())
                ->setPlaceOfBirth($peopleApi->getPlaceOfBirth());

            $fileName = Constants::DEFAULT_IMAGE_NAME;

            if(Utils::isNotNullOrUnknown($peopleApi->getProfilePath()))
                $fileName = Utils::downloadFileTo($destination_path, TMDbWrapper::$api_poster_url . $peopleApi->getProfilePath());

            $people->setProfile($fileName);

            $em->persist($people);
        }

        $em->flush();

        return $people;
    }
}
