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

/**
 * Rattachement
 *
 * @ORM\Table(name="rattachement")
 * @ORM\Entity
 */
class Rattachement
{
    /**
     * @var string
     *
     * @ORM\Column(name="libelle_rattachement", type="string", length=200, nullable=false)
     */
    private $libelleRattachement;

    /**
     * @var integer
     *
     * @ORM\Column(name="id_rattachement", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $idRattachement;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="App\Entity\Individu", inversedBy="rattachement")
     * @ORM\JoinTable(name="rattachementExpert",
     *   joinColumns={
     *     @ORM\JoinColumn(name="id_rattachement", referencedColumnName="id_rattachement")
     *   },
     *   inverseJoinColumns={
     *     @ORM\JoinColumn(name="id_expert", referencedColumnName="id_individu")
     *   }
     * )
     */
    private $expert;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="\App\Entity\Version", mappedBy="prjRattachement")
     */
    private $version;


    //////////////////////////////////////////////////////////

    public function getId()
    {
        return $this->getIdRattachement();
    }
    public function __toString()
    {
        return $this->getLibelleRattachement();
    }

    //////////////////////////////////////////////////////////


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->expert = new \Doctrine\Common\Collections\ArrayCollection();
        $this->version = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set libelleRattachement
     *
     * @param string $libelleRattachement
     *
     * @return Rattachement
     */
    public function setLibelleRattachement($libelleRattachement)
    {
        $this->libelleRattachement = $libelleRattachement;

        return $this;
    }

    /**
     * Get libelleRattachement
     *
     * @return string
     */
    public function getLibelleRattachement()
    {
        return $this->libelleRattachement;
    }

    /**
     * Get idRattachement
     *
     * @return integer
     */
    public function getIdRattachement()
    {
        return $this->idRattachement;
    }

    /**
     * Add expert
     *
     * @param \App\Entity\Individu $expert
     *
     * @return Rattachement
     */
    public function addExpert(\App\Entity\Individu $expert)
    {
        if (! $this->expert->contains($expert)) {
            $this->expert[] = $expert;
        }

        return $this;
    }

    /**
     * Remove expert
     *
     * @param \App\Entity\Individu $expert
     */
    public function removeExpert(\App\Entity\Individu $expert)
    {
        $this->expert->removeElement($expert);
    }

    /**
     * Get expert
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getExpert()
    {
        return $this->expert;
    }

    /**
     * Add version
     *
     * @param \App\Entity\Version $version
     *
     * @return Rattachement
     */
    public function addVersion(\App\Entity\Version $version)
    {
        $this->version[] = $version;

        return $this;
    }

    /**
     * Remove version
     *
     * @param \App\Entity\Version $version
     */
    public function removeVersion(\App\Entity\Version $version)
    {
        $this->version->removeElement($version);
    }

    /**
     * Get version
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getVersion()
    {
        return $this->version;
    }
}
