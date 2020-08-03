<?php

namespace App\Repository;

use App\Entity\Artist;
use App\Utils\Constants;
use App\Utils\Utils;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Artist|null find($id, $lockMode = null, $lockVersion = null)
 * @method Artist|null findOneBy(array $criteria, array $orderBy = null)
 * @method Artist[]    findAll()
 * @method Artist[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArtistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Artist::class);
    }

    public function insertOrUpdateSpotify(object $artistApi, string $destination_path) : Artist
    {
        // It can take some time so enable infinite execution time
        ini_set('max_execution_time', 0);

        $em = $this->getEntityManager();

        // Check if artist already exist in our database
        $artist = $this->findOneBy(['spotify_id' => $artistApi->id]);
        $alreadyExist = true;

        // If it's a new one, initialize it
        if($artist == null)
        {
            $alreadyExist = false;
            $artist = new Artist();
            $fileName = Constants::DEFAULT_IMAGE_NAME;
        }

        // Poster handling
        if(count($artistApi->images) > 0 && Utils::isNotNullOrUnknown($artistApi->images[0]->url))
        {
            $fileName = Utils::downloadFileTo($destination_path, $artistApi->images[0]->url);

            // Prevent losing the current image if new image is not found
            if($fileName == Constants::DEFAULT_IMAGE_NAME && Utils::isNotNullOrDefaultImage($artist->getProfilePicture()))
                unset($fileName);

            // Remove old poster if movie already exist
            if($alreadyExist && Utils::isNotNullOrDefaultImage($artist->getProfilePicture()))
                Utils::removeFile($destination_path . $artist->getProfilePicture());
        }

        $artist->setName($artistApi->name)
            ->setPopularity($artistApi->popularity)
            ->setGenres(implode(", ", $artistApi->genres))
            ->setArtistType($artistApi->type)
            ->setSpotifyId($artistApi->id);

        if(isset($fileName))
            $artist->setProfilePicture($fileName);

        if(!$alreadyExist)
            $em->persist($artist);

        $em->flush();

        return $artist;
    }
}
