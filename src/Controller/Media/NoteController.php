<?php

namespace App\Controller\Media;

use App\Service\Media\NoteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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

    /**
     * Retrieves a note based on the provided ID.
     * @Route("/getNote/{id}", name="getNote", methods="GET")
     */
    #[Route('/getNote/{id}', name: 'getNote', methods: 'GET')]
    public function getNote(int $id): JsonResponse
    {
        $response = $this->noteService->getNote($id);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json($response->getData(), $response->getStatusCode(), [], ['groups' => 'note']);

    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Creates a new note based on the request data.
     * @Route("/createNote", name="createNote", methods="POST")
     */
    #[Route('/createNote', name: 'createNote', methods: 'Post')]
    public function createNote(Request $request): JsonResponse
    {
        $response = $this->noteService->createNote($request);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json($response->getData(), $response->getStatusCode(), [], ['groups' => 'note']);

    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Retrieves notes written by the current user based on the provided date.
     * @Route("/writtenNotesByDate", name="writtenNotesByDate", methods="GET")
     */
    #[Route('/writtenNotes', name: 'writtenNotes', methods: 'GET')]
    public function getWrittenNotesByDate(Request $request): JsonResponse
    {
        $response = $this->noteService->getWrittenNotes($request);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json($response->getData(), $response->getStatusCode(), [], ['groups' => 'note']);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Retrieves notes received by the current user based on the provided date.
     * @Route("/receivedNotes", name="receivedNotes", methods="GET")
     */
    #[route('/receivedNotes', name: 'receivedNotes', methods: ['GET'])]
    public function getReceivedNotes(Request $request): JsonResponse
    {
        $response = $this->noteService->getReceivedNotes($request);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json($response->getData(), $response->getStatusCode(), [], ['groups' => 'note']);
    }
}
