<?php

namespace App\Service\Media;

use App\Entity\Media\Note;
use App\Entity\User\User;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use App\Utils\CurrentUser;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class NoteService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorService $validateService,
        protected SerializerInterface $serializer,
        protected CurrentUser $currentUser
    ) {
    }

    //! --------------------------------------------------------------------------------------------

    public function getNote(int $noteId): ApiResponse
    {
        $note = $this->em->getRepository(Note::class)->find($noteId);
        if (!$note) {
            return ApiResponse::error('Note not found.', null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::success('Note found.', ["note" => $note], Response::HTTP_OK);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Creates a new note and associates it with recipients.
     *
     * @param Request $request The HTTP request containing the JSON data for the note.
     *
     * @return ApiResponse Returns an ApiResponse with the result of the operation.
     *                     - Success: Returns the created note and HTTP_CREATED status.
     *                     - Error: Returns an error message and appropriate HTTP status.
     *
     * The request JSON must have the following structure:
     * ```json
     * {
     *   "text": "The content of the note",
     *   "users": [1, 2, 3] // Array of recipient user IDs
     * }
     * ```
     * Behavior:
     * - The note is associated with the current user as the author.
     * - Recipients are filtered to exclude the current user.
     * - Validation is performed on the note entity before persisting.
     * - If any recipient user is not found, an error response is returned.
     * - On success, the note is persisted, and a success response is returned.
     */
    public function createNote(Request $request): ApiResponse
    {

        $responseData = $this->validateService->validateJson($request);
        if (!$responseData->isSuccess()) {
            return ApiResponse::error($responseData->getMessage(), null, $responseData->getStatusCode());
        }


        $currentUser = $this->currentUser->getCurrentUser();
        $note = (new Note())
            ->setText($responseData->getData()[ 'text' ]);

        $currentUser->addWrittenNote($note);
        $users = $responseData->getData()[ 'users' ];
        $users = array_filter($users, function ($user) use ($currentUser) {
            return $user !== $currentUser->getId();
        });

        foreach ($users as $user) {
            $user = $this->em->getRepository(User::class)->find($user);
            if (!$user) {
                return ApiResponse::error('User not found.', null, Response::HTTP_NOT_FOUND);
            }
            $note->addRecipient($user);
        }
        $responseValidation = $this->validateService->validateEntity($note);
        if (!$responseValidation->isSuccess()) {
            return ApiResponse::error($responseValidation->getMessage(), null, $responseValidation->getStatusCode());
        }

        $this->em->persist($note);
        $this->em->flush();

        return ApiResponse::success("Absence created successfully", ["note" => $note], Response::HTTP_CREATED);
    }



    //! --------------------------------------------------------------------------------------------
    /**
     * Retrieves notes written by the current user and filters them based on the given date.
     *
     * @param Request $request The HTTP request containing a JSON payload with the 'date' parameter.
     * @return ApiResponse The response containing the filtered notes or an error message.
     */
    public function getWrittenNotes(Request $request): ApiResponse
    {
        $currentUser = $this->currentUser->getCurrentUser();
        $notes = $currentUser->getWrittenNotes();

        return $this->getNotes($notes, $request);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Retrieves notes received by the current user and filters them based on the given date.
     *
     * @param Request $request The HTTP request containing a JSON payload with the 'date' parameter.
     * @return ApiResponse The response containing the filtered notes or an error message.
     */
    public function getReceivedNotes(Request $request): ApiResponse
    {
        $currentUser = $this->currentUser->getCurrentUser();
        $notes = $currentUser->getReceivedNotes();

        return $this->getNotes($notes, $request);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Filters a collection of notes based on the provided date from the request.
     *
     * @param Collection<int, Note> $notes A collection of notes to filter.
     * @param Request $request The HTTP request containing a JSON payload with the 'date' parameter.
     * @return ApiResponse The response containing the filtered notes or an error message.
     */
    private function getNotes(Collection $notes, Request $request): ApiResponse
    {
        $responseData = $this->validateService->validateJson($request);
        if (!$responseData->isSuccess()) {
            return ApiResponse::error($responseData->getMessage(), null, $responseData->getStatusCode());
        }
        $date = $responseData->getData()[ 'date' ];

        if (!$notes) {
            return ApiResponse::error('No notes found.', null, Response::HTTP_NOT_FOUND);
        }

        $notes = $notes->filter(function (Note $note) use ($date) {
            return $note->getCreatedAt()->format('Y-m-d') === $date;
        });

        return ApiResponse::success('Notes found.', ["notes" => $notes], Response::HTTP_OK);
    }

}