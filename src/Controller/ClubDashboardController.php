<?php

namespace App\Controller;

use App\Repository\ClubMemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ClubDashboardController extends AbstractController
{
    #[Route('/mon-club', name: 'app_mon_club')]
    public function index(ClubMemberRepository $clubMemberRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();

        // Find the user's club membership
        $membership = null;
        foreach ($user->getClubMembers() as $member) {
            if ($member->getStatus() === 'approved') {
                $membership = $member;
                break;
            }
        }

        if (!$membership) {
            $this->addFlash('warning', 'Vous n\'êtes membre d\'aucun club.');
            return $this->redirectToRoute('app_club_index');
        }

        $club = $membership->getClub();
        $role = $membership->getRole();
        $isManager = in_array($role, ['president', 'President', 'responsable', 'Responsable']);

        return $this->render('club_dashboard/index.html.twig', [
            'club' => $club,
            'membership' => $membership,
            'role' => $role,
            'evenements' => $club->getEvenements(),
            'isManager' => $isManager,
            'members' => $club->getClubMembers(),
            'recrutements' => $club->getRecrutements(),
        ]);
    }
    #[Route('/mon-club/approve-member/{id}', name: 'app_club_approve_member')]
    public function approveMember(
        \App\Entity\ClubMember $member,
        EntityManagerInterface $em,
        \App\Repository\ClubMemberRepository $clubMemberRepo
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

        $myMembership = $clubMemberRepo->findOneBy([
            'user' => $user,
            'club' => $member->getClub(),
            'status' => 'approved'
        ]);

        if (!$myMembership || !in_array($myMembership->getRole(), ['President', 'Responsable'])) {
            throw $this->createAccessDeniedException();
        }

        $member->setStatus('approved');
        $em->flush();
        $this->addFlash('success', 'Membre approuvé !');
        return $this->redirectToRoute('app_mon_club');
    }

    #[Route('/mon-club/reject-member/{id}', name: 'app_club_reject_member')]
    public function rejectMember(
        \App\Entity\ClubMember $member,
        EntityManagerInterface $em,
        \App\Repository\ClubMemberRepository $clubMemberRepo
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

        $myMembership = $clubMemberRepo->findOneBy([
            'user' => $user,
            'club' => $member->getClub(),
            'status' => 'approved'
        ]);

        if (!$myMembership || !in_array($myMembership->getRole(), ['President', 'Responsable'])) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($member);
        $em->flush();
        $this->addFlash('success', 'Demande refusée !');
        return $this->redirectToRoute('app_mon_club');
    }
}