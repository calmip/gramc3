<?php

/**
 * This file is part of GRAMC (Computing Ressource Granting Software)
 * GRAMC stands for : Gestion des Ressources et de leurs Attributions pour Mésocentre de Calcul
 *
 * GRAMC is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 *  GRAMC is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with GRAMC.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  authors : Miloslav Grundmann - C.N.R.S. - UMS 3667 - CALMIP
 *            Emmanuel Courcelle - C.N.R.S. - UMS 3667 - CALMIP
 *            Nicolas Renon - Université Paul Sabatier - CALMIP
 **/

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Individu;
use App\Entity\User;

/**
 * Sso
 *
 * @ORM\Table(name="clessh",
 *            uniqueConstraints={@ORM\UniqueConstraint(name="nom_individu", columns={"id_individu", "nom"}),
 *                               @ORM\UniqueConstraint(name="pubuniq", columns={"emp"})})
 * @ORM\Entity
 */
class Clessh
{
    public function __construct()
    {
        $this->user = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getNom();
    }

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * 
     * @var \App\Entity\Individu
     *
     * ORM\Column(name="id_individu", type="integer")
     * @ORM\ManyToOne(targetEntity="Individu",inversedBy="clessh")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_individu", referencedColumnName="id_individu")
     * })
     * 
     */
    private $individu;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="User", mappedBy="clessh")
     */
    private $user;

    /**
     *
     * @ORM\Column(name="nom", type="string", length=20)
     * @var string
     *
     */
    private $nom;
    
    /**
     * 
     * @var string
     *
     * @ORM\Column(name="pub", type="string", length=5000)
     *
     */
    private $pub;
    
    /**
     * 
     * @var string
     *
     * @ORM\Column(name="emp", type="string", length=100, nullable=false)
     *
     * L'empreinte de cette clé ssh
     * 
     */
    private $emp;
    
    /**
     * @var boolean
     *
     * @ORM\Column(name="rvk", type="boolean")
     */
    private $rvk = false;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set individu
     *
     * @param Individu $individu
     *
     * @return Clessh
     */
    public function setIndividu(?Individu $individu = null): self
    {
        $this->individu = $individu;

        return $this;
    }

    /**
     * Get individu
     *
     * @return \App\Entity\Individu
     */
    public function getIndividu(): ?Individu
    {
        return $this->individu;
    }

    /**
     * Add user
     *
     * @param \App\Entity\User $user
     *
     * @return clessh
     */
    public function adduser(\App\Entity\User $user): self
    {
        if ( ! $this->user->contains($user))
        {
            $this->user[] = $user;
        }
        return $this;
    }

    /**
     * Remove user
     *
     * @param User $user
     *
     * @return clessh
     */
    public function removeUser(\App\Entity\User $user): self
    {
        $this->user->removeElement($user);
        return $this;
    }

    /**
     * Get user
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set nom
     *
     * @param string
     *
     * @return Clessh
     */
    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    /**
     * Get nom
     *
     * @return string
     */
    public function getNom(): ?string
    {
        return $this->nom;
    }

    /**
     * Set pub
     *
     * @param string
     *
     * @return Clessh
     */
    public function setPub(string $pub): self
    {
        $this->pub = $pub;
        return $this;
    }

    /**
     * Get pub
     *
     * @return string
     */
    public function getPub(): ?string
    {
        return $this->pub;
    }

    /**
     * Set emp
     *
     * @param string
     *
     * @return Clessh
     */
    public function setEmp(string $emp): self
    {
        $this->emp = $emp;
        return $this;
    }

    /**
     * Get emp
     *
     * @return string
     */
    public function getEmp(): ?string
    {
        return $this->emp;
    }

    /**
     * Set rvk
     *
     * @param boolean $rvk
     *
     * @return Version
     */
    public function setRvk(bool $rvk): self
    {
        $this->rvk = $rvk;

        return $this;
    }

    /**
     * Get rvk
     *
     * @return boolean
     */
    public function getRvk():bool 
    {
        return $this->rvk;
    }
}
