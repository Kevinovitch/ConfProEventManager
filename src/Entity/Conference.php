<?php

namespace App\Entity;

use App\Repository\ConferenceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Conference entity representing professional events
 */
#[ORM\Entity(repositoryClass: ConferenceRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['title'], message: 'A conference with this title already exists')]
class Conference
{
    /**
     * Conference status constants
     */
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_VALIDATION = 'under_validation';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ARCHIVED = 'archived';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $status = self::STATUS_SUBMITTED;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'moderatedConferences')]
    private ?User $moderator = null;

    #[ORM\ManyToOne(inversedBy: 'presentedConferences')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $presenter = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\OneToMany(targetEntity: ModerationRequest::class, mappedBy: 'conference', orphanRemoval: true)]
    private Collection $moderationRequests;

    #[ORM\OneToMany(targetEntity: Session::class, mappedBy: 'conference', orphanRemoval: true)]
    private Collection $sessions;

    #[ORM\OneToMany(targetEntity: Registration::class, mappedBy: 'conference', orphanRemoval: true)]
    private Collection $registrations;

    #[ORM\OneToMany(targetEntity: Media::class, mappedBy: 'conference', orphanRemoval: true)]
    private Collection $media;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->moderationRequests = new ArrayCollection();
        $this->sessions = new ArrayCollection();
        $this->registrations = new ArrayCollection();
        $this->media = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!in_array($status, [
            self::STATUS_SUBMITTED,
            self::STATUS_UNDER_VALIDATION,
            self::STATUS_SCHEDULED,
            self::STATUS_ARCHIVED
        ])) {
            throw new \InvalidArgumentException('Invalid status');
        }

        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getModerator(): ?User
    {
        return $this->moderator;
    }

    public function setModerator(?User $moderator): static
    {
        $this->moderator = $moderator;
        return $this;
    }

    public function getPresenter(): ?User
    {
        return $this->presenter;
    }

    public function setPresenter(?User $presenter): static
    {
        $this->presenter = $presenter;
        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;
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
            $moderationRequest->setConference($this);
        }

        return $this;
    }

    public function removeModerationRequest(ModerationRequest $moderationRequest): static
    {
        if ($this->moderationRequests->removeElement($moderationRequest)) {
            // Set the owning side to null (unless already changed)
            if ($moderationRequest->getConference() === $this) {
                $moderationRequest->setConference(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Session>
     */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }

    public function addSession(Session $session): static
    {
        if (!$this->sessions->contains($session)) {
            $this->sessions->add($session);
            $session->setConference($this);
        }

        return $this;
    }

    public function removeSession(Session $session): static
    {
        if ($this->sessions->removeElement($session)) {
            // Set the owning side to null (unless already changed)
            if ($session->getConference() === $this) {
                $session->setConference(null);
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
            $registration->setConference($this);
        }

        return $this;
    }

    public function removeRegistration(Registration $registration): static
    {
        if ($this->registrations->removeElement($registration)) {
            // Set the owning side to null (unless already changed)
            if ($registration->getConference() === $this) {
                $registration->setConference(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Media>
     */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    public function addMedia(Media $media): static
    {
        if (!$this->media->contains($media)) {
            $this->media->add($media);
            $media->setConference($this);
        }

        return $this;
    }

    public function removeMedia(Media $media): static
    {
        if ($this->media->removeElement($media)) {
            // Set the owning side to null (unless already changed)
            if ($media->getConference() === $this) {
                $media->setConference(null);
            }
        }

        return $this;
    }
}