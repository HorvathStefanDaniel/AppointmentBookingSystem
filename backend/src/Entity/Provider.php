<?php

namespace App\Entity;

use App\Repository\ProviderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProviderRepository::class)]
class Provider
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\OneToMany(mappedBy: 'provider', targetEntity: ProviderWorkingHours::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $workingHours;

    #[ORM\OneToMany(mappedBy: 'provider', targetEntity: User::class)]
    private Collection $users;

    #[ORM\OneToMany(mappedBy: 'provider', targetEntity: Booking::class, orphanRemoval: true)]
    private Collection $bookings;

    public function __construct()
    {
        $this->workingHours = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->bookings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, ProviderWorkingHours>
     */
    public function getWorkingHours(): Collection
    {
        return $this->workingHours;
    }

    public function addWorkingHour(ProviderWorkingHours $workingHour): self
    {
        if (!$this->workingHours->contains($workingHour)) {
            $this->workingHours->add($workingHour);
            $workingHour->setProvider($this);
        }

        return $this;
    }

    public function removeWorkingHour(ProviderWorkingHours $workingHour): self
    {
        if ($this->workingHours->removeElement($workingHour)) {
            if ($workingHour->getProvider() === $this) {
                $workingHour->setProvider(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setProvider($this);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->removeElement($user)) {
            if ($user->getProvider() === $this) {
                $user->setProvider(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): self
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setProvider($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): self
    {
        if ($this->bookings->removeElement($booking)) {
            if ($booking->getProvider() === $this) {
                $booking->setProvider(null);
            }
        }

        return $this;
    }
}
