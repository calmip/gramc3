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
 * Etablissement
 *
 * @ORM\Table(name="etablissement")
 * @ORM\Entity
 */
class Etablissement
{
    /**
     * @var string
     *
     * @ORM\Column(name="libelle_etab", type="string", length=50, nullable=false)
     */
    private $libelleEtab;

    /**
     * @var integer
     *
     * @ORM\Column(name="id_etab", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $idEtab;

    ////////////////////////////////////////////////////////////

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="\App\Entity\CollaborateurVersion", mappedBy="etab")
     */
    private $collaborateurVersion;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="\App\Entity\Individu", mappedBy="etab")
     */
    private $individu;

    public function __toString()
    {
        return $this->getLibelleEtab();
    }
    public function getId()
    {
        return $this->getIdEtab();
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->collaborateurVersion = new \Doctrine\Common\Collections\ArrayCollection();
        $this->individu = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set libelleEtab
     *
     * @param string $libelleEtab
     *
     * @return Etablissement
     */
    public function setLibelleEtab($libelleEtab)
    {
        $this->libelleEtab = $libelleEtab;

        return $this;
    }

    /**
     * Get libelleEtab
     *
     * @return string
     */
    public function getLibelleEtab()
    {
        return $this->libelleEtab;
    }

    /**
     * Get idEtab
     *
     * @return integer
     */
    public function getIdEtab()
    {
        return $this->idEtab;
    }

    /**
     * Add collaborateurVersion
     *
     * @param \App\Entity\CollaborateurVersion $collaborateurVersion
     *
     * @return Etablissement
     */
    public function addCollaborateurVersion(\App\Entity\CollaborateurVersion $collaborateurVersion)
    {
        $this->collaborateurVersion[] = $collaborateurVersion;

        return $this;
    }

    /**
     * Remove collaborateurVersion
     *
     * @param \App\Entity\CollaborateurVersion $collaborateurVersion
     */
    public function removeCollaborateurVersion(\App\Entity\CollaborateurVersion $collaborateurVersion)
    {
        $this->collaborateurVersion->removeElement($collaborateurVersion);
    }

    /**
     * Get collaborateurVersion
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCollaborateurVersion()
    {
        return $this->collaborateurVersion;
    }

    /**
     * Add individu
     *
     * @param \App\Entity\Individu $individu
     *
     * @return Etablissement
     */
    public function addIndividu(\App\Entity\Individu $individu)
    {
        $this->individu[] = $individu;

        return $this;
    }

    /**
     * Remove individu
     *
     * @param \App\Entity\Individu $individu
     */
    public function removeIndividu(\App\Entity\Individu $individu)
    {
        $this->individu->removeElement($individu);
    }

    /**
     * Get individu
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getIndividu()
    {
        return $this->individu;
    }
}
