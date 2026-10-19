<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The development console: a page where a user types commands (`/help`) that call the HTTP API as a client would, and
 * sees the notifications of the Mercure hub. Everything happens in the browser (`assets/console`): the page itself
 * holds no data and needs no token.
 */
class ConsoleController extends AbstractController
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {
    }

    #[Route('/console', name: 'app_console', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('console/index.html.twig', [
            'mercure_url' => $this->hub->getPublicUrl(),
        ]);
    }
}
