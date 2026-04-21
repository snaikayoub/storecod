<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Repository\OrderRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RateLimitSubscriber implements EventSubscriberInterface
{
    private const MAX_ORDERS_PER_PHONE = 5;
    private const WINDOW_MINUTES = 60;

    public function __construct(
        private readonly OrderRepository $orderRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_ends_with($request->getPathInfo(), '/order/create')) {
            return;
        }

        $phone = trim((string) $request->request->get('phone', ''));
        if ($phone === '') {
            return;
        }

        $recent = $this->orderRepository->countRecentByPhone($phone, self::WINDOW_MINUTES);
        if ($recent >= self::MAX_ORDERS_PER_PHONE) {
            $event->setResponse(new \Symfony\Component\HttpFoundation\JsonResponse([
                'error' => 'Trop de commandes pour ce numero. Reessayez dans 1 heure.',
            ], 429));
        }
    }
}