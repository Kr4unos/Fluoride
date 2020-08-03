<?php

namespace App\Repository;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Utils\Constants;
use App\Utils\Utils;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use SpotifyWebAPI\SpotifyWebAPI;

/**
 * @method Album|null find($id, $lockMode = null, $lockVersion = null)
 * @method Album|null findOneBy(array $criteria, array $orderBy = null)
 * @method Album[]    findAll()
 * @method Album[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Album::class);
    }

    /**
     * @param SpotifyWebAPI $spotifyWebAPI
     * @param string $spotifyAlbumId
     * @param string $destination_path
     * @param string $artists_destination_path
     * @param bool $create_all_tracks
     * @return Album
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function insertOrUpdateSpotify(SpotifyWebAPI $spotifyWebAPI, string $spotifyAlbumId, string $destination_path,
                                          string $artists_destination_path, bool $create_all_tracks = true) : Album
    {
        $albumApi = $spotifyWebAPI->getAlbum($spotifyAlbumId);

        // It can take some time so enable infinite execution time
        ini_set('max_execution_time', 0);

        $em = $this->getEntityManager();

        // Check if artist already exist in our database
        $album = $this->findOneBy(['spotify_id' => $albumApi->id]);
        $alreadyExist = true;

        // If it's a new one, initialize it
        if($album == null)
        {
            $alreadyExist = false;
            $album = new Album();
            $fileName = Constants::DEFAULT_IMAGE_NAME;
        }

        // Poster handling
        if(count($albumApi->images) > 0 && Utils::isNotNullOrUnknown($albumApi->images[0]->url))
        {
            $fileName = Utils::downloadFileTo($destination_path, $albumApi->images[0]->url);

            // Prevent losing the current image if new image is not found
            if($fileName == Constants::DEFAULT_IMAGE_NAME && Utils::isNotNullOrDefaultImage($album->getCover()))
                unset($fileName);

            // Remove old poster if movie already exist
            if($alreadyExist && Utils::isNotNullOrDefaultImage($album->getCover()))
                Utils::removeFile($destination_path . $album->getCover());
        }

        $album->setName($albumApi->name)
            ->setPopularity($albumApi->popularity)
            ->setAlbumType($albumApi->album_type);

        $release_date = new \DateTime();
        switch($albumApi->release_date_precision)
        {
            case 'year':
                $release_date->setDate(intval($albumApi->release_date), 1, 1);
                break;
            case 'month':
                $release_date->setDate(
                    intval(substr($albumApi->release_date, 0, 4)), // [2000]-xx
                    intval(substr($albumApi->release_date, 5, 2)), // 2000-[01]
                    1
                );
                break;
            case 'day':
                $release_date = new \DateTime($albumApi->release_date);
                break;
            default:
                break;
        }

        $album->setLabel($albumApi->label)
            ->setReleaseDate($release_date)
            ->setReleaseDatePrecision($albumApi->release_date_precision)
            ->setSpotifyId($albumApi->id);

        if(isset($fileName))
            $album->setCover($fileName);

        if(!$alreadyExist)
            $em->persist($album);

        // Add artists
        foreach($albumApi->artists AS $artistO)
        {
            $artistApi = $spotifyWebAPI->getArtist($artistO->id);
            $artist = $em->getRepository(Artist::class)->insertOrUpdateSpotify($artistApi, $artists_destination_path);

            if($artist != null && !$album->getArtists()->contains($artist))
                $album->addArtist($artist);
        }

        if($create_all_tracks)
        {
            // Add tracks
            foreach ($albumApi->tracks->items AS $trackApi)
            {
                $track = $em->getRepository(Track::class)->insertOrUpdateSpotify($trackApi);
                if($track != null && !$album->getTracks()->contains($track))
                    $album->addTrack($track);
            }
        }

        $em->flush();

        return $album;
    }
}
