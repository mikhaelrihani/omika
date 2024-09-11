<?php
// src/Controller/TestController.php
namespace App\Controller;

use App\Scheduler\Message\CleanupTokensMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class TestController extends AbstractController
{
    #[Route('/test')]
    public function test(MessageBusInterface $bus): Response
    {
        // Envoi manuel du message CleanupTokensMessage
        $bus->dispatch(new CleanupTokensMessage());

        return new Response('Message CleanupTokensMessage envoyé.');
    }
}
