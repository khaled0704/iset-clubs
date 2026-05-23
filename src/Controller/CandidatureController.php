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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;

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

    #[Route('/manage', name: 'app_admin_candidatures')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_PRESIDENT") or is_granted("ROLE_RESPONSABLE")'))]
    public function manage(CandidatureRepository $candidatureRepo): Response
    {
        $user = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN')) {
            $candidatures = $candidatureRepo->findBy([], ['submittedAt' => 'DESC']);
        } else {
            // Find clubs where the user is a president or responsable
            $managedClubs = [];
            foreach ($user->getClubMembers() as $member) {
                if (in_array($member->getRole(), ['President', 'Responsable']) && $member->getStatus() === 'approved') {
                    $managedClubs[] = $member->getClub();
                }
            }

            // Get all candidatures for those clubs
            $candidatures = [];
            foreach ($managedClubs as $club) {
                foreach ($club->getRecrutements() as $recrutement) {
                    foreach ($recrutement->getCandidatures() as $candidature) {
                        $candidatures[] = $candidature;
                    }
                }
            }

            // Sort by submittedAt DESC
            usort($candidatures, function ($a, $b) {
                return $b->getSubmittedAt() <=> $a->getSubmittedAt();
            });
        }

        return $this->render('admin/candidatures.html.twig', [
            'candidatures' => $candidatures,
        ]);
    }

    #[Route('/approve/{id}', name: 'app_admin_approve_candidature')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_PRESIDENT") or is_granted("ROLE_RESPONSABLE")'))]
    public function approveCandidature(
        Candidature $candidature,
        EntityManagerInterface $em,
        \App\Repository\ClubMemberRepository $clubMemberRepo
    ): Response {
        if (!$this->canManageCandidature($this->getUser(), $candidature)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas gérer cette candidature.');
        }

        $candidature->setStatus('approved');

        // Add the user as a club member if not already one
        $club = $candidature->getRecrutement()->getClub();
        $user = $candidature->getUser();

        $existingMember = $clubMemberRepo->findOneBy([
            'user' => $user,
            'club' => $club,
        ]);

        if (!$existingMember) {
            $member = new \App\Entity\ClubMember();
            $member->setUser($user);
            $member->setClub($club);
            $member->setRole('membre');
            $member->setStatus('approved');
            $member->setJoinedAt(new \DateTime());
            $em->persist($member);
        }

        $em->flush();
        $this->addFlash('success', 'Candidature approuvée et membre ajouté au club !');
        return $this->redirectToRoute('app_admin_candidatures');
    }
    #[Route('/reject/{id}', name: 'app_admin_reject_candidature')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_PRESIDENT") or is_granted("ROLE_RESPONSABLE")'))]
    public function rejectCandidature(Candidature $candidature, EntityManagerInterface $em): Response
    {
        // Check if user has right to reject this candidature
        if (!$this->canManageCandidature($this->getUser(), $candidature)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas gérer cette candidature.');
        }

        $candidature->setStatus('rejected');
        $em->flush();
        $this->addFlash('success', 'Candidature refusée !');
        return $this->redirectToRoute('app_admin_candidatures');
    }

    private function canManageCandidature($user, Candidature $candidature): bool
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $club = $candidature->getRecrutement()?->getClub();
        if (!$club) {
            return false;
        }

        foreach ($user->getClubMembers() as $member) {
            if ($member->getClub() === $club && in_array($member->getRole(), ['President', 'Responsable']) && $member->getStatus() === 'approved') {
                return true;
            }
        }

        return false;
    }
}