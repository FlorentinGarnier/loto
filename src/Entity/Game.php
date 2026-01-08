<?php

namespace App\Entity;

use App\Enum\GameStatus;
use App\Enum\RuleType;
use App\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\Table(name: 'game')]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'games')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Event $event = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $position = 0;

    #[ORM\Column(type: Types::STRING, enumType: RuleType::class, length: 20)]
    private RuleType $rule = RuleType::QUINE;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $prize = '';

    #[ORM\Column(type: Types::STRING, enumType: GameStatus::class, length: 20)]
    private GameStatus $status = GameStatus::PENDING;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isFrozen = false;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $freezeOrderIndex = null;

    /** @var Collection<int, Draw> */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Draw::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['orderIndex' => 'ASC'])]
    private Collection $draws;

    /** @var Collection<int, Winner> */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Winner::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $winners;

    public function __construct()
    {
        $this->draws = new ArrayCollection();
        $this->winners = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getRule(): RuleType
    {
        return $this->rule;
    }

    public function setRule(RuleType $rule): self
    {
        $this->rule = $rule;

        return $this;
    }

    public function getPrize(): string
    {
        return $this->prize;
    }

    public function setPrize(string $prize): self
    {
        $this->prize = $prize;

        return $this;
    }

    public function getStatus(): GameStatus
    {
        return $this->status;
    }

    public function setStatus(GameStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    /** @return Collection<int, Draw> */
    public function getDraws(): Collection
    {
        return $this->draws;
    }

    public function addDraw(Draw $draw): self
    {
        if (!$this->draws->contains($draw)) {
            $this->draws->add($draw);
            $draw->setGame($this);
        }

        return $this;
    }

    public function removeDraw(Draw $draw): self
    {
        if ($this->draws->removeElement($draw)) {
            if ($draw->getGame() === $this) {
                $draw->setGame(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, Winner> */
    public function getWinners(): Collection
    {
        return $this->winners;
    }

    public function addWinner(Winner $winner): self
    {
        if (!$this->winners->contains($winner)) {
            $this->winners->add($winner);
            $winner->setGame($this);
        }

        return $this;
    }

    public function removeWinner(Winner $winner): self
    {
        if ($this->winners->removeElement($winner)) {
            if ($winner->getGame() === $this) {
                $winner->setGame(null);
            }
        }

        return $this;
    }

    public function isFrozen(): bool
    {
        return $this->isFrozen;
    }

    public function setIsFrozen(bool $isFrozen): self
    {
        $this->isFrozen = $isFrozen;

        return $this;
    }

    public function getFreezeOrderIndex(): ?int
    {
        return $this->freezeOrderIndex;
    }

    public function setFreezeOrderIndex(?int $freezeOrderIndex): self
    {
        $this->freezeOrderIndex = $freezeOrderIndex;

        return $this;
    }

    public function freeze(int $orderIndex): self
    {
        $this->isFrozen = true;
        $this->freezeOrderIndex = $orderIndex;

        return $this;
    }

    public function unfreeze(): self
    {
        $this->isFrozen = false;
        $this->freezeOrderIndex = null;

        return $this;
    }
}
