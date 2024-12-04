<?php

namespace App\Controller\Media;

use App\Controller\BaseController;
use App\Entity\User\Contact;
use App\Service\EmailFacadeService;
use App\Service\MailerService;
use App\Service\ValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[Route('api/email', name: "app_email_")]
class EmailController extends BaseController
{
    public function __construct(
        private EmailFacadeService $emailFacadeService,
        private MailerService $mailerService,
        private EntityManagerInterface $em,
        private ValidatorService $validatorService
    ) {
    }

    /**
     * Route pour envoyer un email via le service MailerService.
     * 
     * @return Response Retourne un message indiquant le succès ou l'échec de l'envoi.
     */
    #[Route('/sendEmail', name: 'sendEmail', methods: ['POST'])]
    public function sendEmail(): Response
    {
        try {
            $this->mailerService->sendEmail();
            return new JsonResponse('Email sent successfully', Response::HTTP_OK);
        } catch (Exception $e) {
            return new JsonResponse('Failed to send email: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! --------------------------------------------------------------------------------------------
    /**
     * Route pour envoyer un email de bienvenue via le service EmailFacadeService.
     * 
     * @return Response Retourne un message indiquant le succès ou l'échec de l'envoi.
     */
    #[Route('/sendWelcomeEmail', name: 'sendWelcomeEmail', methods: ['POST'])]
    public function sendWelcomeEmail(): Response
    {
        try {
            $this->emailFacadeService->sendWelcomeEmail();
            return new JsonResponse('Welcome email sent successfully', Response::HTTP_OK);
        } catch (Exception $e) {
            return new JsonResponse('Failed to send welcome email: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! ---------------------------------------------------------------------------------------------

    /**
     * Retrieve the email template associated with a specific contact.
     *
     * @Route("/getEmailTemplate/{id}", name="getEmailTemplate", methods={"GET"})
     *
     * @param int $id The ID of the contact whose email template is to be retrieved.
     *
     * @return JsonResponse JSON response containing the email template or an error message if the contact is not found.
     *
     * @throws JsonResponse If the contact is not found, returns a 404 response.
     */
    #[Route('/getEmailTemplate/{id}', name: 'getEmailTemplate', methods: ['GET'])]
    public function getEmailTemplate(int $id): JsonResponse
    {
        $contact = $this->em->getRepository(Contact::class)->find($id);
        if (null === $contact) {
            return new JsonResponse('Contact not found', Response::HTTP_NOT_FOUND);
        }
        $emailTemplate = $contact->getEmailTemplate() ?? null;
        return new JsonResponse(["emailTemplate" => $emailTemplate], Response::HTTP_OK);
    }
    //! ---------------------------------------------------------------------------------------------

    /**
     * Set the email template for a specific contact.
     *
     * @Route("/setEmailTemplate/{id}", name="setEmailTemplate", methods={"POST"})
     *
     * @param Request $request The HTTP request containing the contact ID and the new email template data.
     *
     * @return JsonResponse JSON response indicating the success or failure of the operation.
     *
     * @throws JsonResponse If the provided JSON payload is invalid, returns a validation error response.
     * @throws JsonResponse If the contact is not found, returns a 404 response.
     */
    #[Route('/setEmailTemplate/{id}', name: 'setEmailTemplate', methods: ['POST'])]
    public function setEmailTemplate(Request $request): JsonResponse
    {
        $responseData = $this->validatorService->validateJson($request);
        if (!$responseData->isSuccess()) {
            return new JsonResponse($responseData->getMessage(), $responseData->getStatusCode());
        }
        $contact = $this->em->getRepository(Contact::class)->find($request->get('id'));
        if (null === $contact) {
            return new JsonResponse('Contact not found', Response::HTTP_NOT_FOUND);
        }
        $contact->setEmailTemplate($responseData->getData()[ 'emailTemplate' ]);
        $this->em->flush();
        return new JsonResponse('Email template set successfully', Response::HTTP_OK);
    }

}
