<?php

namespace App\Service\Media;

use App\Entity\Media\Note;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class NoteService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorService $validateService,
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

    public function createNote(array $data)
    {

    }

    //! --------------------------------------------------------------------------------------------

    public function updateNote(int $noteId, array $data)
    {

    }
    //! --------------------------------------------------------------------------------------------

    public function deleteNote(int $noteId)
    {

    }


}