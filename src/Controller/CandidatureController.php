<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\Recrutement;
use App\Form\CandidatureType;
use App\Repository\CandidatureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/candidature')]
final class CandidatureController extends AbstractController
{
    #[Route('/postuler/{id}', name: 'app_candidature_postuler')]
    public function postuler(
        Recrutement $recrutement,
        Request $request,
        EntityManagerInterface $em,
        CandidatureRepository $candidatureRepo
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();

        // Check if already applied
        $existing = $candidatureRepo->findOneBy([
            'user' => $user,
            'recrutement' => $recrutement
        ]);

        if ($existing) {
            $this->addFlash('warning', 'Vous avez déjà postulé à ce recrutement.');
            return $this->redirectToRoute('app_recrutement_show', ['id' => $recrutement->getId()]);
        }

        $candidature = new Candidature();
        $form = $this->createForm(CandidatureType::class, $candidature);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle CV upload
            $cvFile = $form->get('cvFile')->getData();
            if ($cvFile) {
                $newFilename = uniqid('cv_') . '.pdf';
                $cvFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/cvs',
                    $newFilename
                );
                $candidature->setCvFilename($newFilename);
            }

            $candidature->setUser($user);
            $candidature->setRecrutement($recrutement);
            $candidature->setStatus('pending');
            $candidature->setSubmittedAt(new \DateTime());
            $candidature->setNo(uniqid('CAND-'));
            $em->persist($candidature);
            $em->flush();

            $this->addFlash('success', 'Votre candidature a été envoyée !');
            return $this->redirectToRoute('app_recrutement_show', ['id' => $recrutement->getId()]);
        }

        return $this->render('candidature/postuler.html.twig', [
            'form' => $form->createView(),
            'recrutement' => $recrutement,
        ]);
    }

    #[Route('/mes-candidatures', name: 'app_candidature_index')]
    public function index(CandidatureRepository $candidatureRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $candidatures = $candidatureRepo->findBy(
            ['user' => $this->getUser()],
            ['submittedAt' => 'DESC']
        );

        return $this->render('candidature/index.html.twig', [
            'candidatures' => $candidatures,
        ]);
    }
}