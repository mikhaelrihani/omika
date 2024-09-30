<?php

namespace App\MessageHandler;

use App\Message\SendMessage;
use App\Service\TwilioService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendMessageHandler
{
    private TwilioService $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }

    public function __invoke(SendMessage $message): void
    {
        $to = $message->getTo();
        $body = $message->getBody();
        $mediaUrl = $message->getMediaUrl();
        $type = $message->getType();

        // Envoie le message selon le type (SMS, MMS, WhatsApp)
        switch ($type) {
            case 'sms':
                $this->twilioService->sendSms($to, $body);
                break;
            case 'mms':
                $this->twilioService->sendMms($to, $body, $mediaUrl);
                break;
            case 'whatsapp':
                $this->twilioService->sendWhatsapp($to, $body, $mediaUrl);
                break;
            default:
                throw new \InvalidArgumentException("Unknown message type: " . $type);
        }
    }
}
