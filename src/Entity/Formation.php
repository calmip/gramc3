<?php

namespace App\Entity;

use App\Repository\FormationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=FormationRepository::class)
 */
class Formation
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $numeroForm;

    /**
     * @ORM\Column(type="string", length=15, nullable=true)
     */
    private $acroForm;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $nomForm;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumeroForm(): ?int
    {
        return $this->numeroForm;
    }

    public function setNumeroForm(?int $numeroForm): self
    {
        $this->numeroForm = $numeroForm;

        return $this;
    }

    public function getAcroForm(): ?string
    {
        return $this->acroForm;
    }

    public function setAcroForm(?string $acroForm): self
    {
        $this->acroForm = $acroForm;

        return $this;
    }

    public function getNomForm(): ?string
    {
        return $this->nomForm;
    }

    public function setNomForm(?string $nomForm): self
    {
        $this->nomForm = $nomForm;

        return $this;
    }
}
