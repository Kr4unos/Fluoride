<?php


namespace App\Controller;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Utils\Constants;
use SpotifyWebAPI\Session;
use SpotifyWebAPI\SpotifyWebAPI;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MusicController extends AbstractController
{
    /**
     * @Route("/music/artists", name="music-artists")
     * @return Response
     */
    public function artists()
    {
        $artists = $this->getDoctrine()->getRepository(Artist::class)->findBy([], ['popularity' => 'desc'], 52);

        return $this->render('music/artists.html.twig', ['artists' => $artists]);
    }

    /**
     * @Route("/music/artists/{id}", name="music-artist")
     * @param string $spotifyArtistId
     * @return Response
     */
    public function artist(string $id)
    {
        $artist = $this->getDoctrine()->getRepository(Artist::class)->findOneBy(['spotify_id' => $id]);
        if($artist == null)
            throw new NotFoundHttpException('This artist does not exist');

        return $this->render('music/artist.html.twig', ['artist' => $artist]);
    }

    /**
     * @Route("/music/albums/add/{spotifyAlbumId}", name="addAlbum")
     * @param string $spotifyAlbumId
     * @return Response
     */
    public function addAlbum(string $spotifyAlbumId)
    {
        $session = new Session(
            Constants::SPOTIFY_CLIENT_ID,
            Constants::SPOTIFY_CLIENT_SECRET,
            Constants::SPOTIFY_REDIRECT_URI
        );

        $session->requestCredentialsToken();

        $api = new SpotifyWebAPI();
        $api->setAccessToken($session->getAccessToken());

        $album = $this->getDoctrine()->getRepository(Album::class)
            ->insertOrUpdateSpotify(
                $api,
                $spotifyAlbumId,
                $this->getParameter('albums_upload_folder'),
                $this->getParameter('artists_upload_folder')
            );

        if(isset($album->getArtists()[0]))
        {
            return $this->redirectToRoute('music-artist', [
                'id' => $album->getArtists()[0]->getSpotifyId(),
                '_fragment' => $album->getSpotifyId()
            ]);
        }

        return $this->redirectToRoute('index');
    }

    /**
     * @Route("/music/tracks/add/{spotifyTrackId}", name="addTrack")
     * @param string $spotifyTrackId
     * @return Response
     */
    public function addTrack(string $spotifyTrackId)
    {
        $session = new Session(
            Constants::SPOTIFY_CLIENT_ID,
            Constants::SPOTIFY_CLIENT_SECRET,
            Constants::SPOTIFY_REDIRECT_URI
        );

        $session->requestCredentialsToken();

        $api = new SpotifyWebAPI();
        $api->setAccessToken($session->getAccessToken());

        $trackApi = $api->getTrack($spotifyTrackId);

        $album = $this->getDoctrine()->getRepository(Album::class)
            ->insertOrUpdateSpotify(
                $api,
                $trackApi->album->id,
                $this->getParameter('albums_upload_folder'),
                $this->getParameter('artists_upload_folder'),
                false
            );

        $track = $this->getDoctrine()->getRepository(Track::class)->insertOrUpdateSpotify($trackApi);

        if($track != null && !$album->getTracks()->contains($track))
            $album->addTrack($track);

        $this->getDoctrine()->getManager()->flush();

        if(isset($album->getArtists()[0]))
        {
            return $this->redirectToRoute('music-artist', [
                'id' => $album->getArtists()[0]->getSpotifyId(),
                '_fragment' => $album->getSpotifyId()
            ]);
        }

        return $this->redirectToRoute('index');
    }
}