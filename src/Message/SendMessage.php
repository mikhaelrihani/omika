<?php

namespace App\Message;

class SendMessage
{
    private string $to;
    private string $body;
    private ?string $mediaUrl;
    private string $type; // 'sms', 'mms', or 'whatsapp'

    public function __construct(string $to, string $body, ?string $mediaUrl = null, string $type = 'sms')
    {
        $this->to = $to;
        $this->body = $body;
        $this->mediaUrl = $mediaUrl;
        $this->type = $type;
    }

    public function getTo(): string
    {
        return $this->to;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getMediaUrl(): ?string
    {
        return $this->mediaUrl;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
