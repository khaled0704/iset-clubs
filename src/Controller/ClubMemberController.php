<?php

namespace App\Controller;

use App\Entity\ClubMember;
use App\Entity\Club;
use App\Repository\ClubMemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/club-member')]
class ClubMemberController extends AbstractController
{
    #[Route('/join/{id}', name: 'app_club_join')]
    public function join(
        Club $club,
        EntityManagerInterface $em,
        ClubMemberRepository $clubMemberRepo
    ): Response {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $existing = $clubMemberRepo->findOneBy([
            'user' => $user,
            'club' => $club
        ]);

        if (!$existing) {
            $member = new ClubMember();
            $member->setUser($user);
            $member->setClub($club);
            $member->setRole('membre');
            $member->setStatus('pending');
            $member->setJoinedAt(new \DateTime());
            $em->persist($member);
            $em->flush();
            $this->addFlash('success', 'Vous avez rejoint le club !');
        } else {
            $this->addFlash('warning', 'Vous êtes déjà membre de ce club !');
        }

        return $this->redirectToRoute('app_club_show', ['id' => $club->getId()]);
    }

    #[Route('/leave/{id}', name: 'app_club_leave')]
    public function leave(
        Club $club,
        EntityManagerInterface $em,
        ClubMemberRepository $clubMemberRepo
    ): Response {
        $user = $this->getUser();

        $member = $clubMemberRepo->findOneBy([
            'user' => $user,
            'club' => $club
        ]);

        if ($member) {
            $em->remove($member);
            $em->flush();
            $this->addFlash('success', 'Vous avez quitté le club !');
        }

        return $this->redirectToRoute('app_club_show', ['id' => $club->getId()]);
    }
}