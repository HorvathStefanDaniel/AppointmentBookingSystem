<?php

namespace App\Security\Voter;

use App\Entity\Booking;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class BookingVoter extends Voter
{
    public const VIEW = 'BOOKING_VIEW';
    public const MANAGE = 'BOOKING_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::MANAGE], true)) {
            return false;
        }

        return $subject instanceof Booking;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Booking $booking */
        $booking = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($booking, $user),
            self::MANAGE => $this->canManage($booking, $user),
            default => false,
        };
    }

    private function canView(Booking $booking, User $user): bool
    {
        if ($this->hasRole($user, 'R_ADMIN')) {
            return true;
        }

        if ($booking->getUser()?->getId() === $user->getId()) {
            return true;
        }

        $bookingProviderId = $booking->getProvider()?->getId();
        if ($this->hasRole($user, 'R_PROVIDER') && $bookingProviderId !== null && $user->getProvider()?->getId() === $bookingProviderId) {
            return true;
        }

        return false;
    }

    private function canManage(Booking $booking, User $user): bool
    {
        if ($this->hasRole($user, 'R_ADMIN')) {
            return true;
        }

        if ($booking->getUser()?->getId() === $user->getId()) {
            return true;
        }

        $bookingProviderId = $booking->getProvider()?->getId();
        if ($this->hasRole($user, 'R_PROVIDER') && $bookingProviderId !== null && $user->getProvider()?->getId() === $bookingProviderId) {
            return true;
        }

        return false;
    }

    private function hasRole(User $user, string $role): bool
    {
        return in_array($role, $user->getRoles(), true);
    }
}

