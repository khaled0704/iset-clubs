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
            'isManager' => $isManager,
            'members' => $club->getClubMembers(),
            'recrutements' => $club->getRecrutements(),
        ]);
    }
}