<?php

namespace App\EventSubscriber;

use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function onKernelException(ExceptionEvent $event): void
    {
        // $exception = $event->getThrowable();
        // $response = new JsonResponse();

        // // Initialisation des valeurs par défaut pour le statut et le message
        // $statusCode = JsonResponse::HTTP_INTERNAL_SERVER_ERROR;
        // $message = 'An unexpected error occurred.';
        
        // // Si c'est une HttpException, récupérer le code de statut et le message
        // if ($exception instanceof HttpExceptionInterface) {
        //     $statusCode = $exception->getStatusCode();
        //     $message = $exception->getMessage();
        // } else {
        //     // Si ce n'est pas une HttpException, vérifier d'autres types d'exception
        //     switch (true) {
        //         case $exception instanceof \InvalidArgumentException:
        //             $statusCode = JsonResponse::HTTP_BAD_REQUEST;
        //             $message = 'Invalid argument: ' . $exception->getMessage();
        //             break;
        //         case $exception instanceof \LogicException:
        //             $statusCode = JsonResponse::HTTP_UNPROCESSABLE_ENTITY; // Code 422
        //             $message = 'A logic error occurred: ' . $exception->getMessage();
        //             break;
        //         default:
        //             // Pour les autres exceptions, utiliser les valeurs par défaut
        //             $message = $exception->getMessage(); // Capturer le message de l'exception générique
        //             break;
        //     }
        // }

        // // Préparation de la réponse en JSON
        // $response->setData([
        //     'status' => $statusCode,
        //     'error' => $message,
        // ]);

        // $response->setStatusCode($statusCode);
        
        // // Définir la réponse dans l'événement pour que Symfony renvoie cette réponse
        // $event->setResponse($response);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.exception' => 'onKernelException',
        ];
    }
}
