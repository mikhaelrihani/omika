<?php

namespace App\Service;

use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

/**
 * Class TwilioService
 * @package App\Service
 * 
 * Service that handles sending SMS, MMS, and WhatsApp messages using Twilio's API.
 */
class TwilioService
{
    /**
     * @var string Twilio phone number or sender ID.
     */
    private string $from;

    /**
     * @var Client Twilio REST client instance.
     */
    private Client $client;

    /**
     * TwilioService constructor.
     *
     * @param string $accountSid Twilio Account SID.
     * @param string $authToken Twilio Auth Token.
     * @param string $from Sender phone number or ID from Twilio.
     */
    public function __construct(string $accountSid, string $authToken, string $from)
    {
        $this->from = $from;
        $this->client = new Client($accountSid, $authToken);
    }

    /**
     * Sends a message (SMS, MMS, or WhatsApp) using Twilio.
     *
     * @param string $from The sender's number or ID.
     * @param string $to The recipient's phone number.
     * @param string $body The message content.
     * @param string|null $mediaUrl Optional URL of media for MMS or WhatsApp.
     * 
     * @throws \Exception If there is an error during message sending.
     */
    private function sendMessage(string $from, string $to, string $body, ?string $mediaUrl = null): void
    {
        $options = [
            'from' => $from,
            'body' => PHP_EOL . $body . PHP_EOL,
        ];

        if ($mediaUrl) {
            $options['mediaUrl'] = [$mediaUrl];
        }

        try {
            $this->client->messages->create($to, $options);
        } catch (RestException $e) {
            throw new \Exception("Error sending message: " . $e->getMessage());
        }
    }

    /**
     * Sends an SMS message via Twilio.
     *
     * @param string $to The recipient's phone number.
     * @param string $body The SMS message content.
     * 
     * @throws \Exception If there is an error during SMS sending.
     */
    public function sendSms(string $to, string $body): void
    {
        $this->sendMessage($this->from, $to, $body);
    }

    /**
     * Sends an MMS message via Twilio.
     *
     * @param string $to The recipient's phone number.
     * @param string $body The MMS message content.
     * @param string|null $mediaUrl Optional URL of the media file.
     * 
     * @throws \Exception If there is an error during MMS sending.
     */
    public function sendMms(string $to, string $body, ?string $mediaUrl = null): void
    {
        $this->sendMessage($this->from, $to, $body, $mediaUrl);
    }

    /**
     * Sends a WhatsApp message via Twilio.
     *
     * @param string $to The recipient's WhatsApp number (e.g., '+1234567890').
     * @param string $body The WhatsApp message content.
     * @param string|null $mediaUrl Optional URL of the media file.
     * 
     * @throws \Exception If there is an error during WhatsApp message sending.
     */
    public function sendWhatsapp(string $to, string $body, ?string $mediaUrl = null): void
    {
        $whatsappFrom = "whatsapp:" . $this->from;
        $whatsappTo = "whatsapp:" . $to;
        $this->sendMessage($whatsappFrom, $whatsappTo, $body, $mediaUrl);
    }
}
