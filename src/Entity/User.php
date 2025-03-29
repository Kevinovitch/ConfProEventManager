<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * User entity with different roles for the system
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * User role constants
     */
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_MODERATOR = 'ROLE_MODERATOR';
    public const ROLE_PRESENTER = 'ROLE_PRESENTER';
    public const ROLE_PARTICIPANT = 'ROLE_PARTICIPANT';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\OneToMany(targetEntity: Conference::class, mappedBy: 'moderator')]
    private Collection $moderatedConferences;

    #[ORM\OneToMany(targetEntity: Conference::class, mappedBy: 'presenter', orphanRemoval: true)]
    private Collection $presentedConferences;

    #[ORM\OneToMany(targetEntity: ModerationRequest::class, mappedBy: 'moderator')]
    private Collection $moderationRequests;

    #[ORM\OneToMany(targetEntity: Registration::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $registrations;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->moderatedConferences = new ArrayCollection();
        $this->presentedConferences = new ArrayCollection();
        $this->moderationRequests = new ArrayCollection();
        $this->registrations = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return Collection<int, Conference>
     */
    public function getModeratedConferences(): Collection
    {
        return $this->moderatedConferences;
    }

    public function addModeratedConference(Conference $conference): static
    {
        if (!$this->moderatedConferences->contains($conference)) {
            $this->moderatedConferences->add($conference);
            $conference->setModerator($this);
        }

        return $this;
    }

    public function removeModeratedConference(Conference $conference): static
    {
        if ($this->moderatedConferences->removeElement($conference)) {
            // Set the owning side to null (unless already changed)
            if ($conference->getModerator() === $this) {
                $conference->setModerator(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Conference>
     */
    public function getPresentedConferences(): Collection
    {
        return $this->presentedConferences;
    }

    public function addPresentedConference(Conference $conference): static
    {
        if (!$this->presentedConferences->contains($conference)) {
            $this->presentedConferences->add($conference);
            $conference->setPresenter($this);
        }

        return $this;
    }

    public function removePresentedConference(Conference $conference): static
    {
        if ($this->presentedConferences->removeElement($conference)) {
            // Set the owning side to null (unless already changed)
            if ($conference->getPresenter() === $this) {
                $conference->setPresenter(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ModerationRequest>
     */
    public function getModerationRequests(): Collection
    {
        return $this->moderationRequests;
    }

    public function addModerationRequest(ModerationRequest $moderationRequest): static
    {
        if (!$this->moderationRequests->contains($moderationRequest)) {
            $this->moderationRequests->add($moderationRequest);
            $moderationRequest->setModerator($this);
        }

        return $this;
    }

    public function removeModerationRequest(ModerationRequest $moderationRequest): static
    {
        if ($this->moderationRequests->removeElement($moderationRequest)) {
            // Set the owning side to null (unless already changed)
            if ($moderationRequest->getModerator() === $this) {
                $moderationRequest->setModerator(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Registration>
     */
    public function getRegistrations(): Collection
    {
        return $this->registrations;
    }

    public function addRegistration(Registration $registration): static
    {
        if (!$this->registrations->contains($registration)) {
            $this->registrations->add($registration);
            $registration->setUser($this);
        }

        return $this;
    }

    public function removeRegistration(Registration $registration): static
    {
        if ($this->registrations->removeElement($registration)) {
            // Set the owning side to null (unless already changed)
            if ($registration->getUser() === $this) {
                $registration->setUser(null);
            }
        }

        return $this;
    }
}