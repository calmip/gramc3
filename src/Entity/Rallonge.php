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

use App\GramcServices\Etat;
use App\Utils\Functions;
use App\Interfaces\Demande;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Rallonge
 *
 * @ORM\Table(name="rallonge", indexes={@ORM\Index(name="id_version", columns={"id_version"}), @ORM\Index(name="num_rallonge", columns={"id_rallonge"}), @ORM\Index(name="etat_rallonge", columns={"etat_rallonge"})})
 * @ORM\Entity(repositoryClass="App\Repository\RallongeRepository")
 * @Assert\Expression("this.getNbHeuresAtt() > 0  or  this.getValidation() != 1",
 *  message="Si vous ne voulez pas attribuer des heures pour cette demande, choisissez ""Refuser""",groups={"expertise"})
 * @Assert\Expression("this.getNbHeuresAtt() == 0  or  this.getValidation() !=  0",
 *  message="Si vous voulez attribuer des heures pour cette demande, choisissez ""Accepter""",groups={"expertise"})
 * @ORM\HasLifecycleCallbacks()
 */
class Rallonge implements Demande
{
    /**
     * @var integer
     *
     * @ORM\Column(name="etat_rallonge", type="integer", nullable=false)
     */
    private $etatRallonge;

    /**
     * @var integer
     *
     * @ORM\Column(name="dem_heures", type="integer", nullable=true)
     * @Assert\GreaterThan(0,message="Vous devez demander des heures.")
     * @Assert\GreaterThanOrEqual(0,message="Vous ne pouvez pas demander un nombre d'heures négatif.")
     */
    private $demHeures;

    /**
     * @var integer
     *
     * @ORM\Column(name="dem_heures_gpu", type="integer", nullable=true)
     * @Assert\GreaterThan(0,message="Vous devez demander des heures.")
     * @Assert\GreaterThanOrEqual(0,message="Vous ne pouvez pas demander un nombre d'heures négatif.")
     */
    private $demHeuresGpu;

    /**
     * @var integer
     *
     * @ORM\Column(name="attr_heures", type="integer", nullable=true)
     */
    private $attrHeures;

    /**
     * @var string
     *
     * @ORM\Column(name="prj_justif_rallonge", type="text", length=65535, nullable=true)
     * @Assert\NotBlank(message="Vous n'avez pas rempli la justification scientifique")
     */
    private $prjJustifRallonge;

    /**
     * @var boolean
     *
     * @ORM\Column(name="attr_accept", type="boolean", nullable=false)
     */
    private $attrAccept = '1';

    /**
     * @var string
     *
     * @ORM\Column(name="id_rallonge", type="string", length=15)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $idRallonge;

    /**
     * @var \App\Entity\Version
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Version",inversedBy="rallonge")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_version", referencedColumnName="id_version")
     * })
     */
    private $version;

    ////////////////////////////////////////////////////////

    /**
     * @var integer
     *
     * @ORM\Column(name="nb_heures_att", type="integer", nullable=true)
     * @Assert\GreaterThanOrEqual(value = 0,message="Vous ne pouvez pas attribuer un nombre d'heures négatif.", groups={"expertise","president"})
     */
    private $nbHeuresAtt;

    /**
     * @var string
     *
     * @ORM\Column(name="commentaire_interne", type="text", length=65535, nullable=true)
     * @Assert\NotBlank(message="Vous n'avez pas rempli le commentaire interne", groups={"expertise","president"})
     */
    private $commentaireInterne;

    /**
     * @var string
     *
     * @ORM\Column(name="commentaire_externe", type="text", length=65535, nullable=true)
     * @Assert\NotBlank(message="Vous n'avez pas rempli le commentaire pour le responsable", groups={"president"})
     */
    private $commentaireExterne;

    /**
     * @var boolean
     *
     * @ORM\Column(name="validation", type="boolean", nullable=false)
     *
     */
    private $validation = true;

    /**
     * @var \App\Entity\Individu
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Individu")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_expert", referencedColumnName="id_individu")
     * })
     */
    private $expert;


    /////////

    /**
    * @ORM\PostLoad
    *
    * TODO - Ce truc est une bidouille immonde à supprimer ASAP
    */
    public function convert()
    {
        if ($this->getEtatRallonge() == Etat::ACTIF && $this->getAttrHeures() == null) {
            $this->setAttrHeures($this->getNbHeuresAtt());
            //Functions::infoMessage(__METHOD__ . ':' . __LINE__ . ' Fixture partielle de Rallonge ' . $this->getIdRallonge() );
        }
    }

    ////////////////////////////////////////////////////////

    /**
     * Constructor
     */
    public function __construct()
    {
        // $this->majStamp             =   new \DateTime("now");
    }



    /////////////////////////////////////////////////////////////////////////////


    public function getId()
    {
        return $this->getIdRallonge();
    }
    public function __toString()
    {
        return $this->getIdRallonge();
    }

    ////////////////////////////////////////////////////////////////////////////



    /**
     * Set etatRallonge
     *
     * @param integer $etatRallonge
     *
     * @return Rallonge
     */
    public function setEtatRallonge($etatRallonge)
    {
        $this->etatRallonge = $etatRallonge;

        return $this;
    }
    public function setEtat($etatRallonge)
    {
        return $this->setEtatRallonge($etatRallonge);
    }

    /**
     * Get etatRallonge
     *
     * @return integer
     */
    public function getEtatRallonge()
    {
        return $this->etatRallonge;
    }

    /**
     * Set demHeures
     *
     * @param integer $demHeures
     *
     * @return Rallonge
     */
    public function setDemHeures($demHeures)
    {
        $this->demHeures = $demHeures;

        return $this;
    }

    /**
     * Get demHeures
     *
     * @return integer
     */
    public function getDemHeures()
    {
        return $this->demHeures;
    }

    /**
     * Set demHeuresGpu
     *
     * @param integer $demHeuresGpu
     *
     * @return Rallonge
     */
    public function setDemHeuresGpu($demHeuresGpu)
    {
        $this->demHeuresGpu = $demHeuresGpu;

        return $this;
    }

    /**
     * Get demHeuresGpu
     *
     * @return integer
     */
    public function getDemHeuresGpu()
    {
        return $this->demHeuresGpu;
    }

    /**
     * Set attrHeures
     *
     * @param integer $attrHeures
     *
     * @return Rallonge
     */
    public function setAttrHeures($attrHeures)
    {
        $this->attrHeures = $attrHeures;

        return $this;
    }

    /**
     * Get attrHeures
     *
     * @return integer
     */
    public function getAttrHeures()
    {
        return $this->attrHeures;
    }

    /**
     * Set prjJustifRallonge
     *
     * @param string $prjJustifRallonge
     *
     * @return Rallonge
     */
    public function setPrjJustifRallonge($prjJustifRallonge)
    {
        $this->prjJustifRallonge = $prjJustifRallonge;

        return $this;
    }

    /**
     * Get prjJustifRallonge
     *
     * @return string
     */
    public function getPrjJustifRallonge()
    {
        return $this->prjJustifRallonge;
    }

    /**
     * Set attrAccept
     *
     * @param boolean $attrAccept
     *
     * @return Rallonge
     */
    public function setAttrAccept($attrAccept)
    {
        $this->attrAccept = $attrAccept;

        return $this;
    }

    /**
     * Get attrAccept
     *
     * @return boolean
     */
    public function getAttrAccept()
    {
        return $this->attrAccept;
    }

    /**
     * Set idRallonge
     *
     * @param string $idRallonge
     *
     * @return Rallonge
     */
    public function setIdRallonge($idRallonge)
    {
        $this->idRallonge = $idRallonge;

        return $this;
    }

    /**
     * Get idRallonge
     *
     * @return string
     */
    public function getIdRallonge()
    {
        return $this->idRallonge;
    }

    /**
     * Set version
     *
     * @param \App\Entity\Version $version
     *
     * @return Rallonge
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

    /////////////////////////////////////////////////////////////////////



    /**
     * Set nbHeuresAtt
     *
     * @param integer $nbHeuresAtt
     *
     * @return Rallonge
     */
    public function setNbHeuresAtt($nbHeuresAtt)
    {
        $this->nbHeuresAtt = $nbHeuresAtt;

        return $this;
    }

    /**
     * Get nbHeuresAtt
     *
     * @return integer
     */
    public function getNbHeuresAtt()
    {
        return $this->nbHeuresAtt;
    }

    /**
     * Set commentaireInterne
     *
     * @param string $commentaireInterne
     *
     * @return Rallonge
     */
    public function setCommentaireInterne($commentaireInterne)
    {
        $this->commentaireInterne = $commentaireInterne;

        return $this;
    }

    /**
     * Get commentaireInterne
     *
     * @return string
     */
    public function getCommentaireInterne()
    {
        return $this->commentaireInterne;
    }

    /**
     * Set commentaireExterne
     *
     * @param string $commentaireExterne
     *
     * @return Rallonge
     */
    public function setCommentaireExterne($commentaireExterne)
    {
        $this->commentaireExterne = $commentaireExterne;

        return $this;
    }

    /**
     * Get commentaireExterne
     *
     * @return string
     */
    public function getCommentaireExterne()
    {
        return $this->commentaireExterne;
    }

    /**
     * Set validation
     *
     * @param boolean $validation
     *
     * @return Rallonge
     */
    public function setValidation($validation)
    {
        $this->validation = $validation;

        return $this;
    }

    /**
     * Get validation
     *
     * @return boolean
     */
    public function getValidation()
    {
        return $this->validation;
    }

    /**
     * Set expert
     *
     * @param \App\Entity\Individu $expert
     *
     * @return Rallonge
     */
    public function setExpert(\App\Entity\Individu $expert = null)
    {
        $this->expert = $expert;

        return $this;
    }

    /**
     * Get expert
     *
     * @return \App\Entity\Individu
     */
    public function getExpert()
    {
        return $this->expert;
    }

    /**
     * @ORM\PrePersist()
     */
    // cf. https://stackoverflow.com/questions/39272733/boolean-values-and-choice-symfony-type
    public function prePersist()
    {
        $this->validation = (bool) $this->validation; //Force using boolean value of $this->active
    }

    /**
     * @ORM\PreUpdate()
     */
    public function preUpdate()
    {
        $this->validation = (bool) $this->validation;
    }    


    /***************************************************
     * Fonctions utiles pour la class Workflow
     * Autre nom pour getEtatRallonge/setEtatRallonge !
     ***************************************************/
    public function getObjectState()
    {
        return $this->getEtatRallonge();
    }
    public function setObjectState($state)
    {
        $this->setEtatRallonge($state);
        return $this;
    }

    public function getResponsables()
    {
        $version = $this->getVersion();
        if ($version != null) {
            return $version->getResponsables();
        } else {
            return [];
        }
    }

    // pour notifications
    public function getOneExpert()
    {
        $expert = $this->getExpert();
        if ($expert == null) {
            return null;
        } else {
            //return $expert[0];
            return $expert;
        }
    }

    // pour notifications
    public function getExperts()
    {
        return [ $this->getExpert() ];
    }

    // pour notifications
    public function getExpertsThematique()
    {
        $version    =   $this->getVersion();
        if ($version    ==  null) {
            return [];
        }

        $thematique = $version->getThematique();
        if ($thematique == null) {
            return [];
        } else {
            return $thematique->getExpert();
        }
    }

    //////////////////////////////
    // TODO - Mettre cette fonction dans ServiceRallonge
    public function getMetaEtat()
    {
        $etat = $this->getEtatRallonge();
        if ($etat    ==  Etat::EDITION_DEMANDE) {
            return  'EDITION';
        } elseif ($etat    ==  Etat::EDITION_EXPERTISE) {
            return  'EXPERTISE';
        } elseif ($etat    ==  Etat::DESAFFECTE) {
            return  'EXPERTISE';
        } elseif ($etat    ==  Etat::EN_ATTENTE) {
            return  'ATTENTE';
        } elseif ($etat == Etat::ANNULE ) {
            return 'TERMINE';
        } elseif ($this->getAttrAccept() == true) {
            return  'ACCEPTE';
        } elseif ($this->getAttrAccept() == false) {
            return  'REFUSE';
        } else {
            return '';
        }
    }

    //////////////

    public function getLibelleEtatRallonge()
    {
        return Etat::getLibelle($this->getEtatRallonge());
    }

    ////////////////////////////////////////////////////////////////////////
    // TODO - Mettre cette fonction dans ServiceRallonge

    public function isExpertDe(Individu $individu)
    {
        if ($individu == null) {
            return false;
        }

        $expert = $this->getExpert();

        if ($expert == null) {
            //Functions::warningMessage(__METHOD__ . ":" . __LINE__ . " rallonge " . $this->__toString() . " n'a pas d'expert ");
            return false;
        } elseif ($expert->isEqualTo($individu)) {
            return true;
        } else {
            return false;
        }
    }

    ////////////////////////////////////////////////////////////////////////

    public function isFinalisable()
    {
        if ($this->getEtatRallonge() == Etat::EN_ATTENTE) {
            return true;
        } else {
            return false;
        }
    }

    ////////////////////////////////////////////////////////////////////////
    public function getEtat()
    {
        return $this->getEtatRallonge();
    }
}
