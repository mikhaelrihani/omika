<?php

namespace App\Validator;

use App\Entity\Media\Picture;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class UniquePictureValidator extends ConstraintValidator
{
    
      
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function validate($value, Constraint $constraint)
    {
        $existingPicture = $this->em->getRepository(Picture::class)->findOneBy(['name' => $value]);
        if ($existingPicture) {
            $this->context
                ->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
