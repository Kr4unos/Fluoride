<?php

namespace App\Controller;

use App\Entity\TVEpisode;
use App\Entity\TVSeason;
use App\Entity\TVShow;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class TVShowController extends AbstractController
{
    /**
     * @Route("/shows", name="shows")
     */
    public function shows() : Response
    {
        $shows = $this->getDoctrine()->getRepository(TVShow::class)->findBy([], ['release_date' => 'desc'], 52);

        return $this->render('series/series.html.twig', [
            'shows' => $shows
        ]);
    }

    /**
     * @Route("/show/{id}", name="show")
     * @param int $id
     * @return Response
     */
    public function show(int $id) : Response
    {
        $show = $this->getDoctrine()->getRepository(TVShow::class)->find($id);

        if($show == null)
            throw new NotFoundHttpException("This show does not exist");

        $this->getDoctrine()->getRepository(TVShow::class)->checkCompletion($show);

        return $this->render('series/serie.html.twig', [
            'show' => $show,
            'peoples' => $show->getPeoples()
        ]);
    }

    /**
     * @Route("/shows/add/{imdbId}", name="addTVShow")
     * @param $imdbId
     * @return RedirectResponse
     * @throws Exception
     */
    public function addTVShow(string $imdbId) : Response
    {
        $tvShow = $this->getDoctrine()->getRepository(TVShow::class)
            ->insertOrUpdate(
                $imdbId,
                $this->getParameter('series_upload_folder'),
                $this->getParameter('seasons_upload_folder'),
                $this->getParameter('episodes_upload_folder'),
                $this->getParameter('peoples_upload_folder')
            );

        return $this->redirectToRoute('show', ['id' => $tvShow->getId()]);
    }

    /**************************************************
     ******************* AJAX CALLS *******************
     **************************************************/

    /**
     * @Route("/series/seasons/downloaded/{id}", name="ajax-downloaded-season", options={"expose"=true})
     * @param int $id
     * @return JsonResponse
     */
    public function ajaxSeasonDownloadedStatus(int $id) : JsonResponse
    {
        return new JsonResponse(['response' => $this->getDoctrine()->getRepository(TVSeason::class)->changeDownloadedStatus($id)]);
    }

    /**
     * @Route("/series/seasons/seen/{id}", name="ajax-seen-season", options={"expose"=true})
     * @param int $id
     * @return JsonResponse
     */
    public function ajaxSeasonSeenStatus(int $id) : JsonResponse
    {
        return new JsonResponse(['response' => $this->getDoctrine()->getRepository(TVSeason::class)->changeSeenStatus($id)]);
    }


    /**
     * @Route("/series/episodes/downloaded/{id}", name="ajax-downloaded-episode", options={"expose"=true})
     * @param int $id
     * @return JsonResponse
     */
    public function ajaxEpisodeDownloadedStatus(int $id) : JsonResponse
    {
        return new JsonResponse(['response' => $this->getDoctrine()->getRepository(TVEpisode::class)->changeDownloadedStatus($id)]);
    }

    /**
     * @Route("/series/episodes/seen/{id}", name="ajax-seen-episode", options={"expose"=true})
     * @param int $id
     * @return JsonResponse
     */
    public function ajaxEpisodeSeenStatus(int $id) : JsonResponse
    {
        return new JsonResponse(['response' => $this->getDoctrine()->getRepository(TVEpisode::class)->changeSeenStatus($id)]);
    }
}