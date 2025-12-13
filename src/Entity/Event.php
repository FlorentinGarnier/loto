<?php
namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $date;

    /** @var Collection<int, Game> */
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Game::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $games;

    public function __construct(string $name = '', ?\DateTimeImmutable $date = null)
    {
        $this->name = $name;
        $this->date = $date ?? new \DateTimeImmutable();
        $this->games = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function setDate(\DateTimeImmutable $date): self { $this->date = $date; return $this; }

    /** @return Collection<int, Game> */
    public function getGames(): Collection { return $this->games; }
    public function addGame(Game $game): self {
        if (!$this->games->contains($game)) {
            $this->games->add($game);
            $game->setEvent($this);
        }
        return $this;
    }
    public function removeGame(Game $game): self {
        if ($this->games->removeElement($game)) {
            if ($game->getEvent() === $this) { $game->setEvent(null); }
        }
        return $this;
    }
}
