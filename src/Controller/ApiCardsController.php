<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\LogicalCardRepository;
use App\View\LogicalCardViewFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The cards as printed (see LogicalCardViewFactory), by code: the views of the games and their logs send codes. They
 * depend on no game: public and cacheable. Errors are problem details (RFC 9457).
 */
class ApiCardsController extends AbstractController
{
    use ProblemResponseTrait;

    public function __construct(
        private readonly LogicalCardRepository $logicalCardRepository,
        private readonly LogicalCardViewFactory $logicalCardViewFactory,
    ) {
    }

    #[Route('/api/cards', name: 'app_api_cards', methods: ['GET'])]
    public function list(): Response
    {
        $cards = $this->logicalCardRepository->findBy([], ['id' => 'ASC']);

        return $this->cacheable($this->json(array_map($this->logicalCardViewFactory->create(...), $cards)));
    }

    #[Route('/api/cards/{code}', name: 'app_api_card', methods: ['GET'])]
    public function show(string $code): Response
    {
        $card = $this->logicalCardRepository->find($code);
        if ($card === null) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'card_not_found', "Card not found: [{$code}]");
        }

        return $this->cacheable($this->json($this->logicalCardViewFactory->create($card)));
    }

    private function cacheable(Response $response): Response
    {
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
