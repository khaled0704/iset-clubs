<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
            $user->setIsVerified(0);

            // Generate OTP
            $otp = random_int(100000, 999999);
            $user->setOtpCode((string) $otp);
            $user->setOtpExpiresAt(new \DateTime('+10 minutes'));

            $entityManager->persist($user);
            $entityManager->flush();

            // Send OTP email
            $email = (new Email())
                ->from('zakariahaj326@gmail.com')
                ->to($user->getEmail())
                ->subject('Votre code de vérification ISET Clubs')
                ->html(
                    '<p>Bonjour <strong>' . $user->getFirstname() . '</strong>,</p>' .
                    '<p>Votre code de vérification est : <strong style="font-size:24px">' . $otp . '</strong></p>' .
                    '<p>Ce code expire dans 10 minutes.</p>' .
                    '<p>L\'équipe ISET Clubs</p>'
                );
            $mailer->send($email);

            // Store email in session for OTP verification
            $request->getSession()->set('otp_email', $user->getEmail());
            $request->getSession()->set('otp_context', 'registration');

            return $this->redirectToRoute('app_otp_verify');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}