<?php

namespace App\Repository;

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
use VfacTmdb\Item;
use VfacTmdb\Tmdb;

/**
 * @method TVSeason|null find($id, $lockMode = null, $lockVersion = null)
 * @method TVSeason|null findOneBy(array $criteria, array $orderBy = null)
 * @method TVSeason[]    findAll()
 * @method TVSeason[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TVSeasonRepository extends ServiceEntityRepository
{
    /**
     * TVSeasonRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TVSeason::class);
    }

    /**
     * @param int $id
     * @return bool
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function changeSeenStatus(int $id) : bool
    {
        $season = $this->find($id);
        if($season)
        {
            foreach($season->getEpisodes() AS $episode)
            {
                $episode->setSeen(!$episode->getSeen());
            }
            $this->getEntityManager()->flush();
            return true;
        }
        return false;
    }

    /**
     * @param int $id
     * @return bool
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function changeDownloadedStatus(int $id) : bool
    {
        $season = $this->find($id);
        if ($season) {
            foreach ($season->getEpisodes() AS $episode) {
                $episode->setDownloaded(!$episode->getDownloaded());
            }
            $this->getEntityManager()->flush();
            return true;
        }
        return false;
    }

    /**
     * @param TVShow $tvShow
     * @param int $seasonNumber
     * @param string $destination_path
     * @param string $episode_destination_path
     * @param Tmdb $tmdbApi
     * @return TVSeason|null
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function insertOrUpdate(TVShow $tvShow, int $seasonNumber, string $destination_path,
                                   string $episode_destination_path, Tmdb $tmdbApi) : ?TVSeason
    {
        if($seasonNumber == 0)
            return null;

        $em = $this->getEntityManager();

        $item = new Item($tmdbApi);
        $tmdbSeason = $item->getTVSeason($tvShow->getId(), $seasonNumber, ['language' => 'fr-FR']);

        $season = $this->find($tmdbSeason->getId());
        $alreadyExist = true;

        if($season == null)
        {
            $alreadyExist = false;
            $season = new TVSeason();
            $fileName = Constants::DEFAULT_IMAGE_NAME;
        }

        // Poster handling
        if(Utils::isNotNullOrUnknown($tmdbSeason->getPosterPath()))
        {
            $fileName = Utils::downloadFileTo($destination_path, TMDbWrapper::$api_poster_url . $tmdbSeason->getPosterPath());

            // Prevent losing the current image if new image is not found
            if($fileName == Constants::DEFAULT_IMAGE_NAME && Utils::isNotNullOrDefaultImage($season->getPoster()))
                unset($fileName);

            // Remove old poster
            if ($alreadyExist && Utils::isNotNullOrDefaultImage($season->getPoster()))
                Utils::removeFile($destination_path . $season->getPoster());
        }

        $season->setId($tmdbSeason->getId())
            ->setName($tmdbSeason->getName())
            ->setAirDate(new DateTime($tmdbSeason->getAirDate()))
            ->setSeasonNumber($tmdbSeason->getSeasonNumber())
            ->setOverview($tmdbSeason->getOverview());

        // If poster changed
        if(isset($fileName))
            $season->setPoster($fileName);

        if(!$alreadyExist)
            $em->persist($season);

        foreach($tmdbSeason->getEpisodes() AS $episodeResult)
        {
            $episode = $em->getRepository(TVEpisode::class)
                ->insertOrUpdate(
                    $tvShow,
                    $season->getSeasonNumber(),
                    $episodeResult->getEpisodeNumber(),
                    $episode_destination_path,
                    $tmdbApi
                );

            if($episode != null && !$season->getEpisodes()->contains($episode))
                $season->addEpisode($episode);
        }

        $em->flush();

        return $season;
    }
}
