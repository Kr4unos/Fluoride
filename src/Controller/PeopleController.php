<?php

namespace App\Controller;

use App\Entity\People;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class PeopleController extends AbstractController
{
    /**
     * @Route("/peoples", name="peoples")
     */
    public function peoples()
    {
        $peoples = $this->getDoctrine()->getRepository(People::class)->findBy([], ['popularity' => 'desc'], 52);

        return $this->render('peoples/peoples.html.twig', [
            'peoples' => $peoples
        ]);
    }

    /**
     * @Route("/people/{id}", name="people")
     * @param int $id
     * @return Response
     */
    public function people(int $id)
    {
        $people = $this->getDoctrine()->getRepository(People::class)->find($id);

        if($people == null)
            throw new NotFoundHttpException("This people does not exist");

        return $this->render('peoples/people.html.twig', [
            'people' => $people,
            'movies' => $people->getMovies(),
            'shows' => $people->getTvShows()
        ]);
    }

    /**
     * @param string $name
     * @Route("/peoples/add/{name}", name="addPeople")
     * @return RedirectResponse
     */
    public function addPeople(string $name)
    {
        $people = $this->getDoctrine()->getRepository(People::class)
            ->insertOrUpdate($name, $this->getParameter('peoples_upload_folder'));

        if($people == null)
            throw new NotFoundHttpException();

        return $this->redirectToRoute('people', ['id' => $people->getId()]);
    }
}