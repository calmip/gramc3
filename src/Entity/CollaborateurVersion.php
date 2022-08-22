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
 * CollaborateurVersion
 *
 * @ORM\Table(name="collaborateurVersion",
 *            uniqueConstraints={@ORM\UniqueConstraint(name="id_version_2", columns={"id_version", "id_collaborateur"})},
 *            indexes={@ORM\Index(name="id_coll_labo", columns={"id_coll_labo"}),
 *                     @ORM\Index(name="id_coll_statut", columns={"id_coll_statut"}),
 *                     @ORM\Index(name="id_coll_etab", columns={"id_coll_etab"}),
 *                     @ORM\Index(name="collaborateur_collaborateurprojet_fk", columns={"id_collaborateur"}),
 *                     @ORM\Index(name="id_version", columns={"id_version"})})
 * @ORM\Entity(repositoryClass="App\Repository\CollaborateurVersionRepository")
 */
class CollaborateurVersion
{
    /**
     * @var boolean
     *
     * @ORM\Column(name="responsable", type="boolean", nullable=false)
     */
    private $responsable;

    /**
     * @var boolean
     *
     * @ORM\Column(name="deleted", type="boolean", nullable=false)
     */
    private $deleted = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="login", type="boolean", nullable=true)
     */
    private $login = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="clogin", type="boolean", nullable=true)
     */
    private $clogin = false;

    /**
     * @var string
     *
     * @ORM\Column(name="loginname", type="string", nullable=true,length=100 )
     */
    private $loginname;

    /**
     * @var \App\Entity\Projet
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_coll_statut", referencedColumnName="id_statut")
     * })
     */
    private $statut;

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var \App\Entity\Version
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Version")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_version", referencedColumnName="id_version", onDelete="CASCADE")
     * })
     */
    private $version;

    /**
     * @var \App\Entity\Laboratoire
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Laboratoire")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_coll_labo", referencedColumnName="id_labo")
     * })
     */
    private $labo;

    /**
     * @var \App\Entity\Etablissement
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Etablissement")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_coll_etab", referencedColumnName="id_etab")
     * })
     */
    private $etab;

    /**
     * @var \App\Entity\Individu
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Individu")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_collaborateur", referencedColumnName="id_individu")
     * })
     */
    private $collaborateur;

    public function __toString()
    {
        $output = '{';
        if ($this->getResponsable() == true) {
            $output .= 'responsable:';
        }
        if ($this->getLogin() == true) {
            $output .= 'login:';
        }
        $output .= 'version=' . $this->getVersion() .':';
        $output .= 'id=' . $this->getId() . ':';
        $output .= 'statut=' .$this->getStatut() .':';
        $output .= 'labo=' . $this->getLabo() .':';
        $output .= 'etab=' .$this->getEtab() .':';
        $output .= 'collab=' .$this->getCollaborateur() .'}';
        return $output;
    }

    public function __construct(Individu $individu = null, Version $version = null)
    {
        $this->login        = false;
        $this->clogin       = false;
        $this->responsable  = false;

        if ($individu != null) {
            $this->statut           =   $individu->getStatut();
            $this->labo             =   $individu->getLabo();
            $this->etab             =   $individu->getEtab();
            $this->collaborateur    =   $individu;
        }

        if ($version != null) {
            $this->version  =   $version;
        }
    }

    /**
     * Set responsable
     *
     * @param boolean $responsable
     *
     * @return CollaborateurVersion
     */
    public function setResponsable($responsable)
    {
        $this->responsable = $responsable;

        return $this;
    }

    /**
     * Get responsable
     *
     * @return boolean
     */
    public function getResponsable()
    {
        return $this->responsable;
    }

    /**
     * Set deleted
     *
     * @param boolean $deleted
     *
     * @return CollaborateurVersion
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * Get deleted
     *
     * @return boolean
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

    /**
     * Set login
     *
     * @param boolean $login
     *
     * @return CollaborateurVersion
     */
    public function setLogin($login)
    {
        $this->login = $login;

        return $this;
    }

    /**
     * Get login
     *
     * @return boolean
     */
    public function getLogin()
    {
        return $this->login;
    }

    /**
     * Set clogin
     *
     * @param boolean $clogin
     *
     * @return CollaborateurVersion
     */
    public function setClogin($clogin)
    {
        $this->clogin = $clogin;

        return $this;
    }

    /**
     * Get clogin
     *
     * @return boolean
     */
    public function getClogin()
    {
        return $this->clogin;
    }

    /**
     * Set loginname
     *
     * @param string $loginname
     *
     * @return CollaborateurVersion
     */
    public function setLoginname($loginname)
    {
        $this->loginname = $loginname;

        return $this;
    }

    /**
     * Get loginname
     *
     * @return string
     */
    public function getLoginname()
    {
        return $this->loginname;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set statut
     *
     * @param \App\Entity\Statut $statut
     *
     * @return CollaborateurVersion
     */
    public function setStatut(\App\Entity\Statut $statut = null)
    {
        $this->statut = $statut;

        return $this;
    }

    /**
     * Get statut
     *
     * @return \App\Entity\Statut
     */
    public function getStatut()
    {
        return $this->statut;
    }

    /**
     * Set version
     *
     * @param \App\Entity\Version $version
     *
     * @return CollaborateurVersion
     */
    public function setVersion(\App\Entity\Version $version = null)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Get version
     *
     * @return \App\Entity\Version
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Set labo
     *
     * @param \App\Entity\Laboratoire $labo
     *
     * @return CollaborateurVersion
     */
    public function setLabo(\App\Entity\Laboratoire $labo = null)
    {
        $this->labo = $labo;

        return $this;
    }

    /**
     * Get labo
     *
     * @return \App\Entity\Laboratoire
     */
    public function getLabo()
    {
        return $this->labo;
    }

    /**
     * Set etab
     *
     * @param \App\Entity\Etablissement $etab
     *
     * @return CollaborateurVersion
     */
    public function setEtab(\App\Entity\Etablissement $etab = null)
    {
        $this->etab = $etab;

        return $this;
    }

    /**
     * Get etab
     *
     * @return \App\Entity\Etablissement
     */
    public function getEtab()
    {
        return $this->etab;
    }

    /**
     * Set collaborateur
     *
     * @param \App\Entity\Individu $collaborateur
     *
     * @return CollaborateurVersion
     */
    public function setCollaborateur(\App\Entity\Individu $collaborateur = null)
    {
        $this->collaborateur = $collaborateur;

        return $this;
    }

    /**
     * Get collaborateur
     *
     * @return \App\Entity\Individu
     */
    public function getCollaborateur()
    {
        return $this->collaborateur;
    }
}
