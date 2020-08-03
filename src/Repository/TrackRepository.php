<?php

namespace App\Repository;

use App\Entity\Track;
use App\Utils\Constants;
use App\Utils\Utils;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Track|null find($id, $lockMode = null, $lockVersion = null)
 * @method Track|null findOneBy(array $criteria, array $orderBy = null)
 * @method Track[]    findAll()
 * @method Track[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Track::class);
    }

    public function insertOrUpdateSpotify(object $trackApi) : Track
    {
        // It can take some time so enable infinite execution time
        ini_set('max_execution_time', 0);

        $em = $this->getEntityManager();

        // Check if artist already exist in our database
        $track = $this->findOneBy(['spotify_id' => $trackApi->id]);
        $alreadyExist = true;

        // If it's a new one, initialize it
        if($track == null)
        {
            $alreadyExist = false;
            $track = new Track();
        }

        $track->setName($trackApi->name)
            ->setTrackNumber($trackApi->track_number)
            ->setDiscNumber($trackApi->disc_number)
            ->setExplicit($trackApi->explicit)
            ->setDurationMs($trackApi->duration_ms)
            ->setSpotifyId($trackApi->id)
            ->setTrackType($trackApi->type);

        if(!$alreadyExist)
            $em->persist($track);

        $em->flush();

        return $track;
    }
}
