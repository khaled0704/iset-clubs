<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\Participation;
use App\Entity\Feedback;
use App\Form\EvenementType;
use App\Form\FeedbackType;
use App\Repository\EvenementRepository;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/evenement')]
class EvenementController extends AbstractController
{
    #[Route('/', name: 'app_evenement_index')]
    public function index(EvenementRepository $evenementRepo): Response
    {
        return $this->render('evenement/index.html.twig', [
            'evenements' => $evenementRepo->findBy(['status' => 'validated']),
        ]);
    }
    #[Route('/create', name: 'app_evenement_create')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            $clubMember = $em->getRepository(\App\Entity\ClubMember::class)->findOneBy(['user' => $user]);

            if (!$clubMember) {
                $this->addFlash('error', 'Vous devez être membre d\'un club pour créer un événement.');
                return $this->redirectToRoute('app_evenement_index');
            }

            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/events',
                    $newFilename
                );
                $evenement->setImage($newFilename);
            }

            $evenement->setStatus('pending');
            $evenement->setCreatedAt(new \DateTime());
            $evenement->setClub($clubMember->getClub());
            $em->persist($evenement);
            $em->flush();

            $this->addFlash('success', 'Événement créé et en attente de validation !');
            return $this->redirectToRoute('app_evenement_index');
        }

        return $this->render('evenement/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    #[Route('/{id}', name: 'app_evenement_show')]
    public function show(
        Evenement $evenement,
        Request $request,
        EntityManagerInterface $em,
        ParticipationRepository $participationRepo
    ): Response {
        $user = $this->getUser();

        // checki keno deja participant
        $alreadyParticipating = false;
        if ($user) {
            $existing = $participationRepo->findOneBy([
                'user' => $user,
                'Evenement' => $evenement
            ]);
            $alreadyParticipating = $existing !== null;
        }

        // form ta3 el feedback
        $feedback = new Feedback();
        $feedbackForm = $this->createForm(FeedbackType::class, $feedback);
        $feedbackForm->handleRequest($request);

        if ($feedbackForm->isSubmitted() && $feedbackForm->isValid()) {
            $feedback->setUser($user);
            $feedback->setEvenement($evenement);
            $feedback->setCreatedAt(new \DateTime());
            $em->persist($feedback);
            $em->flush();
            $this->addFlash('success', 'Feedback envoyé !');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        return $this->render('evenement/show.html.twig', [
            'evenement' => $evenement,
            'alreadyParticipating' => $alreadyParticipating,
            'feedbackForm' => $feedbackForm->createView(),
        ]);
    }

    #[Route('/{id}/participer', name: 'app_evenement_participer')]
    public function participer(
        Evenement $evenement,
        EntityManagerInterface $em,
        ParticipationRepository $participationRepo
    ): Response {
        $user = $this->getUser();

        $existing = $participationRepo->findOneBy([
            'user' => $user,
            'Evenement' => $evenement
        ]);

        if (!$existing) {
            $participation = new Participation();
            $participation->setUser($user);
            $participation->setEvenement($evenement);
            $participation->setRegisteredAt(new \DateTime());
            $em->persist($participation);
            $em->flush();
            $this->addFlash('success', 'Inscription confirmée !');
        } else {
            $this->addFlash('warning', 'Vous êtes déjà inscrit !');
        }

        return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
    }

}