<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AppAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private MailerInterface $mailer,
        private UserRepository $userRepository
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->getPayload()->getString('email');
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->getPayload()->getString('password')),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var \App\Entity\User $user */
        $user = $token->getUser();

        // If coming from OtpController::authenticateUser(), skip OTP
        if ($request->getSession()->get('otp_verified') === true) {
            $request->getSession()->remove('otp_verified');

            if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
                return new RedirectResponse($targetPath);
            }
            return new RedirectResponse($this->urlGenerator->generate('app_evenement_index'));
        }

        // Generate and send OTP
        $otp = random_int(100000, 999999);
        $user->setOtpCode((string) $otp);
        $user->setOtpExpiresAt(new \DateTime('+10 minutes'));
        $this->userRepository->save($user);

        $email = (new Email())
            ->from('zakariahaj326@gmail.com')
            ->to($user->getEmail())
            ->subject('Code de connexion ISET Clubs')
            ->html(
                '<p>Bonjour <strong>' . $user->getFirstname() . '</strong>,</p>' .
                '<p>Votre code de connexion est : <strong style="font-size:24px">' . $otp . '</strong></p>' .
                '<p>Ce code expire dans 10 minutes.</p>' .
                '<p>L\'équipe ISET Clubs</p>'
            );
        $this->mailer->send($email);

        $request->getSession()->set('otp_email', $user->getEmail());
        $request->getSession()->set('otp_context', 'login');

        return new RedirectResponse($this->urlGenerator->generate('app_otp_verify'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}