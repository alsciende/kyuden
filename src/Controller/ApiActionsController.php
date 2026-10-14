<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Api\MessageSchemaFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The catalogue of the action messages players send through the API: the JSON schema of the payload of each type (see
 * MessageSchemaFactory). It depends on no game: public and cacheable.
 */
class ApiActionsController extends AbstractController
{
    public function __construct(
        private readonly MessageSchemaFactory $messageSchemaFactory,
    ) {
    }

    #[Route('/api/actions', name: 'app_api_actions', methods: ['GET'])]
    public function list(): Response
    {
        $response = new JsonResponse($this->messageSchemaFactory->createAll());
        $response->setEncodingOptions(\JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
