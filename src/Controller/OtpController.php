<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use App\Security\AppAuthenticator;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class OtpController extends AbstractController
{
    #[Route('/otp/verify', name: 'app_otp_verify')]
    public function verify(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserAuthenticatorInterface $userAuthenticator,
        AppAuthenticator $authenticator,
        MailerInterface $mailer
    ): Response {
        $otpEmail = $request->getSession()->get('otp_email');
        $otpContext = $request->getSession()->get('otp_context');

        if (!$otpEmail) {
            return $this->redirectToRoute('app_login');
        }

        $user = $userRepository->findOneBy(['email' => $otpEmail]);

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $enteredCode = $request->request->get('otp_code');

            // Check if expired
            if ($user->getOtpExpiresAt() < new \DateTime()) {
                $this->addFlash('error', 'Code expiré. Un nouveau code a été envoyé.');

                $otp = random_int(100000, 999999);
                $user->setOtpCode((string) $otp);
                $user->setOtpExpiresAt(new \DateTime('+10 minutes'));
                $em->flush();

                $email = (new Email())
                    ->from('zakariahaj326@gmail.com')
                    ->to($user->getEmail())
                    ->subject('Nouveau code de vérification ISET Clubs')
                    ->html(
                        '<p>Votre nouveau code est : <strong style="font-size:24px">' . $otp . '</strong></p>' .
                        '<p>Ce code expire dans 10 minutes.</p>'
                    );
                $mailer->send($email);

                return $this->redirectToRoute('app_otp_verify');
            }

            // Check code
            if ($enteredCode === $user->getOtpCode()) {
                $user->setOtpCode(null);
                $user->setOtpExpiresAt(null);
                $user->setIsVerified(1);
                $em->flush();

                $request->getSession()->remove('otp_email');
                $request->getSession()->remove('otp_context');

                if ($otpContext === 'login') {
                    // Set flag so AppAuthenticator skips OTP
                    $request->getSession()->set('otp_verified', true);

                    return $userAuthenticator->authenticateUser(
                        $user,
                        $authenticator,
                        $request
                    );
                }

                $this->addFlash('success', 'Compte vérifié ! Vous pouvez maintenant vous connecter.');
                return $this->redirectToRoute('app_login');

            } else {
                $this->addFlash('error', 'Code incorrect. Veuillez réessayer.');
            }
        }

        return $this->render('security/otp_verify.html.twig', [
            'context' => $otpContext,
        ]);
    }
}