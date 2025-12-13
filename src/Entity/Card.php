<?php
namespace App\Entity;

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

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Event $event = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $reference = '';

    // Store as JSON: array of 3 arrays, each with 5 integers
    #[ORM\Column(type: Types::JSON)]
    private array $grid = [];

    #[ORM\ManyToOne(targetEntity: Player::class)]
    private ?Player $player = null;

    public function getId(): ?int { return $this->id; }

    public function getEvent(): ?Event { return $this->event; }
    public function setEvent(?Event $event): self { $this->event = $event; return $this; }

    public function getReference(): string { return $this->reference; }
    public function setReference(string $reference): self { $this->reference = $reference; return $this; }

    public function getGrid(): array { return $this->grid; }
    public function setGrid(array $grid): self { $this->grid = $grid; return $this; }

    public function getPlayer(): ?Player { return $this->player; }
    public function setPlayer(?Player $player): self { $this->player = $player; return $this; }
}
