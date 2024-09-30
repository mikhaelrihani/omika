<?php
namespace App\Service;

use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

class TwilioService
{
    private $from;
    private $client;

    public function __construct(string $accountSid, string $authToken, string $from)
    {
        $this->from = $from;

        $this->client = new Client($accountSid, $authToken);
    }

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

    public function sendSms(string $to, string $body): void
    {
        $this->sendMessage($this->from, $to, $body);
    }
    
    public function sendMms(string $to, string $body, ?string $mediaUrl = null): void
    {
        $this->sendMessage($this->from, $to, $body, $mediaUrl);
    }
    
    public function sendWhatsapp(string $to, string $body, ?string $mediaUrl = null): void
    {
        $whatsappFrom = "whatsapp:" . $this->from;
        $whatsappTo = "whatsapp:" . $to;
        $this->sendMessage($whatsappFrom, $whatsappTo, $body, $mediaUrl);
    }
    

   
}
