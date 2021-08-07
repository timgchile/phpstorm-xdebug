<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class HealthCheckController
{
    #[Route(path: '/healthcheck', methods: ['GET', 'POST'])]
    public function getAction(): Response
    {
        return new Response('phpstorm + xdebug over docker rocks :D');
    }
}
