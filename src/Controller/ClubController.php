<?php

namespace App\Controller;

use App\Entity\Club;
use App\Form\ClubType;
use App\Repository\ClubRepository;
use App\Repository\ClubMemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/club')]
final class ClubController extends AbstractController
{
    #[Route(name: 'app_club_index', methods: ['GET'])]
    public function index(ClubRepository $clubRepository): Response
    {
        return $this->render('club/index.html.twig', [
            'clubs' => $clubRepository->findBy(['status' => 'validated']),
        ]);
    }

    #[Route('/new', name: 'app_club_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $club = new Club();
        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile) {
                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $logoFile->guessExtension();
                $logoFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/clubs',
                    $newFilename
                );
                $club->setLogo($newFilename);
            }

            // Club starts as pending until admin approves
            $club->setStatus('pending');
            $club->setCreatedAt(new \DateTime());
            $entityManager->persist($club);

            // Creator becomes President but also pending until club is approved
            $member = new \App\Entity\ClubMember();
            $member->setUser($this->getUser());
            $member->setClub($club);
            $member->setRole('President');
            $member->setStatus('pending');
            $member->setJoinedAt(new \DateTime());
            $entityManager->persist($member);

            $entityManager->flush();

            $this->addFlash('success', 'Votre club a été soumis et est en attente de validation par un admin.');
            return $this->redirectToRoute('app_club_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('club/new.html.twig', [
            'club' => $club,
            'form' => $form,
        ]);
    }
    #[Route('/{id}', name: 'app_club_show', methods: ['GET'])]
    public function show(Club $club, ClubMemberRepository $clubMemberRepo): Response
    {
        $isMember = null;
        if ($this->getUser()) {
            $isMember = $clubMemberRepo->findOneBy([
                'user' => $this->getUser(),
                'club' => $club
            ]);
        }

        return $this->render('club/show.html.twig', [
            'club' => $club,
            'isMember' => $isMember,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_club_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Club $club, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $logoFile */
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile) {
                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $logoFile->guessExtension();

                $logoFile->move(
                    $this->getParameter('kernel.project_dir') . '/public/uploads/clubs',
                    $newFilename
                );

                $club->setLogo($newFilename);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_club_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('club/edit.html.twig', [
            'club' => $club,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_club_delete', methods: ['POST'])]
    public function delete(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $club->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($club);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_club_index', [], Response::HTTP_SEE_OTHER);
    }
}
