<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Club;
use App\Entity\Evenement;
use App\Repository\UserRepository;
use App\Repository\ClubRepository;
use App\Repository\EvenementRepository;
use App\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'app_admin')]
    public function index(
        UserRepository $userRepo,
        ClubRepository $clubRepo,
        EvenementRepository $evenementRepo,
        ReclamationRepository $reclamationRepo
    ): Response {
        return $this->render('admin/index.html.twig', [
            'totalUsers' => count($userRepo->findAll()),
            'totalClubs' => count($clubRepo->findAll()),
            'totalEvents' => count($evenementRepo->findAll()),
            'totalReclamations' => count($reclamationRepo->findAll()),
            'recentUsers' => $userRepo->findBy([], ['id' => 'DESC'], 5),
            'recentEvents' => $evenementRepo->findBy([], ['id' => 'DESC'], 5),
        ]);
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(UserRepository $userRepo): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $userRepo->findAll(),
        ]);
    }

    #[Route('/users/delete/{id}', name: 'app_admin_delete_user')]
    public function deleteUser(User $user, EntityManagerInterface $em): Response
    {
        $em->remove($user);
        $em->flush();
        $this->addFlash('success', 'Utilisateur supprimé !');
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/events', name: 'app_admin_events')]
    public function events(EvenementRepository $evenementRepo): Response
    {
        return $this->render('admin/events.html.twig', [
            'events' => $evenementRepo->findAll(),
        ]);
    }

    #[Route('/events/validate/{id}', name: 'app_admin_validate_event')]
    public function validateEvent(Evenement $evenement, EntityManagerInterface $em): Response
    {
        $evenement->setStatus('validated');
        $em->flush();
        $this->addFlash('success', 'Événement validé !');
        return $this->redirectToRoute('app_admin_events');
    }

    #[Route('/events/reject/{id}', name: 'app_admin_reject_event')]
    public function rejectEvent(Evenement $evenement, EntityManagerInterface $em): Response
    {
        $evenement->setStatus('rejected');
        $em->flush();
        $this->addFlash('success', 'Événement refusé !');
        return $this->redirectToRoute('app_admin_events');
    }

    #[Route('/clubs', name: 'app_admin_clubs')]
    public function clubs(ClubRepository $clubRepo): Response
    {
        return $this->render('admin/clubs.html.twig', [
            'clubs' => $clubRepo->findAll(),
        ]);
    }
    #[Route('/club-members', name: 'app_admin_club_members')]
    public function clubMembers(\App\Repository\ClubMemberRepository $clubMemberRepo): Response
    {
        return $this->render('admin/club_members.html.twig', [
            'pendingMembers' => $clubMemberRepo->findBy(['status' => 'pending']),
        ]);
    }
    #[Route('/club-members/approve/{id}', name: 'app_admin_approve_member')]
    public function approveMember(\App\Entity\ClubMember $member, EntityManagerInterface $em): Response
    {
        $member->setStatus('approved');
        $em->flush();
        $this->addFlash('success', 'Membre approuvé !');
        return $this->redirectToRoute('app_admin_club_members');
    }

    #[Route('/club-members/reject/{id}', name: 'app_admin_reject_member')]
    public function rejectMember(\App\Entity\ClubMember $member, EntityManagerInterface $em): Response
    {
        $em->remove($member);
        $em->flush();
        $this->addFlash('success', 'Demande refusée !');
        return $this->redirectToRoute('app_admin_club_members');
    }
}