<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OtpCheckSubscriber implements EventSubscriberInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $currentRoute = $request->attributes->get('_route');

        if (!$currentRoute) {
            return;
        }

        // Skip profiler, assets, and internal routes
        if (str_starts_with($currentRoute, '_') || str_starts_with($currentRoute, 'debugbar')) {
            return;
        }

        $session = $request->getSession();

        if ($session->get('otp_email')) {
            $allowedRoutes = ['app_otp_verify', 'app_logout'];

            if (!in_array($currentRoute, $allowedRoutes)) {
                $event->setResponse(
                    new RedirectResponse(
                        $this->urlGenerator->generate('app_otp_verify')
                    )
                );
                return;
            }
        }
    }
}