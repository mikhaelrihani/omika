<?php

namespace App\Validator;

use App\Entity\User\UserLogin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class UniqueEmailValidator extends ConstraintValidator
{

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function validate($value, Constraint $constraint)
    {
        $existingUser = $this->em->getRepository(UserLogin::class)->findOneBy(['email' => $value]);
        if ($existingUser) {
            $this->context
                ->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
