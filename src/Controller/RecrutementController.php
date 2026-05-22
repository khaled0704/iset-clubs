<?php

namespace App\Controller;

use App\Entity\Recrutement;
use App\Entity\Candidature;
use App\Entity\Feedback;
use App\Form\RecrutementType;
use App\Form\FeedbackType;
use App\Repository\CandidatureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/recrutement')]
final class RecrutementController extends AbstractController
{
    #[Route('/', name: 'app_recrutement_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $recrutements = $em->getRepository(Recrutement::class)->findBy(['status' => 'validated']);

        return $this->render('recrutement/index.html.twig', [
            'recrutements' => $recrutements,
        ]);
    }

    #[Route('/create', name: 'app_recrutement_create')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $recrutement = new Recrutement();
        $form = $this->createForm(RecrutementType::class, $recrutement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            $clubMember = $em->getRepository(\App\Entity\ClubMember::class)->findOneBy(['user' => $user]);

            if (!$clubMember) {
                $this->addFlash('error', 'Vous devez être membre d\'un club pour créer un recrutement.');
                return $this->redirectToRoute('app_recrutement_index');
            }



            $recrutement->setStatus('pending');
            $recrutement->setCreatedAt(new \DateTime());
            $recrutement->setClub($clubMember->getClub());
            $em->persist($recrutement);
            $em->flush();

            $this->addFlash('success', 'Recrutement créé et en attente de validation !');
            return $this->redirectToRoute('app_recrutement_index');
        }

        return $this->render('recrutement/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_recrutement_show')]
    public function show(
        Recrutement $recrutement,
        Request $request,
        EntityManagerInterface $em,
        CandidatureRepository $candidatureRepo
    ): Response {
        $user = $this->getUser();

        // Check if user already applied
        $alreadyApplied = false;
        if ($user) {
            $existing = $candidatureRepo->findOneBy([
                'user' => $user,
                'recrutement' => $recrutement
            ]);
            $alreadyApplied = $existing !== null;
        }

        // Feedback form
        $feedback = new Feedback();
        $feedbackForm = $this->createForm(FeedbackType::class, $feedback);
        $feedbackForm->handleRequest($request);

        if ($feedbackForm->isSubmitted() && $feedbackForm->isValid()) {
            if (!$this->getUser()) {
                $this->addFlash('error', 'Vous devez être connecté pour laisser un feedback.');
                return $this->redirectToRoute('app_recrutement_show', ['id' => $recrutement->getId()]);
            }

            $feedback->setUser($this->getUser());
            $feedback->setCreatedAt(new \DateTime());
            $em->persist($feedback);
            $em->flush();
            $this->addFlash('success', 'Feedback envoyé !');
            return $this->redirectToRoute('app_recrutement_show', ['id' => $recrutement->getId()]);
        }

        return $this->render('recrutement/show.html.twig', [
            'recrutement' => $recrutement,
            'alreadyApplied' => $alreadyApplied,
            'feedbackForm' => $feedbackForm->createView(),
        ]);
    }


}