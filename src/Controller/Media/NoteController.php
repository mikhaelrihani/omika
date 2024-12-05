<?php

namespace App\Controller\Media;

use App\Service\Media\NoteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('api/note', "app_note_")]
class NoteController extends AbstractController
{

    public function __construct(
        private EntityManagerInterface $em,
        private NoteService $noteService,
    ) {
    }

    //! --------------------------------------------------------------------------------------------

    #[Route('/getNote/{id}', name: 'getNote', methods: 'GET')]
    public function getNote(int $id): JsonResponse
    {
        $response = $this->noteService->getNote($id);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json($response->getData(), $response->getStatusCode(),[], ['groups' => 'note']);

    }

    //! --------------------------------------------------------------------------------------------

}
