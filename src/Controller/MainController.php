<?php

namespace App\Controller;

use App\Entity\Movie;
use App\Entity\People;
use App\Entity\TVShow;
use App\Form\SearchType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;



class MainController extends AbstractController
{
    /**
     * @Route("/", name="index")
     */
    public function index()
    {
        $em = $this->getDoctrine()->getManager();

        // Main stats
        $totalMovies = $em->getRepository(Movie::class)->createQueryBuilder('m')->select('count(m.id)')->getQuery()->getSingleScalarResult();
        $totalShows = $em->getRepository(TVShow::class)->createQueryBuilder('s')->select('count(s.id)')->getQuery()->getSingleScalarResult();
        $totalPeoples = $em->getRepository(People::class)->createQueryBuilder('p')->select('count(p.id)')->getQuery()->getSingleScalarResult();

        // Secondary stats
        $seenMovies = $em->getRepository(Movie::class)->createQueryBuilder('m')->select('count(m.id)')->where('m.seen = 1')->getQuery()->getSingleScalarResult();
        $seenShows = $em->getRepository(TVShow::class)->createQueryBuilder('s')->select('count(s.id)')->where('s.seen = 1')->getQuery()->getSingleScalarResult();
        $totalMen = $em->getRepository(People::class)->createQueryBuilder('p')->select('count(p.id)')->where('p.gender = 2')->getQuery()->getSingleScalarResult();
        $totalWomen = $em->getRepository(People::class)->createQueryBuilder('p')->select('count(p.id)')->where('p.gender = 1')->getQuery()->getSingleScalarResult();

        // Latest
        $latestMovies = $em->getRepository(Movie::class)->findBy([], ['updated_at' => 'desc'], 10);
        $latestShows = $em->getRepository(TVShow::class)->findBy([], ['updated_at' => 'desc'], 10);

        return $this->render('main/index.html.twig', [
            'totalMovies' => $totalMovies,
            'totalShows' => $totalShows,
            'totalPeoples' => $totalPeoples,
            'totalMen' => $totalMen,
            'totalWomen' => $totalWomen,
            'seenMovies' => $seenMovies,
            'seenShows' => $seenShows,
            'latestMovies' => $latestMovies,
            'latestShows' => $latestShows
        ]);
    }

    public function searchBar()
    {
        $form = $this->createForm(SearchType::class);

        return $this->render('main/searchbar.html.twig', [
            'form' =>$form->createView()
        ]);
    }

    /**
     * @Route("/searchWithForm", name="searchWithForm")
     * @param Request $request
     * @return Response
     */
    public function searchWithForm(Request $request)
    {
        $form = $this->createForm(SearchType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $query = $form->get('query')->getData();
            return $this->redirectToRoute('search', ['query' => $query]);
        }
        throw new NotFoundHttpException();
    }

    /**
     * @Route("/search/{query}", name="search")
     * @param string $query The search query
     * @return Response
     */
    public function search(string $query)
    {
        if(!empty($query))
        {
            $movies = $this->getDoctrine()->getRepository(Movie::class)->search($query);
            $shows = $this->getDoctrine()->getRepository(TVShow::class)->search($query);
            $people = $this->getDoctrine()->getRepository(People::class)->findOneBy(['name' => $query]);

            return $this->render('main/search.html.twig', [
                'query' => $query,
                'movies' => $movies,
                'shows' => $shows,
                'people' => $people
            ]);
        }
        throw new NotFoundHttpException();
    }
}

