<?php

namespace App\Entity;

use App\Enum\WinnerSource;
use App\Repository\WinnerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WinnerRepository::class)]
#[ORM\Table(name: 'winner')]
class Winner
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Game::class, inversedBy: 'winners')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Game $game = null;

    #[ORM\ManyToOne(targetEntity: Card::class)]
    private ?Card $card = null;

    #[ORM\Column(type: Types::STRING, enumType: WinnerSource::class, length: 20)]
    private WinnerSource $source = WinnerSource::SYSTEM;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $winningOrderIndex = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): self
    {
        $this->game = $game;

        return $this;
    }

    public function getCard(): ?Card
    {
        return $this->card;
    }

    public function setCard(?Card $card): self
    {
        $this->card = $card;

        return $this;
    }

    public function getSource(): WinnerSource
    {
        return $this->source;
    }

    public function setSource(WinnerSource $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getWinningOrderIndex(): int
    {
        return $this->winningOrderIndex;
    }

    public function setWinningOrderIndex(int $winningOrderIndex): self
    {
        $this->winningOrderIndex = $winningOrderIndex;

        return $this;
    }
}
