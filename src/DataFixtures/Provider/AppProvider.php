<?php

namespace App\DataFixtures\Provider;

use Faker\Generator;
use Faker\Provider\Base;

class AppProvider extends Base
{
 
    public function dateTimeImmutableBetween($startDate = '-30 years', $endDate = 'now')
    {
        $dateTime = $this->generator->dateTimeBetween($startDate, $endDate);
        return \DateTimeImmutable::createFromMutable($dateTime);
    }

    public static function generatePassword(): string
    {
        $length = 10;
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $specialChars = '!@#$%^&*';
        $numbers = '0123456789';

        // Generate a password
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $specialChars[random_int(0, strlen($specialChars) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];

        // Fill the rest of the password length with random characters
        $allChars = $uppercase . $specialChars . $numbers . strtolower($uppercase);
        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle the password to avoid predictable patterns
        return str_shuffle($password);
    }

    public static function generatePhoneNumber(): string
    {
        // Générer un numéro de téléphone de 10 chiffres
        $number = sprintf('%010d', rand(0, 9999999999));
        
        // Ajouter un préfixe '+' de manière aléatoire pour simuler un code international
        $prefix = rand(0, 1) ? '+' : '';
        
        // Ajouter des espaces de manière aléatoire dans le numéro pour le format
        $formattedNumber = $prefix . implode(' ', str_split($number, 2));
        
        // S'assurer que la longueur totale est entre 10 et 20 caractères
        // Si le numéro est trop court, ajouter des espaces jusqu'à 20 caractères
        $maxLength = 20;
        $formattedNumber = str_pad($formattedNumber, $maxLength, ' ', STR_PAD_RIGHT);

        // Limiter à une longueur maximale de 20 caractères
        return substr($formattedNumber, 0, $maxLength);
    }


    private $roles = [
        ["ROLE_USER"],
        ["ROLE_ADMIN"],
        ["ROLE_SUPER_ADMIN"]
    ];
   
    public function role()
    {
        return $this->roles[array_rand($this->roles)];
    }
    
    private $business = [
        "3Brasseurs",
        "FraisImport",
        "ProAPro",
        "Runlog",
        "Victoria",
        "MartinPecheur",
        "ArmementDesMascareignes",
        "SalaisonsdeBourbon"
    ];
   
    public function business()
    {
        return $this->business[array_rand($this->business)];
    }
}