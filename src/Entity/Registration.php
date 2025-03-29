<?php

namespace App\Entity;

use App\Repository\RegistrationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;

/**
 * Entity for tracking participant registrations to conferences
 */
#[ORM\Entity(repositoryClass: RegistrationRepository::class)]
#[ORM\UniqueConstraint(fields: ['user', 'conference'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['user', 'conference'], message: 'You are already registered for this conference')]
class Registration
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'registrations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'registrations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Conference $conference = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $registeredAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $qrCode = null;

    #[ORM\Column]
    private bool $attended = false;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->registeredAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getConference(): ?Conference
    {
        return $this->conference;
    }

    public function setConference(?Conference $conference): static
    {
        $this->conference = $conference;
        return $this;
    }

    public function getRegisteredAt(): ?\DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function getQrCode(): ?string
    {
        return $this->qrCode;
    }

    public function setQrCode(?string $qrCode): static
    {
        $this->qrCode = $qrCode;
        return $this;
    }

    public function isAttended(): bool
    {
        return $this->attended;
    }

    public function setAttended(bool $attended): static
    {
        $this->attended = $attended;
        return $this;
    }

    /**
     * Generate a unique QR code for this registration
     */
    public function generateQrCode(): void
    {
        // In a real app, you'd use a dedicated library for QR codes
        // Here we just generate a unique string that could be used to create a QR code
        $this->qrCode = md5($this->id . $this->user->getId() . $this->conference->getId() . time());
    }
}