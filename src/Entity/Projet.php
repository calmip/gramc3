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

//use App\App;
use App\GramcServices\Etat;
use App\Utils\Functions;
use App\Entity\Version;
use App\Entity\Expertise;
use App\Entity\CollaborateurVersion;
use App\Utils\GramcDate;

use App\Form\ChoiceList\ExpertChoiceLoader;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * Projet
 *
 * @ORM\Table(name="projet", indexes={@ORM\Index(name="etat_projet", columns={"etat_projet"})})
 * @ORM\Entity(repositoryClass="App\Repository\ProjetRepository")
 */
class Projet
{
    public const PROJET_SESS = 1;		// Projet créé lors d'une session d'attribution
    public const PROJET_TEST = 2;		// Projet test, créé au fil de l'eau, non renouvelable
    public const PROJET_FIL  = 3;		// Projet créé au fil de l'eau, renouvelable lors des sessions

    public const LIBELLE_TYPE=
    [
        self::PROJET_SESS => 'S',
        self::PROJET_TEST =>  'T',
        self::PROJET_FIL =>  'F',
    ];


    /**
     * @var integer
     *
     * @ORM\Column(name="etat_projet", type="integer", nullable=false)
     */
    private $etatProjet;


    /**
     * @var integer
     *
     * @ORM\Column(name="type_projet", type="integer", nullable=false)
     */
    private $typeProjet;

    /**
     * @var string
     *
     * @ORM\Column(name="id_projet", type="string", length=10)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $idProjet;

    /**
     * @var \App\Entity\Version
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Version")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_veract", referencedColumnName="id_version", onDelete="SET NULL", nullable=true)
     * })
     *
     * la version active actuelle ou la dernière version active si aucune n'est active actuellement
     *
     */
    private $versionActive;

    /**
     * @var \App\Entity\Version
     *
     * @ORM\OneToOne(targetEntity="App\Entity\Version")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_verder", referencedColumnName="id_version", onDelete="SET NULL", nullable=true )
     * })
     *
     *  la version qui correspond  à la dernière session
     *  cette clé est fixée au moment de la création de la version
     *  si la session d'une version change après sa création il faut le modifier manuellement
     */
    private $versionDerniere;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="App\Entity\Publication", inversedBy="projet")
     * @ORM\JoinTable(name="publicationProjet",
     *   joinColumns={
     *     @ORM\JoinColumn(name="id_projet", referencedColumnName="id_projet")
     *   },
     *   inverseJoinColumns={
     *     @ORM\JoinColumn(name="id_publi", referencedColumnName="id_publi")
     *   }
     * )
     */
    private $publi;

    ////////////////////////////////////////////////////////////////////////////////

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="\App\Entity\Version", mappedBy="projet")
     */
    private $version;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="\App\Entity\RapportActivite", mappedBy="projet")
     */
    private $rapportActivite;

    /**
     * @var boolean
     *
     * @ORM\Column(name="nepasterminer", type="boolean", nullable=true)
     */
    private $nepasterminer;

    public function getId()
    {
        return $this->getIdProjet();
    }
    public function __toString()
    {
        return $this->getIdProjet();
    }

    /**
     * Constructor
     */
    public function __construct($type)
    {
        $this->publi        = new \Doctrine\Common\Collections\ArrayCollection();
        $this->version      = new \Doctrine\Common\Collections\ArrayCollection();
        $this->rapportActivite = new \Doctrine\Common\Collections\ArrayCollection();
        $this->etatProjet   = Etat::EDITION_DEMANDE;
        $this->typeProjet   = $type;
    }

    /**
     * Set etatProjet
     *
     * @param integer $etatProjet
     *
     * @return Projet
     */
    public function setEtatProjet($etatProjet)
    {
        $this->etatProjet = $etatProjet;

        return $this;
    }

    /**
     * Set typeProjet
     *
     * @param integer $typeProjet
     *
     * @return Projet
     */
    public function setTypeProjet($typeProjet)
    {
        $this->typeProjet = $typeProjet;

        return $this;
    }

    /**
     * Get etatProjet
     *
     * @return integer
     */
    public function getEtatProjet()
    {
        return $this->etatProjet;
    }

    /**
     * Get typeProjet
     *
     * @return integer
     */
    public function getTypeProjet()
    {
        return $this->typeProjet;
    }

    /**
     * Set idProjet
     *
     * @param string $idProjet
     *
     * @return Projet
     */
    public function setIdProjet($idProjet)
    {
        $this->idProjet = $idProjet;

        return $this;
    }

    /**
     * Get idProjet
     *
     * @return string
     */
    public function getIdProjet()
    {
        return $this->idProjet;
    }

    /**
     * Set versionActive
     *
     * @param \App\Entity\Version $version
     *
     * @return Projet
     */
    public function setVersionActive(\App\Entity\Version $version = null)
    {
        $this->versionActive = $version;

        return $this;
    }

    /**
     * Get versionActive
     *
     * @return \App\Entity\Version
     */
    public function getVersionActive()
    {
        return $this->versionActive;
    }

    /**
     * Set versionDerniere
     *
     * @param \App\Entity\Version $version
     *
     * @return Projet
     */
    public function setVersionDerniere(Version $version = null)
    {
        $this->versionDerniere = $version;

        return $this;
    }

    /**
     * Get versionDerniere
     *
     * @return \App\Entity\Version
     */
    public function getVersionDerniere()
    {
        return $this->versionDerniere;
    }

    /**
     * Set nepasterminer
     *
     * @param boolean $nepasterminer
     *
     * @return Projet
     */
    public function setNepasterminer($nepasterminer)
    {
        $this->nepasterminer = $nepasterminer;

        return $this;
    }

    /**
     * Get nepasterminer
     *
     * @return boolean
     */
    public function getNepasterminer()
    {
        return $this->nepasterminer;
    }

    /**
     * Add publi
     *
     * @param \App\Entity\Publication $publi
     *
     * @return Projet
     */
    public function addPubli(\App\Entity\Publication $publi)
    {
        if (! $this->publi->contains($publi)) {
            $this->publi[] = $publi;
        }

        return $this;
    }

    /**
     * Remove publi
     *
     * @param \App\Entity\Publication $publi
     */
    public function removePubli(\App\Entity\Publication $publi)
    {
        $this->publi->removeElement($publi);
    }

    /**
     * Get publi
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPubli()
    {
        return $this->publi;
    }

    /**
     * Add version
     *
     * @param \App\Entity\Version $version
     *
     * @return Projet
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

    /**
     * Add rapportActivite
     *
     * @param \App\Entity\RapportActivite $rapportActivite
     *
     * @return Projet
     */
    public function addRapportActivite(\App\Entity\RapportActivite $rapportActivite)
    {
        $this->rapportActivite[] = $rapportActivite;

        return $this;
    }

    /**
     * Remove rapportActivite
     *
     * @param \App\Entity\RapportActivite $rapportActivite
     */
    public function removeRapportActivite(\App\Entity\RapportActivite $rapportActivite)
    {
        $this->rapportActivite->removeElement($rapportActivite);
    }

    /**
     * Get rapportActivite
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRapportActivite()
    {
        return $this->rapportActivite;
    }

    /***************************************************
     * Fonctions utiles pour la class Workflow
     * Autre nom pour getEtatProjet/setEtatProjet !
     ***************************************************/
    public function getObjectState()
    {
        return $this->getEtatProjet();
    }
    public function setObjectState($state)
    {
        $this->setEtatProjet($state);
        return $this;
    }

    //public function getSubWorkflow()    { return new \App\Workflow\VersionWorkflow(); }

    //public function getSubObjects()
    //{
    //$versions = $this->getVersion();
    //$my_versions = new \Doctrine\Common\Collections\ArrayCollection();

    //foreach( $versions as $version )
    //{
    //$etat   =   $version->getEtatVersion();
    //if( $etat != Etat::TERMINE && $etat != Etat::ANNULE )
    //$my_versions[]  = $version;
    //}
    //return $my_versions;
    //}

    ////////////////////////////////////////

    // pour twig

    public function getLibelleEtat()
    {
        return Etat::getLibelle($this->getEtatProjet());
    }

    public function getTitre()
    {
        if ($this->derniereVersion() != null) {
            return $this->derniereVersion()->getPrjTitre();
        } else {
            return null;
        }
    }

    public function getThematique()
    {
        if ($this->derniereVersion() != null) {
            return $this->derniereVersion()->getPrjThematique();
        } else {
            return null;
        }
    }

    public function getRattachement()
    {
        if ($this->derniereVersion() != null) {
            return $this->derniereVersion()->getPrjRattachement();
        } else {
            return null;
        }
    }

    public function getLaboratoire()
    {
        if ($this->derniereVersion() != null) {
            return $this->derniereVersion()->getPrjLLabo();
        } else {
            return null;
        }
    }

    //public function countVersions()
    //{
    //return App::getRepository(Version::class)->countVersions($this);
    //}

    public function derniereSession()
    {
        if ($this->derniereVersion() != null) {
            return $this->derniereVersion()->getSession();
        } else {
            return null;
        }
    }

    public function getResponsable()
    {
        if ($this->derniereVersion() != null) {
            return $this->derniereVersion()->getResponsable();
        } else {
            return null;
        }
    }

    /*
     * Renvoie true si le projet est un projet test, false sinon
     *
     */
    public function isProjetTest()
    {
        $type = $this->getTypeProjet();
        if ($this->getTypeProjet() === Projet::PROJET_TEST) {
            return true;
        } else {
            return false;
        }
    }

    /**
    * derniereVersion - Alias de getVersionDerniere()
    *                   TODO - A supprimer !
    *
    * @return \App\Entity\Version
    */
    public function derniereVersion()
    {
        return $this->getVersionDerniere();
    }

    /****************
     * Retourne true si $individu collabore à au moins une version du projet
     ******************************************/
    public function isCollaborateur(Individu $individu)
    {
        foreach ($this->getVersion() as $version) {
            if ($version->isCollaborateur($individu) == true) {
                return true;
            }
        }
        return false;
    }

    ////////////////////////////////////////////////////

    /* Supprimé car non utilisé
        //public function getCollaborateurs( $versions = [] )
        //{
            //if( $versions == [] ) $versions = App::getRepository(Version::class)->findVersions( $this );

            //$collaborateurs = [];
            //foreach( $versions as $version )
                //foreach( $version->getCollaborateurs() as $collaborateur )
                    //$collaborateurs[ $collaborateur->getIdIndividu() ] = $collaborateur;

            //return $collaborateurs;
        //}
    */
    /////////////////////////////////////////////////////


    public function getEtat()
    {
        return $this->getEtatProjet();
    }

    public function getLibelleType()
    {
        $type = $this->getTypeProjet();
        if ($type <=3 and $type > 0) {
            return Projet::LIBELLE_TYPE[$this->getTypeProjet()];
        } else {
            return '?';
        }
    }
}
