<?php

namespace App\Controller\Event;

use App\Repository\Event\TagRepository;
use App\Service\Event\TagService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/api/tag', name: "app_tag_")]
class TagController extends AbstractController
{
    public function __construct(private TagRepository $tagRepository, private TagService $tagService)
    {
    }

    #[Route('/getTag/{id}', name: 'getTag', methods: ['GET'])]
    public function getTag(int $id): JsonResponse
    {
        $tag = $this->tagRepository->find($id);
        return $this->json($tag, 200, [], ['groups' => 'tag']);
    }
}
