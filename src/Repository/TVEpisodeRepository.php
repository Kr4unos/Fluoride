<?php

namespace App\Repository;

use App\Entity\TVEpisode;
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
use VfacTmdb\Item;
use VfacTmdb\Tmdb;

/**
 * @method TVEpisode|null find($id, $lockMode = null, $lockVersion = null)
 * @method TVEpisode|null findOneBy(array $criteria, array $orderBy = null)
 * @method TVEpisode[]    findAll()
 * @method TVEpisode[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TVEpisodeRepository extends ServiceEntityRepository
{
    /**
     * TVEpisodeRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TVEpisode::class);
    }

    /**
     * @param TVShow $tvShow
     * @param int $season_number
     * @param int $episode_number
     * @param string $destination_path
     * @param Tmdb $tmdbApi
     * @return TVEpisode|null
     * @throws Exception
     */
    public function insertOrUpdate(TVShow $tvShow, int $season_number, int $episode_number, string $destination_path,
                                   Tmdb $tmdbApi) : ?TVEpisode
    {
        $em = $this->getEntityManager();
        $item = new Item($tmdbApi);

        // Get response for both fr and en
        $tmdbEpisodeFr = $item->getTVEpisode($tvShow->getId(), $season_number, $episode_number, ['language' => 'fr-FR']);
        $tmdbEpisodeOriginalLang = $item->getTVEpisode($tvShow->getId(), $season_number, $episode_number);

        $episode = $this->find($tmdbEpisodeFr->getId());
        $alreadyExist = true;

        if($episode == null)
        {
            $alreadyExist = false;
            $episode = new TVEpisode();
            $fileName = Constants::DEFAULT_IMAGE_NAME;
        }

        // Poster handling
        if(Utils::isNotNullOrUnknown($tmdbEpisodeFr->getStillPath()))
        {
            $fileName = Utils::downloadFileTo($destination_path, TMDbWrapper::$api_thumbnail_url . $tmdbEpisodeFr->getStillPath());

            // Prevent losing the current image if new image is not found
            if($fileName == Constants::DEFAULT_IMAGE_NAME && Utils::isNotNullOrDefaultImage($episode->getPoster()))
                unset($fileName);

            // Remove old poster
            if($alreadyExist && Utils::isNotNullOrDefaultImage($episode->getPoster()))
                Utils::removeFile($destination_path . $episode->getPoster());
        }

        $episode->setId($tmdbEpisodeFr->getId())
            ->setTitle($tmdbEpisodeFr->getName())
            ->setOriginalTitle($tmdbEpisodeOriginalLang->getName())
            ->setOverview($tmdbEpisodeFr->getOverview())
            ->setAirDate(new DateTime($tmdbEpisodeFr->getAirDate()))
            ->setRating($tmdbEpisodeFr->getNote())
            ->setEpisodeNumber($tmdbEpisodeFr->getEpisodeNumber())
            ->setSeen(($alreadyExist) ? $episode->getSeen() : false)
            ->setDownloaded(($alreadyExist) ? $episode->getDownloaded() : false);

        if(isset($fileName))
            $episode->setPoster($fileName);

        if(!$alreadyExist)
            $em->persist($episode);

        $em->flush();

        return $episode;
    }

    /**
     * @param int $id
     * @return bool
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function changeSeenStatus(int $id) : bool
    {
        $episode = $this->find($id);
        if($episode)
        {
            $episode->setSeen(!$episode->getSeen());
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
        $episode = $this->find($id);
        if($episode)
        {
            $episode->setDownloaded(!$episode->getDownloaded());
            $this->getEntityManager()->flush();
            return true;
        }
        return false;
    }
}
