<?php

namespace App\Controller;

use App\Entity\Movie;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MovieController extends AbstractController
{
    /**
     * @Route("/movies", name="movies")
     */
    public function movies() : Response
    {
        $movies = $this->getDoctrine()->getRepository(Movie::class)->findBy([], ['release_date' => 'desc'], 52);

        return $this->render('movies/movies.html.twig', [
            'movies' => $movies
        ]);
    }

    /**
     * @Route("/movie/{id}", name="movie")
     * @param int $id
     * @return Response
     */
    public function movie(int $id) : Response
    {
        $movie = $this->getDoctrine()->getRepository(Movie::class)->find($id);

        if($movie == null)
            throw new NotFoundHttpException("This movie does not exist");

        return $this->render('movies/movie.html.twig', [
            'movie' => $movie,
            'peoples' => $movie->getPeoples()
        ]);
    }

    /**
     * @Route("/movies/add/{imdbId}", name="addMovie")
     * @param $imdbId
     * @return RedirectResponse|Response
     * @throws Exception
     */
    public function addMovie(string $imdbId) : Response
    {
        $movie = $this->getDoctrine()->getRepository(Movie::class)
            ->insertOrUpdate(
                $imdbId,
                $this->getParameter('movies_upload_folder'),
                $this->getParameter('people_upload_folder')
            );

        return $this->redirectToRoute('movie', ['id' => $movie->getId()]);
    }

    /**************************************************
     ******************* AJAX CALLS *******************
     **************************************************/

    /**
     * @Route("/movies/seen/{id}", name="ajax-seen-movie", options={"expose"=true})
     * @param int $id
     * @return JsonResponse
     */
    public function ajaxMovieSeenStatus(int $id) : JsonResponse
    {
        return new JsonResponse(['response' => $this->getDoctrine()->getRepository(Movie::class)->changeSeenStatus($id)]);
    }
}