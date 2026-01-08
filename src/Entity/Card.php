<?php

namespace App\Entity;

use App\Enum\BlockedReason;
use App\Repository\CardRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CardRepository::class)]
#[ORM\Table(name: 'card')]
class Card
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $reference = '';

    // Store as JSON: array of 3 arrays, each with 5 integers
    #[ORM\Column(type: Types::JSON)]
    private array $grid = [];

    #[ORM\ManyToOne(targetEntity: Player::class, inversedBy: 'cards')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Player $player = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isBlocked = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $blockedAt = null;

    #[ORM\Column(type: Types::STRING, enumType: BlockedReason::class, length: 20, nullable: true)]
    private ?BlockedReason $blockedReason = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getGrid(): array
    {
        return $this->grid;
    }

    public function setGrid(array $grid): self
    {
        $this->grid = $grid;

        return $this;
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setPlayer(?Player $player): self
    {
        $this->player = $player;

        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->player?->getEvent();
    }

    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }

    public function setIsBlocked(bool $isBlocked): self
    {
        $this->isBlocked = $isBlocked;

        return $this;
    }

    public function getBlockedAt(): ?\DateTimeImmutable
    {
        return $this->blockedAt;
    }

    public function setBlockedAt(?\DateTimeImmutable $blockedAt): self
    {
        $this->blockedAt = $blockedAt;

        return $this;
    }

    public function getBlockedReason(): ?BlockedReason
    {
        return $this->blockedReason;
    }

    public function setBlockedReason(?BlockedReason $blockedReason): self
    {
        $this->blockedReason = $blockedReason;

        return $this;
    }

    public function block(BlockedReason $reason): self
    {
        $this->isBlocked = true;
        $this->blockedAt = new \DateTimeImmutable();
        $this->blockedReason = $reason;

        return $this;
    }

    public function unblock(): self
    {
        $this->isBlocked = false;
        $this->blockedAt = null;
        $this->blockedReason = null;

        return $this;
    }
}
