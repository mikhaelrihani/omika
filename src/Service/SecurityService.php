<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Class SecurityService
 * 
 * This service handles user security operations such as password management,
 * email validation, and sending password reset links.
 */
class SecurityService
{
    private EntityManagerInterface $em;
    private UserProviderInterface $userProvider;
    private EmailFacadeService $emailFacadeService;
    protected UserPasswordHasherInterface $userPasswordHasher;
    protected RequestStack $requestStack;

    /**
     * SecurityService constructor.
     * 
     * @param RequestStack $requestStack
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @param EntityManagerInterface $em
     * @param UserProviderInterface $userProvider
     * @param EmailFacadeService $emailFacadeService
     */
    public function __construct(
        RequestStack $requestStack, 
        UserPasswordHasherInterface $userPasswordHasher, 
        EntityManagerInterface $em, 
        UserProviderInterface $userProvider, 
        EmailFacadeService $emailFacadeService
    ) {
        $this->em = $em;
        $this->userProvider = $userProvider;
        $this->emailFacadeService = $emailFacadeService;
        $this->userPasswordHasher = $userPasswordHasher;
        $this->requestStack = $requestStack;
    }

    /**
     * Retrieves the email from the current request and validates it.
     * 
     * @return string The validated email address.
     * 
     * @throws Exception If the email is empty or invalid.
     */
    public function getEmail(): string
    {
        $data = json_decode($this->requestStack->getCurrentRequest()->getContent(), true);
        $email = $data['email'];
        if (empty($email)) {
            throw new Exception('Email value is empty');
        }

        // Email format validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format.');
        }

        return $email;
    }

    /**
     * Refreshes the user's password after validating the user and hashing the new password.
     * 
     * @param string $email The user's email.
     * @param string $newPassword The new password to be set.
     * 
     * @throws Exception If the user is not found, inactive, or doesn't implement PasswordAuthenticatedUserInterface.
     */
    public function refreshPassword(string $email, string $newPassword): bool
    {
        $user = $this->userProvider->loadUserByIdentifier($email);
        if (!$user) {
            throw new Exception('Invalid credentials or user not found');
        }

        // Check if the user is enabled
        if (!$user->isEnabled()) {
            throw new Exception('This account is inactive.');
        }

        // Hash the new password if user implements PasswordAuthenticatedUserInterface
        if ($user instanceof PasswordAuthenticatedUserInterface) {
            $newPassword = $this->userPasswordHasher->hashPassword($user, $newPassword);
        } else {
            throw new Exception('User does not implement PasswordAuthenticatedUserInterface');
        }

        // Set the new hashed password and save it to the database
        $user->setPassword($newPassword);
        $this->em->flush();

        return true;
    }

    /**
     * Sends a password reset link to the user if they are found and enabled.
     * 
     * @param string $email The user's email.
     * @param string $link The password reset link.
     * 
     * @throws Exception If the user is not found or is not enabled.
     */
    public function sendPasswordLink(string $email, string $link): void
    {
        $user = $this->userProvider->loadUserByIdentifier($email);
        if (!$user) {
            throw new Exception('User not found, check the email value');
        }

        // Check if the user is enabled before sending the password reset link
        if ($user->isEnabled()) {
            $this->emailFacadeService->sendPasswordLink();
        } else {
            throw new Exception('User is not enabled, so we cannot renew the password');
        }
    }

    /**
     * Validates the strength of the given password.
     * 
     * @param string $password The password to validate.
     * 
     * @return bool True if the password meets the strength criteria, false otherwise.
     */
    public function checkPasswordStrenght(string $password): bool
    {
        return (
            strlen($password) >= 10 &&                  // Minimum length of 10 characters
            preg_match('/[A-Z]/', $password) &&         // At least one uppercase letter
            preg_match('/[0-9]/', $password) &&         // At least one number
            preg_match('/[\W]/', $password)             // At least one special character (non-alphanumeric)
        );
    }

    /**
     * Checks if the new password and its confirmation match and meet the strength criteria.
     * 
     * @return string The validated new password.
     * 
     * @throws \LogicException If required fields are missing, passwords are empty, do not match, or do not meet strength criteria.
     */
    public function checkNewPassword(): string
    {
        $data = json_decode($this->requestStack->getCurrentRequest()->getContent(), true);
        $newPassword = $data['newPassword'];

        if (!isset($newPassword, $data['confirmPassword'])) {
            throw new \LogicException('Missing required fields (newPassword or confirmPassword)');
        }

        if (empty($newPassword) || empty($data['confirmPassword'])) {
            throw new \LogicException('Password cannot be empty');
        }

        if ($newPassword !== $data['confirmPassword']) {
            throw new \LogicException('Passwords do not match');
        }

        if (!$this->checkPasswordStrenght($newPassword)) {
            throw new \LogicException('Password must be at least 10 characters long, contain at least one uppercase letter, one number, and one special character.');
        }

        return $newPassword; // Returns the validated new password
    }
}
