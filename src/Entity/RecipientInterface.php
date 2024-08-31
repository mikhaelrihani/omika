<?php
namespace App\Entity;

interface RecipientInterface
{
    public function getFullName(): string;
    public function getPhone(): ?string;
   
}
