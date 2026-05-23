<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\User;
use App\Entity\Club;
use App\Entity\Evenement;
use App\Entity\Recrutement;
use App\Repository\CandidatureRepository;
use App\Repository\RecrutementRepository;
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
        ReclamationRepository $reclamationRepo,
        RecrutementRepository $recrutementRepo,
        CandidatureRepository $candidatureRepo,
        EntityManagerInterface $em
    ): Response {
        return $this->render('admin/index.html.twig', [
            'totalUsers' => count($userRepo->findAll()),
            'totalClubs' => count($clubRepo->findAll()),

            'totalEvents' => count($evenementRepo->findAll()),
            'totalReclamations' => count($reclamationRepo->findAll()),
            'totalRecrutements' => count($recrutementRepo->findAll()),
            'totalCandidatures' => count($em->getRepository(\App\Entity\Candidature::class)->findAll()),
            'recentUsers' => $userRepo->findBy([], ['id' => 'DESC'], 5),
            'recentEvents' => $evenementRepo->findBy([], ['id' => 'DESC'], 5),
            'recentRecrutements' => $recrutementRepo->findBy([], ['id' => 'DESC'], 5),

            // Pending counts for badges
            'totalPendingUsers' => count($userRepo->findBy(['isVerified' => 0])),
            'totalPendingEvents' => count($evenementRepo->findBy(['status' => 'pending'])),
            'totalPendingClubs' => count($clubRepo->findBy(['status' => 'pending'])),
            'totalPendingClubMembers' => count($em->getRepository(\App\Entity\ClubMember::class)->findBy(['status' => 'pending'])),
            'totalPendingRecrutements' => count($recrutementRepo->findBy(['status' => 'pending'])),
            'totalPendingCandidatures' => count($em->getRepository(\App\Entity\Candidature::class)->findBy(['status' => 'pending'])),
            'totalPendingReclamations' => count($reclamationRepo->findBy(['status' => 'pending'])),
        ]);
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(UserRepository $userRepo): Response
    {
        return $this->render('admin/users.html.twig', [
            'pendingUsers' => $userRepo->findBy(['isApproved' => false]),
            'approvedUsers' => $userRepo->findBy(['isApproved' => true]),
        ]);
    }

    #[Route('/users/approve/{id}', name: 'app_admin_approve_user')]
    public function approveUser(User $user, EntityManagerInterface $em): Response
    {
        $user->setIsApproved(true);
        $em->flush();
        $this->addFlash('success', 'Utilisateur approuvé ! Il peut maintenant se connecter.');
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/users/reject/{id}', name: 'app_admin_reject_user')]
    public function rejectUser(User $user, EntityManagerInterface $em): Response
    {
        $em->remove($user);
        $em->flush();
        $this->addFlash('success', 'Inscription refusée et compte supprimé.');
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/users/delete/{id}', name: 'app_admin_delete_user')]
    public function deleteUser(User $user, EntityManagerInterface $em): Response
    {
        // Delete related club memberships
        foreach ($user->getClubMembers() as $member) {
            $em->remove($member);
        }

        // Delete related candidatures
        foreach ($user->getCandidatures() as $candidature) {
            $em->remove($candidature);
        }

        // Delete related participations
        foreach ($user->getParticipations() as $participation) {
            $em->remove($participation);
        }

        // Delete related reclamations
        foreach ($user->getReclamations() as $reclamation) {
            $em->remove($reclamation);
        }

        $em->flush(); // flush removals first

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
    #[Route('/clubs/validate/{id}', name: 'app_admin_validate_club')]
    public function validateClub(
        \App\Entity\Club $club,
        EntityManagerInterface $em,
        \App\Repository\ClubMemberRepository $clubMemberRepo
    ): Response {
        $club->setStatus('validated');

        foreach ($club->getClubMembers() as $member) {
            if ($member->getRole() === 'President') {
                $member->setStatus('approved');
                $member->getUser()->setRoles(['ROLE_PRESIDENT']);
            }
        }

        $em->flush();
        $this->addFlash('success', 'Club validé et président approuvé !');
        return $this->redirectToRoute('app_admin_clubs');
    }
    #[Route('/clubs/reject/{id}', name: 'app_admin_reject_club')]
    public function rejectClub(Club $club, EntityManagerInterface $em): Response
    {
        foreach ($club->getClubMembers() as $member) {
            $em->remove($member);
        }

        $em->remove($club);
        $em->flush();
        $this->addFlash('success', 'Club refusé et supprimé !');
        return $this->redirectToRoute('app_admin_clubs');
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
    #[Route('/recrutements', name: 'app_admin_recrutements')]
    public function recrutements(RecrutementRepository $recrutementRepo): Response
    {
        return $this->render('admin/recrutements.html.twig', [
            'recrutements' => $recrutementRepo->findAll(),
        ]);
    }

    #[Route('/recrutements/validate/{id}', name: 'app_admin_validate_recrutement')]
    public function validateRecrutement(Recrutement $recrutement, EntityManagerInterface $em): Response
    {
        $recrutement->setStatus('validated');
        $em->flush();
        $this->addFlash('success', 'Recrutement validé !');
        return $this->redirectToRoute('app_admin_recrutements');
    }

    #[Route('/recrutements/reject/{id}', name: 'app_admin_reject_recrutement')]
    public function rejectRecrutement(Recrutement $recrutement, EntityManagerInterface $em): Response
    {
        $recrutement->setStatus('rejected');
        $em->flush();
        $this->addFlash('success', 'Recrutement refusé !');
        return $this->redirectToRoute('app_admin_recrutements');
    }
    #[Route('/reclamations', name: 'app_admin_reclamations')]
    public function reclamations(ReclamationRepository $reclamationRepo): Response
    {
        return $this->render('admin/reclamations.html.twig', [
            'reclamations' => $reclamationRepo->findAll(),
        ]);
    }

    #[Route('/reclamations/resolve/{id}', name: 'app_admin_resolve_reclamation')]
    public function resolveReclamation(\App\Entity\Reclamation $reclamation, EntityManagerInterface $em): Response
    {
        $reclamation->setStatus('resolved');
        $em->flush();
        $this->addFlash('success', 'Réclamation résolue !');
        return $this->redirectToRoute('app_admin_reclamations');
    }

    #[Route('/reclamations/reject/{id}', name: 'app_admin_reject_reclamation')]
    public function rejectReclamation(\App\Entity\Reclamation $reclamation, EntityManagerInterface $em): Response
    {
        $reclamation->setStatus('rejected');
        $em->flush();
        $this->addFlash('success', 'Réclamation refusée !');
        return $this->redirectToRoute('app_admin_reclamations');
    }
    #[Route('/clubs/delete/{id}', name: 'app_admin_delete_club')]
    public function deleteClub(Club $club, EntityManagerInterface $em): Response
    {
        // Remove all members first
        foreach ($club->getClubMembers() as $member) {
            $em->remove($member);
        }

        // Remove all recrutements and their candidatures
        foreach ($club->getRecrutements() as $recrutement) {
            foreach ($recrutement->getCandidatures() as $candidature) {
                $em->remove($candidature);
            }
            $em->remove($recrutement);
        }

        // Remove participations and feedbacks before evenements
        foreach ($club->getEvenements() as $evenement) {
            foreach ($evenement->getParticipations() as $participation) {
                $em->remove($participation);
            }
            foreach ($evenement->getFeedback() as $feedback) {
                $em->remove($feedback);
            }
            $em->remove($evenement);
        }

        $em->flush();
        $em->remove($club);
        $em->flush();

        $this->addFlash('success', 'Club supprimé !');
        return $this->redirectToRoute('app_admin_clubs');
    }
}