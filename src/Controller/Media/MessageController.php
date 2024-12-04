<?php

namespace App\Controller\Media;

use App\Controller\BaseController;
use App\Service\TwilioService;
use App\Service\User\ContactService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class MessageController
 * @package App\Controller\Media
 * 
 * This controller handles sending SMS, MMS, and WhatsApp messages using Twilio.
 */
#[Route('/api/message', name: "app_message_")]
class MessageController extends BaseController
{

    public function __construct(
        private ParameterBagInterface $params,
        private TwilioService $twilioService,
        private EntityManagerInterface $em,
        private ContactService $contactService
    ) {
    }

    /**
     * Sends an SMS via Twilio.
     *
     * @Route('/sendSms', name='sendSms', methods=['POST'])
     *
     * @param Request $request Incoming request containing "to" and "body" parameters.
     * @return Response JSON response indicating success or failure.
     */
    #[Route('/sendSms', "sendSms", methods: ['POST'])]
    public function sendSms(Request $request): Response
    {
        $parameters = $this->getTwilioParameters($request);

        try {
            $this->twilioService->sendsms($parameters[ 'to' ], $parameters[ 'body' ]);
            return $this->json(['message' => 'SMS sent successfully.'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to send SMS: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    //! ------------------------------------------------------------------------------------------------

    /**
     * Sends an MMS via Twilio.
     *
     * @Route('/sendMms', name='sendMms', methods=['POST'])
     *
     * @param Request $request Incoming request containing "to", "body", and "mediaUrl" or "file".
     * @return Response JSON response indicating success or failure.
     */
    #[Route('/sendMms', "sendMms", methods: ['POST'])]
    public function sendMms(Request $request): Response
    {
        $parameters = $this->getTwilioParameters($request);
        if ($parameters instanceof JsonResponse) {
            return $parameters;
        }

        try {
            $this->twilioService->sendMms($parameters[ 'to' ], $parameters[ 'body' ], $parameters[ 'mediaUrl' ]);

            // Remove temporary file if uploaded
            if ($parameters[ 'fileName' ]) {
                $fileTempPath = $this->params->get('upload_directory') . '/' . $parameters[ 'fileName' ];
                if (file_exists($fileTempPath)) {
                    unlink($fileTempPath);
                }
            }

            return $this->json(['message' => 'MMS sent successfully.'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    //! ------------------------------------------------------------------------------------------------

    /**
     * Sends a WhatsApp message via Twilio.
     *
     * @Route('/sendWhatsapp', name='sendWhatsapp', methods=['POST'])
     *
     * @param Request $request Incoming request containing "to", "body", and optional "mediaUrl" or "file".
     * @return Response JSON response indicating success or failure.
     */
    #[Route('/sendWhatsApp', "sendWhatsApp", methods: ['POST'])]
    public function sendWhatsapp(Request $request): Response
    {
        $parameters = $this->getTwilioParameters($request);

        try {
            $this->twilioService->sendWhatsapp($parameters[ 'to' ], $parameters[ 'body' ], $parameters[ 'mediaUrl' ]);
            return $this->json(['message' => 'WhatsApp sent successfully.'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to send WhatsApp: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! ------------------------------------------------------------------------------------------------
    /**
     * Extracts parameters from the request for sending a message via Twilio.
     *
     * @param Request $request The incoming HTTP request containing form data.
     * @return array|JsonResponse An array of parameters or a JSON response with an error.
     */
    private function getTwilioParameters(Request $request)
    {
        $to = $request->request->get('to');
        $body = $request->request->get('body');

        if (!$to || !$body) {
            return $this->json(['error' => 'Missing required parameters.'], Response::HTTP_BAD_REQUEST);
        }

        $parameters = [
            'to'       => $to,
            'body'     => $body,
            'fileName' => null,
            'mediaUrl' => null,
        ];

        // Check if a file is being uploaded
        $file = $request->files->get('file');
        if ($file) {
            $fileName = $file->getClientOriginalName();
            $parameters[ 'fileName' ] = $fileName;
            $file->move($this->params->get('upload_directory'), $fileName);
            // Generate the public URL for the uploaded file
            $mediaUrl = $this->getParameter('twillio_file_upload_public') . urlencode($fileName);

        } else {
            // If no file is uploaded, check if a media URL is provided
            $mediaUrl = $request->request->get('mediaUrl');
            if (!$mediaUrl) {
                $mediaUrl = null;
            }
        }

        $parameters[ 'mediaUrl' ] = $mediaUrl;

        return $parameters;
    }

    //! ------------------------------------------------------------------------------------------------

    /**
     * Autocompletes contact names based on partial input.
     *
     * @Route("/findContact", name="findContact", methods={"GET"})
     *
     * @param Request $request HTTP request containing the 'query' parameter.
     *
     * @return JsonResponse JSON response with a list of possible contacts.
     */
    #[Route("/findContact", name: "findContact", methods: ["GET"])]
    public function findContact(Request $request): JsonResponse
    {
        $responseData = $this->contactService->autocompleteContact($request);
        if (!$responseData->isSuccess()) {
            return $this->json($responseData->getMessage(), $responseData->getStatusCode());
        }
        return $this->json(["message" => $responseData->getMessage(), "contacts" => $responseData->getData()[ "contacts" ]], $responseData->getStatusCode(), [], ['groups' => 'contact']);

    }


}
