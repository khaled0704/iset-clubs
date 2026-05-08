<?php

namespace App\Entity;

use App\Repository\ClubRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClubRepository::class)]
class Club
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $domain = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $wbsite = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    /**
     * @var Collection<int, ClubMember>
     */
    #[ORM\OneToMany(targetEntity: ClubMember::class, mappedBy: 'club')]
    private Collection $clubMembers;

    /**
     * @var Collection<int, Evenement>
     */
    #[ORM\OneToMany(targetEntity: Evenement::class, mappedBy: 'club')]
    private Collection $evenements;

    /**
     * @var Collection<int, Recrutement>
     */
    #[ORM\OneToMany(targetEntity: Recrutement::class, mappedBy: 'club')]
    private Collection $recrutements;

    public function __construct()
    {
        $this->clubMembers = new ArrayCollection();
        $this->evenements = new ArrayCollection();
        $this->recrutements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(?string $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getWbsite(): ?string
    {
        return $this->wbsite;
    }

    public function setWbsite(?string $wbsite): static
    {
        $this->wbsite = $wbsite;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, ClubMember>
     */
    public function getClubMembers(): Collection
    {
        return $this->clubMembers;
    }

    public function addClubMember(ClubMember $clubMember): static
    {
        if (!$this->clubMembers->contains($clubMember)) {
            $this->clubMembers->add($clubMember);
            $clubMember->setClub($this);
        }

        return $this;
    }

    public function removeClubMember(ClubMember $clubMember): static
    {
        if ($this->clubMembers->removeElement($clubMember)) {
            // set the owning side to null (unless already changed)
            if ($clubMember->getClub() === $this) {
                $clubMember->setClub(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Evenement>
     */
    public function getEvenements(): Collection
    {
        return $this->evenements;
    }

    public function addEvenement(Evenement $evenement): static
    {
        if (!$this->evenements->contains($evenement)) {
            $this->evenements->add($evenement);
            $evenement->setClub($this);
        }

        return $this;
    }

    public function removeEvenement(Evenement $evenement): static
    {
        if ($this->evenements->removeElement($evenement)) {
            // set the owning side to null (unless already changed)
            if ($evenement->getClub() === $this) {
                $evenement->setClub(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Recrutement>
     */
    public function getRecrutements(): Collection
    {
        return $this->recrutements;
    }

    public function addRecrutement(Recrutement $recrutement): static
    {
        if (!$this->recrutements->contains($recrutement)) {
            $this->recrutements->add($recrutement);
            $recrutement->setClub($this);
        }

        return $this;
    }

    public function removeRecrutement(Recrutement $recrutement): static
    {
        if ($this->recrutements->removeElement($recrutement)) {
            // set the owning side to null (unless already changed)
            if ($recrutement->getClub() === $this) {
                $recrutement->setClub(null);
            }
        }

        return $this;
    }
}
