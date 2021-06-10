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
use App\Utils\Etat;
use App\Utils\Functions;
use App\Interfaces\Demande;

use App\Politique\Politique;

/**
 * Version
 *
 * @ORM\Table(name="version", indexes={@ORM\Index(name="etat_version", columns={"etat_version"}), @ORM\Index(name="id_session", columns={"id_session"}), @ORM\Index(name="id_projet", columns={"id_projet"}), @ORM\Index(name="prj_id_thematique", columns={"prj_id_thematique"})})
 * @ORM\Entity(repositoryClass="App\Repository\VersionRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Version implements Demande
{
    /**
     * @var integer
     *
     * @ORM\Column(name="etat_version", type="integer", nullable=true)
     */
    private $etatVersion = Etat::EDITION_DEMANDE;


    /**
     * @var string
     *
     * @ORM\Column(name="prj_l_labo", type="string", length=300, nullable=true)
     */
    private $prjLLabo = '';

    /**
     * @var string
     *
     * @ORM\Column(name="prj_titre", type="string", length=150, nullable=true)
     */
    private $prjTitre = '';

    /**
     * @var integer
     *
     * @ORM\Column(name="dem_heures", type="integer", nullable=true)
     */
    private $demHeures = '0';

    /**
     * @var integer
     *
     * @ORM\Column(name="attr_heures", type="integer", nullable=true)
     */
    private $attrHeures = '0';

    /**
     * @var integer
     *
     * @ORM\Column(name="politique", type="integer", nullable=true)
     */
    private $politique = Politique::DEFAULT_POLITIQUE;


    /**
     * @var string
     *
     * @ORM\Column(name="prj_sous_thematique", type="string", length=100, nullable=true)
     */
    private $prjSousThematique;

    /**
     * @var string
     *
     * @ORM\Column(name="prj_financement", type="string", length=100, nullable=true)
     */
    private $prjFinancement = '';

    /**
     * @var string
     *
     * @ORM\Column(name="prj_genci_machines", type="string", length=60, nullable=true)
     */
    private $prjGenciMachines = '';

    /**
     * @var string
     *
     * @ORM\Column(name="prj_genci_centre", type="string", length=60, nullable=true)
     */
    private $prjGenciCentre = '';

    /**
     * @var string
     *
     * @ORM\Column(name="prj_genci_heures", type="string", length=30, nullable=true)
     */
    private $prjGenciHeures = '';

    /**
     * @var string
     *
     * @ORM\Column(name="prj_resume", type="text", nullable=true)
     */
    private $prjResume = '';

    /**
     * @var string
     *
     * @ORM\Column(name="prj_expose", type="text", nullable=true)
     */
    private $prjExpose = '';

    /**
     * @var string
     *
     * @ORM\Column(name="prj_justif_renouv", type="text", nullable=true)
     */
    private $prjJustifRenouv;

    /**
     * @var string
     *
     * @ORM\Column(name="prj_algorithme", type="text", length=65535, nullable=true)
     */
    private $prjAlgorithme = '';

    /**
     * @var boolean
     *
     * @ORM\Column(name="prj_conception", type="boolean", nullable=true)
     */
    private $prjConception = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="prj_developpement", type="boolean", nullable=true)
     */
    private $prjDeveloppement = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="prj_parallelisation", type="boolean", nullable=true)
     */
    private $prjParallelisation = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="prj_utilisation", type="boolean", nullable=true)
     */
    private $prjUtilisation = false;

    /**
     * @var string
     *
     * @ORM\Column(name="prj_fiche", type="blob", length=65535, nullable=true)
     */
    private $prjFiche = '';

    /**
     * @var boolean
     *
     * @ORM\Column(name="prj_fiche_val", type="boolean", nullable=true)
     */
    private $prjFicheVal = false;

    /**
     * @var string
     *
     * @ORM\Column(name="prj_genci_dari",  type="string", length=15, nullable=true)
     */
    private $prjGenciDari = '';

    /**
     * @var string
     *
     * @ORM\Column(name="code_nom", type="string", length=150, nullable=true)
     */
    private $codeNom = '';

    /**
     * @var string
     *
     * @ORM\Column(name="code_langage", type="string", length=30, nullable=true)
     */
    private $codeLangage = '';

    /**
     * @var boolean
     *
     * @ORM\Column(name="code_c", type="boolean", nullable=true)
     */
    private $codeC = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="code_cpp", type="boolean", nullable=true)
     */
    private $codeCpp = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="code_for", type="boolean", nullable=true)
     */
    private $codeFor = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="code_autre", type="boolean", nullable=true)
     */
    private $codeAutre = false;

    /**
     * @var string
     *
     * @ORM\Column(name="code_licence", type="text", length=65535, nullable=true)
     */
    private $codeLicence = '';

    /**
     * @var string
     *
     * @ORM\Column(name="code_util_sur_mach", type="text", length=65535, nullable=true)
     */
    private $codeUtilSurMach = '';

    /**
     * @var string
     *
     * @ORM\Column(name="code_heures_p_job", type="string", length=15, nullable=true)
     */
    private $codeHeuresPJob = '';

    /**
     * @var string
     *
     * @ORM\Column(name="code_ram_p_coeur", type="string", length=15, nullable=true)
     */
    private $codeRamPCoeur = '';

    /**
     * @var string
     *
     * @ORM\Column(name="gpu", type="string", length=15, nullable=true)
     */
    private $gpu = '';

    /**
     * @var string
     *
     * @ORM\Column(name="code_ram_part", type="string", length=15, nullable=true)
     */
    private $codeRamPart = '';

    /**
     * @var string
     *
     * @ORM\Column(name="code_eff_paral", type="string", length=15, nullable=true)
     */
    private $codeEffParal = '';

    /**
     * @var string
     *
     * @ORM\Column(name="code_vol_donn_tmp", type="string", length=15, nullable=true)
     */
    private $codeVolDonnTmp = '';

    /**
     * @var string
     *
     * @ORM\Column(name="dem_logiciels", type="text", length=65535, nullable=true)
     */
    private $demLogiciels ='';

    /**
     * @var string
     *
     * @ORM\Column(name="dem_bib", type="text", length=65535, nullable=true)
     */
    private $demBib ='';

    /**
     * @var string
     *
     * @ORM\Column(name="dem_post_trait", type="string", length=15, nullable=true)
     */
    private $demPostTrait = '';

    /**
     * @var string
     *
     * @ORM\Column(name="dem_form_maison", type="text", length=65535, nullable=true)
     */
    private $demFormMaison = '';

    /**
     * @var boolean
     *
     * @ORM\Column(name="dem_form_prise", type="boolean", nullable=true)
     */
    private $demFormPrise = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="dem_form_debogage", type="boolean", nullable=true)
     */
    private $demFormDebogage = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="dem_form_optimisation", type="boolean", nullable=true)
     */
    private $demFormOptimisation = false;

    /**
     * @var string
     *
     * @ORM\Column(name="dem_form_autres", type="text", length=65535, nullable=true)
     */
    private $demFormAutres = '';

    /**
     * @var integer
     *
     * @ORM\Column(name="dem_form_0", type="integer", nullable=true)
     */
    private $demForm0 = '';

    /**
     * @var integer
     *
     * @ORM\Column(name="dem_form_1", type="integer", nullable=true)
     */
    private $demForm1 = '';

    /**
     * @var integer
     *
     * @ORM\Column(name="dem_form_2", type="integer", nullable=true)
     */
    private $demForm2 = '';

    /**
     * @var integer
     *
     * @ORM\Column(name="dem_form_3", type="integer", nullable=true)
     */
    private $demForm3 = '';

    /**
     * @var integer
     *
     * @ORM\Column(name="dem_form_4", type="integer", nullable=true)
     */
    private $demForm4 = '';

    /**
     * @var integer
     *
     * @ORM\Column(name="dem_form_5", type="integer", nullable=true)
     */
    private $demForm5 = '';

    /**
     * @var integer
     *
     * @ORM\Column(name="dem_form_6", type="integer", nullable=true)
     */
    private $demForm6 = '';

    /**
     * @var integer
     *
     * @ORM\Column(name="dem_form_7", type="integer", nullable=true)
     */
    private $demForm7 = '';

    /**
     * @var integer
     *
     * @ORM\Column(name="dem_form_8", type="integer", nullable=true)
     */
    private $demForm8 = '';

    /**
     * @var integer
     *
     * @ORM\Column(name="dem_form_9", type="integer", nullable=true)
     */
    private $demForm9 = '';

    /**
     * @var boolean
     *
     * @ORM\Column(name="dem_form_fortran", type="boolean", nullable=true)
     */
    private $demFormFortran = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="dem_form_c", type="boolean", nullable=true)
     */
    private $demFormC = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="dem_form_cpp", type="boolean", nullable=true)
     */
    private $demFormCpp = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="dem_form_python", type="boolean", nullable=true)
     */
    private $demFormPython = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="dem_form_mpi", type="boolean", nullable=true)
     */
    private $demFormMPI = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="dem_form_openmp", type="boolean", nullable=true)
     */
    private $demFormOpenMP = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="dem_form_openacc", type="boolean", nullable=true)
     */
    private $demFormOpenACC = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="dem_form_paraview", type="boolean", nullable=true)
     */
    private $demFormParaview = false;

    /**
     * @var string
     *
     * @ORM\Column(name="libelle_thematique", type="string", length=200, nullable=true)
     */
    private $libelleThematique ='';

    /**
     * @var boolean
     *
     * @ORM\Column(name="attr_accept", type="boolean", nullable=true)
     */
    private $attrAccept = true;


    /**
     * @var integer
     *
     * @ORM\Column(name="rap_conf", type="integer", nullable=true)
     */
    private $rapConf = 0;

    /**
     * @var \App\Entity\Individu
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Individu")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="maj_ind", referencedColumnName="id_individu",onDelete="SET NULL")
     * })
     */
    private $majInd;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="maj_stamp", type="datetime", nullable=true)
     */
    private $majStamp;

    /**
     * @var string
     *
     * @ORM\Column(name="sond_vol_donn_perm", type="string", length=15, nullable=true)
     */
    private $sondVolDonnPerm = '';

    /**
     * @var string
     *
     * @ORM\Column(name="sond_duree_donn_perm", type="string", length=15, nullable=true)
     */
    private $sondDureeDonnPerm = '';

    /**
     * @var integer
     *
     * @ORM\Column(name="prj_fiche_len", type="integer", nullable=true)
     */
    private $prjFicheLen = 0;

    /**
     * @var integer
     *
     * @ORM\Column(name="penal_heures", type="integer", nullable=true)
     */
    private $penalHeures = 0;

    /**
     * @var integer
     *
     * @ORM\Column(name="attr_heures_ete", type="integer", nullable=true)
     */
    private $attrHeuresEte = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="sond_justif_donn_perm", type="text", nullable=true)
     */
    private $sondJustifDonnPerm = '';

    /**
     * @var string
     *
     * @ORM\Column(name="dem_form_autres_autres", type="text", length=65535, nullable=true)
     */
    private $demFormAutresAutres = '';

    /**
     * @var boolean
     *
     * @ORM\Column(name="cgu", type="boolean", nullable=true)
     */
    private $CGU = false;

    /**
     * @var string
     *
     * @ORM\Column(name="id_version", type="string", length=13)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $idVersion;

    /**
     * @var \App\Entity\Thematique
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Thematique")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="prj_id_thematique", referencedColumnName="id_thematique")
     * })
     */
    private $prjThematique;


    /**
     * @var \App\Entity\Rattachement
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Rattachement")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="prj_id_rattachement", referencedColumnName="id_rattachement")
     * })
     */
    private $prjRattachement;

    /**
     * @var \App\Entity\Session
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Session")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_session", referencedColumnName="id_session")
     * })
     */
    private $session;

    /**
     * @var \App\Entity\Projet
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Projet", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_projet", referencedColumnName="id_projet", nullable=true )
     * })
     */
    private $projet;

    /// Ajout Callisto: Septembre 2019
    /**
     * @var \App\Entity\dataMetaDataFormat
     *
     * @ORM\Column(name="data_metadataformat", type="string", length=15, nullable=true)
     *
     */
    private $dataMetaDataFormat;

    /**
     * @var \App\Entity\dataTailleDatasets
     *
     * @ORM\Column(name="data_tailledatasets", type="string", length=15, nullable=true)
     *
     */
    private $dataTailleDatasets;
    /**
     * @var \App\Entity\dataNombreDatasets
     *
     * @ORM\Column(name="data_nombredatasets", type="string", length=15, nullable=true)
     *
     */
    private $dataNombreDatasets;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="fct_stamp", type="datetime", nullable=true)
     */
    private $fctStamp;


    /**
    * @ORM\PostUpdate
    */
    public function setVersionActive()
    // on ne sait pas si cela marche parce que l'on ne s'en sert pas
    {
        if ($this->etatVersion == Etat::ACTIF && $this->projet != null) {
            $this->projet->setVersionActive($this);
        }
    }

    /**
    * convertir la table codeLangage en checkbox
    */
    private function convertCodeLanguage()
    {
        $codeLangage = $this->getCodeLangage();
        if ($codeLangage !=  null) {
            if (preg_match('/Fortran/', $codeLangage)) {
                $this->setCodeFor(true);
            }
            if (preg_match('/C,/', $codeLangage)) {
                $this->setCodeC(true);
            }
            if (preg_match('/C++/', $codeLangage)) {
                $this->setCodeCpp(true);
            }
            if (preg_match('/Autre/', $codeLangage)) {
                $this->setCodeAutre(true);
            }
        }
    }

    /**
    * @ORM\PrePersist
    */
    public function prePersist()
    {
        $this->convertCodeLanguage();
    }

    /**
    * @ORM\PreUpdate
    */
    public function preUpdate()
    {
        $this->convertCodeLanguage();
    }

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="\App\Entity\CollaborateurVersion", mappedBy="version", cascade={"persist"})
     */
    private $collaborateurVersion;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="\App\Entity\Rallonge", mappedBy="version", cascade={"persist"})
     */
    private $rallonge;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="\App\Entity\Expertise", mappedBy="version", cascade={"persist"} )
     */
    private $expertise;


    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="\App\Entity\Projet", mappedBy="versionDerniere", cascade={"persist"} )
     */
    private $versionDerniere;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="\App\Entity\Projet", mappedBy="versionActive", cascade={"persist"} )
     */
    private $versionActive;

    ///////////////////////////////////////////////////////////

    public function __toString()
    {
        return (string)$this->getIdVersion();
    }

    /////////////////////////////////////////////////////////////////


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->collaborateurVersion = new \Doctrine\Common\Collections\ArrayCollection();
        $this->rallonge             = new \Doctrine\Common\Collections\ArrayCollection();
        $this->expertise            = new \Doctrine\Common\Collections\ArrayCollection();
        $this->versionDerniere      = new \Doctrine\Common\Collections\ArrayCollection();
        $this->versionActive        = new \Doctrine\Common\Collections\ArrayCollection();
        $this->etatVersion          = Etat::EDITION_DEMANDE;
    }

    /**
     * Set etatVersion
     *
     * @param integer $etatVersion
     *
     * @return Version
     */
    public function setEtatVersion($etatVersion)
    {
        $this->etatVersion = $etatVersion;

        return $this;
    }
    public function setEtat($etatVersion)
    {
        return $this->setEtatVersion($etatVersion);
    }

    /**
     * Get etatVersion
     *
     * @return integer
     */
    public function getEtatVersion()
    {
        return $this->etatVersion;
    }


    /**
     * Set prjLLabo
     *
     * @param string $prjLLabo
     *
     * @return Version
     */
    public function setPrjLLabo($prjLLabo)
    {
        $this->prjLLabo = $prjLLabo;

        return $this;
    }

    /**
     * Get prjLLabo
     *
     * @return string
     */
    public function getPrjLLabo()
    {
        return $this->prjLLabo;
    }

    /**
     * Set prjTitre
     *
     * @param string $prjTitre
     *
     * @return Version
     */
    public function setPrjTitre($prjTitre)
    {
        $this->prjTitre = $prjTitre;

        return $this;
    }

    /**
     * Get prjTitre
     *
     * @return string
     */
    public function getPrjTitre()
    {
        return $this->prjTitre;
    }

    /**
     * Set demHeures
     *
     * @param integer $demHeures
     *
     * @return Version
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
     * Set attrHeures
     *
     * @param integer $attrHeures
     *
     * @return Version
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
     * Set prjSousThematique
     *
     * @param string $prjSousThematique
     *
     * @return Version
     */
    public function setPrjSousThematique($prjSousThematique)
    {
        $this->prjSousThematique = $prjSousThematique;

        return $this;
    }

    /**
     * Get prjSousThematique
     *
     * @return string
     */
    public function getPrjSousThematique()
    {
        return $this->prjSousThematique;
    }

    /**
     * Set dataMetaDataFormat
     *
     * @param string $dataMetaDataFormat
     *
     * @return Version
     */
    public function setDataMetaDataFormat($dataMetaDataFormat)
    {
        $this->dataMetaDataFormat = $dataMetaDataFormat;

        return $this;
    }

    /**
     * Get dataMetaDataFormat
     *
     * @return string
     */
    public function getDataMetaDataFormat()
    {
        return $this->dataMetaDataFormat;
    }
    /**
     * Set dataNombreDatasets
     *
     * @param string $dataNombreDatasets
     *
     * @return Version
     */
    public function setDataNombreDatasets($dataNombreDatasets)
    {
        $this->dataNombreDatasets = $dataNombreDatasets;

        return $this;
    }

    /**
     * Get dataNombreDatasets
     *
     * @return string
     */
    public function getDataNombreDatasets()
    {
        return $this->dataNombreDatasets;
    }
    /**
     * Set dataTailleDatasets
     *
     * @param string $dataTailleDatasets
     *
     * @return Version
     */
    public function setDataTailleDatasets($dataTailleDatasets)
    {
        $this->dataTailleDatasets = $dataTailleDatasets;
        return $this;
    }
    /**
     * Get dataTailleDatasets
     *
     * @return string
     */
    public function getDataTailleDatasets()
    {
        return $this->dataTailleDatasets;
    }
    /**
     * Set prjFinancement
     *
     * @param string $prjFinancement
     *
     * @return Version
     */
    public function setPrjFinancement($prjFinancement)
    {
        $this->prjFinancement = $prjFinancement;

        return $this;
    }

    /**
     * Get prjFinancement
     *
     * @return string
     */
    public function getPrjFinancement()
    {
        return $this->prjFinancement;
    }

    /**
     * Set prjGenciMachines
     *
     * @param string $prjGenciMachines
     *
     * @return Version
     */
    public function setPrjGenciMachines($prjGenciMachines)
    {
        $this->prjGenciMachines = $prjGenciMachines;

        return $this;
    }

    /**
     * Get prjGenciMachines
     *
     * @return string
     */
    public function getPrjGenciMachines()
    {
        return $this->prjGenciMachines;
    }

    /**
     * Set prjGenciCentre
     *
     * @param string $prjGenciCentre
     *
     * @return Version
     */
    public function setPrjGenciCentre($prjGenciCentre)
    {
        $this->prjGenciCentre = $prjGenciCentre;

        return $this;
    }

    /**
     * Get prjGenciCentre
     *
     * @return string
     */
    public function getPrjGenciCentre()
    {
        return $this->prjGenciCentre;
    }

    /**
     * Set prjGenciDari
     *
     * @param string $prjGenciDari
     *
     * @return Version
     */
    public function setPrjGenciDari($prjGenciDari)
    {
        $this->prjGenciDari = $prjGenciDari;

        return $this;
    }

    /**
     * Get prjGenciDari
     *
     * @return string
     */
    public function getPrjGenciDari()
    {
        return $this->prjGenciDari;
    }

    /**
     * Set prjGenciHeures
     *
     * @param string $prjGenciHeures
     *
     * @return Version
     */
    public function setPrjGenciHeures($prjGenciHeures)
    {
        $this->prjGenciHeures = $prjGenciHeures;

        return $this;
    }

    /**
     * Get prjGenciHeures
     *
     * @return string
     */
    public function getPrjGenciHeures()
    {
        return $this->prjGenciHeures;
    }

    /**
     * Set prjResume
     *
     * @param string $prjResume
     *
     * @return Version
     */
    public function setPrjResume($prjResume)
    {
        $this->prjResume = $prjResume;

        return $this;
    }

    /**
     * Get prjResume
     *
     * @return string
     */
    public function getPrjResume()
    {
        return $this->prjResume;
    }

    /**
     * Set prjExpose
     *
     * @param string $prjExpose
     *
     * @return Version
     */
    public function setPrjExpose($prjExpose)
    {
        $this->prjExpose = $prjExpose;

        return $this;
    }

    /**
     * Get prjExpose
     *
     * @return string
     */
    public function getPrjExpose()
    {
        return $this->prjExpose;
    }

    /**
     * Set prjJustifRenouv
     *
     * @param string $prjJustifRenouv
     *
     * @return Version
     */
    public function setPrjJustifRenouv($prjJustifRenouv)
    {
        $this->prjJustifRenouv = $prjJustifRenouv;

        return $this;
    }

    /**
     * Get prjJustifRenouv
     *
     * @return string
     */
    public function getPrjJustifRenouv()
    {
        return $this->prjJustifRenouv;
    }

    /**
     * Set prjAlgorithme
     *
     * @param string $prjAlgorithme
     *
     * @return Version
     */
    public function setPrjAlgorithme($prjAlgorithme)
    {
        $this->prjAlgorithme = $prjAlgorithme;

        return $this;
    }

    /**
     * Get prjAlgorithme
     *
     * @return string
     */
    public function getPrjAlgorithme()
    {
        return $this->prjAlgorithme;
    }

    /**
     * Set prjConception
     *
     * @param boolean $prjConception
     *
     * @return Version
     */
    public function setPrjConception($prjConception)
    {
        $this->prjConception = $prjConception;

        return $this;
    }

    /**
     * Get prjConception
     *
     * @return boolean
     */
    public function getPrjConception()
    {
        return $this->prjConception;
    }

    /**
     * Set prjDeveloppement
     *
     * @param boolean $prjDeveloppement
     *
     * @return Version
     */
    public function setPrjDeveloppement($prjDeveloppement)
    {
        $this->prjDeveloppement = $prjDeveloppement;

        return $this;
    }

    /**
     * Get prjDeveloppement
     *
     * @return boolean
     */
    public function getPrjDeveloppement()
    {
        return $this->prjDeveloppement;
    }

    /**
     * Set prjParallelisation
     *
     * @param boolean $prjParallelisation
     *
     * @return Version
     */
    public function setPrjParallelisation($prjParallelisation)
    {
        $this->prjParallelisation = $prjParallelisation;

        return $this;
    }

    /**
     * Get prjParallelisation
     *
     * @return boolean
     */
    public function getPrjParallelisation()
    {
        return $this->prjParallelisation;
    }

    /**
     * Set prjUtilisation
     *
     * @param boolean $prjUtilisation
     *
     * @return Version
     */
    public function setPrjUtilisation($prjUtilisation)
    {
        $this->prjUtilisation = $prjUtilisation;

        return $this;
    }

    /**
     * Get prjUtilisation
     *
     * @return boolean
     */
    public function getPrjUtilisation()
    {
        return $this->prjUtilisation;
    }

    /**
     * Set prjFiche
     *
     * @param string $prjFiche
     *
     * @return Version
     */
    public function setPrjFiche($prjFiche)
    {
        $this->prjFiche = $prjFiche;

        return $this;
    }

    /**
     * Get prjFiche
     *
     * @return string
     */
    public function getPrjFiche()
    {
        return $this->prjFiche;
    }

    /**
     * Set prjFicheVal
     *
     * @param boolean $prjFicheVal
     *
     * @return Version
     */
    public function setPrjFicheVal($prjFicheVal)
    {
        $this->prjFicheVal = $prjFicheVal;

        return $this;
    }

    /**
     * Get prjFicheVal
     *
     * @return boolean
     */
    public function getPrjFicheVal()
    {
        return $this->prjFicheVal;
    }

    /**
     * Set codeNom
     *
     * @param string $codeNom
     *
     * @return Version
     */
    public function setCodeNom($codeNom)
    {
        $this->codeNom = $codeNom;

        return $this;
    }

    /**
     * Get codeNom
     *
     * @return string
     */
    public function getCodeNom()
    {
        return $this->codeNom;
    }

    /**
     * Set codeLangage
     *
     * @param string $codeLangage
     *
     * @return Version
     */
    public function setCodeLangage($codeLangage)
    {
        $this->codeLangage = $codeLangage;

        return $this;
    }

    /**
     * Get codeLangage
     *
     * @return string
     */
    public function getCodeLangage()
    {
        return $this->codeLangage;
    }

    /**
     * Set codeC
     *
     * @param boolean $codeC
     *
     * @return Version
     */
    public function setCodeC($codeC)
    {
        $this->codeC = $codeC;

        return $this;
    }

    /**
     * Get codeC
     *
     * @return boolean
     */
    public function getCodeC()
    {
        return $this->codeC;
    }

    /**
     * Set codeCpp
     *
     * @param boolean $codeCpp
     *
     * @return Version
     */
    public function setCodeCpp($codeCpp)
    {
        $this->codeCpp = $codeCpp;

        return $this;
    }

    /**
     * Get codeCpp
     *
     * @return boolean
     */
    public function getCodeCpp()
    {
        return $this->codeCpp;
    }

    /**
     * Set codeFor
     *
     * @param boolean $codeFor
     *
     * @return Version
     */
    public function setFor($codeFor)
    {
        $this->codeFor = $codeFor;

        return $this;
    }

    /**
     * Get codeFor
     *
     * @return boolean
     */
    public function getCodeFor()
    {
        return $this->codeFor;
    }

    /**
     * Set codeAutre
     *
     * @param boolean $codeAutre
     *
     * @return Version
     */
    public function setAutre($codeAutre)
    {
        $this->codeAutre = $codeAutre;

        return $this;
    }

    /**
     * Get codeAutre
     *
     * @return boolean
     */
    public function getCodeAutre()
    {
        return $this->codeAutre;
    }

    /**
     * Set codeLicence
     *
     * @param string $codeLicence
     *
     * @return Version
     */
    public function setCodeLicence($codeLicence)
    {
        $this->codeLicence = $codeLicence;

        return $this;
    }

    /**
     * Get codeLicence
     *
     * @return string
     */
    public function getCodeLicence()
    {
        return $this->codeLicence;
    }

    /**
     * Set codeUtilSurMach
     *
     * @param string $codeUtilSurMach
     *
     * @return Version
     */
    public function setCodeUtilSurMach($codeUtilSurMach)
    {
        $this->codeUtilSurMach = $codeUtilSurMach;

        return $this;
    }

    /**
     * Get codeUtilSurMach
     *
     * @return string
     */
    public function getCodeUtilSurMach()
    {
        return $this->codeUtilSurMach;
    }

    /**
     * Set codeHeuresPJob
     *
     * @param string $codeHeuresPJob
     *
     * @return Version
     */
    public function setCodeHeuresPJob($codeHeuresPJob)
    {
        $this->codeHeuresPJob = $codeHeuresPJob;

        return $this;
    }

    /**
     * Get codeHeuresPJob
     *
     * @return string
     */
    public function getCodeHeuresPJob()
    {
        return $this->codeHeuresPJob;
    }

    /**
     * Set codeRamPCoeur
     *
     * @param string $codeRamPCoeur
     *
     * @return Version
     */
    public function setCodeRamPCoeur($codeRamPCoeur)
    {
        $this->codeRamPCoeur = $codeRamPCoeur;

        return $this;
    }

    /**
     * Get codeRamPCoeur
     *
     * @return string
     */
    public function getCodeRamPCoeur()
    {
        return $this->codeRamPCoeur;
    }

    /**
     * Set gpu
     *
     * @param string $gpu
     *
     * @return Version
     */
    public function setGpu($gpu)
    {
        $this->gpu = $gpu;

        return $this;
    }

    /**
     * Get gpu
     *
     * @return string
     */
    public function getGpu()
    {
        return $this->gpu;
    }

    /**
     * Set codeRamPart
     *
     * @param string $codeRamPart
     *
     * @return Version
     */
    public function setCodeRamPart($codeRamPart)
    {
        $this->codeRamPart = $codeRamPart;

        return $this;
    }

    /**
     * Get codeRamPart
     *
     * @return string
     */
    public function getCodeRamPart()
    {
        return $this->codeRamPart;
    }

    /**
     * Set codeEffParal
     *
     * @param string $codeEffParal
     *
     * @return Version
     */
    public function setCodeEffParal($codeEffParal)
    {
        $this->codeEffParal = $codeEffParal;

        return $this;
    }

    /**
     * Get codeEffParal
     *
     * @return string
     */
    public function getCodeEffParal()
    {
        return $this->codeEffParal;
    }

    /**
     * Set codeVolDonnTmp
     *
     * @param string $codeVolDonnTmp
     *
     * @return Version
     */
    public function setCodeVolDonnTmp($codeVolDonnTmp)
    {
        $this->codeVolDonnTmp = $codeVolDonnTmp;

        return $this;
    }

    /**
     * Get codeVolDonnTmp
     *
     * @return string
     */
    public function getCodeVolDonnTmp()
    {
        return $this->codeVolDonnTmp;
    }

    /**
     * Set demLogiciels
     *
     * @param string $demLogiciels
     *
     * @return Version
     */
    public function setDemLogiciels($demLogiciels)
    {
        $this->demLogiciels = $demLogiciels;

        return $this;
    }

    /**
     * Get demLogiciels
     *
     * @return string
     */
    public function getDemLogiciels()
    {
        return $this->demLogiciels;
    }

    /**
     * Set demBib
     *
     * @param string $demBib
     *
     * @return Version
     */
    public function setDemBib($demBib)
    {
        $this->demBib = $demBib;

        return $this;
    }

    /**
     * Get demBib
     *
     * @return string
     */
    public function getDemBib()
    {
        return $this->demBib;
    }

    /**
     * Set demPostTrait
     *
     * @param string $demPostTrait
     *
     * @return Version
     */
    public function setDemPostTrait($demPostTrait)
    {
        $this->demPostTrait = $demPostTrait;

        return $this;
    }

    /**
     * Get demPostTrait
     *
     * @return string
     */
    public function getDemPostTrait()
    {
        return $this->demPostTrait;
    }

    /**
     * Set demFormMaison
     *
     * @param string $demFormMaison
     *
     * @return Version
     */
    public function setDemFormMaison($demFormMaison)
    {
        $this->demFormMaison = $demFormMaison;

        return $this;
    }

    /**
     * Get demFormMaison
     *
     * @return string
     */
    public function getDemFormMaison()
    {
        return $this->demFormMaison;
    }

    /**
     * Set demFormAutres
     *
     * @param string $demFormAutres
     *
     * @return Version
     */
    public function setDemFormAutres($demFormAutres)
    {
        $this->demFormAutres = $demFormAutres;

        return $this;
    }

    /**
     * Set codeFor
     *
     * @param boolean $codeFor
     *
     * @return Version
     */
    public function setCodeFor($codeFor)
    {
        $this->codeFor = $codeFor;

        return $this;
    }

    /**
     * Set codeAutre
     *
     * @param boolean $codeAutre
     *
     * @return Version
     */
    public function setCodeAutre($codeAutre)
    {
        $this->codeAutre = $codeAutre;

        return $this;
    }

    /**
     * Set demFormPrise
     *
     * @param boolean $demFormPrise
     *
     * @return Version
     */
    public function setDemFormPrise($demFormPrise)
    {
        $this->demFormPrise = $demFormPrise;

        return $this;
    }

    /**
     * Get demFormPrise
     *
     * @return boolean
     */
    public function getDemFormPrise()
    {
        return $this->demFormPrise;
    }

    /**
     * Set demFormDebogage
     *
     * @param boolean $demFormDebogage
     *
     * @return Version
     */
    public function setDemFormDebogage($demFormDebogage)
    {
        $this->demFormDebogage = $demFormDebogage;

        return $this;
    }

    /**
     * Get demFormDebogage
     *
     * @return boolean
     */
    public function getDemFormDebogage()
    {
        return $this->demFormDebogage;
    }

    /**
     * Set demFormOptimisation
     *
     * @param boolean $demFormOptimisation
     *
     * @return Version
     */
    public function setDemFormOptimisation($demFormOptimisation)
    {
        $this->demFormOptimisation = $demFormOptimisation;

        return $this;
    }

    /**
     * Get demFormOptimisation
     *
     * @return boolean
     */
    public function getDemFormOptimisation()
    {
        return $this->demFormOptimisation;
    }

    /**
     * Set demFormFortran
     *
     * @param boolean $demFormFortran
     *
     * @return Version
     */
    public function setDemFormFortran($demFormFortran)
    {
        $this->demFormFortran = $demFormFortran;

        return $this;
    }

    /**
     * Get demFormFortran
     *
     * @return boolean
     */
    public function getDemFormFortran()
    {
        return $this->demFormFortran;
    }

    /**
     * Set demFormC
     *
     * @param boolean $demFormC
     *
     * @return Version
     */
    public function setDemFormC($demFormC)
    {
        $this->demFormC = $demFormC;

        return $this;
    }

    /**
     * Get demFormC
     *
     * @return boolean
     */
    public function getDemFormC()
    {
        return $this->demFormC;
    }

    /**
     * Set demFormCpp
     *
     * @param boolean $demFormCpp
     *
     * @return Version
     */
    public function setDemFormCpp($demFormCpp)
    {
        $this->demFormCpp = $demFormCpp;

        return $this;
    }

    /**
     * Get demFormCpp
     *
     * @return boolean
     */
    public function getDemFormCpp()
    {
        return $this->demFormCpp;
    }

    /**
     * Set demFormPython
     *
     * @param boolean $demFormPython
     *
     * @return Version
     */
    public function setDemFormPython($demFormPython)
    {
        $this->demFormPython = $demFormPython;

        return $this;
    }

    /**
     * Get demFormPython
     *
     * @return boolean
     */
    public function getDemFormPython()
    {
        return $this->demFormPython;
    }

    /**
     * Set demFormMPI
     *
     * @param boolean $demFormMPI
     *
     * @return Version
     */
    public function setDemFormMPI($demFormMPI)
    {
        $this->demFormMPI = $demFormMPI;

        return $this;
    }

    /**
     * Get demFormMPI
     *
     * @return boolean
     */
    public function getDemFormMPI()
    {
        return $this->demFormMPI;
    }

    /**
     * Set demFormOpenMP
     *
     * @param boolean $demFormOpenMP
     *
     * @return Version
     */
    public function setDemFormOpenMP($demFormOpenMP)
    {
        $this->demFormOpenMP = $demFormOpenMP;

        return $this;
    }

    /**
     * Get demFormOpenMP
     *
     * @return boolean
     */
    public function getDemFormOpenMP()
    {
        return $this->demFormOpenMP;
    }

    /**
     * Set demFormOpenACC
     *
     * @param boolean $demFormOpenACC
     *
     * @return Version
     */
    public function setDemFormOpenACC($demFormOpenACC)
    {
        $this->demFormOpenACC = $demFormOpenACC;

        return $this;
    }

    /**
     * Get demFormOpenACC
     *
     * @return boolean
     */
    public function getDemFormOpenACC()
    {
        return $this->demFormOpenACC;
    }

    /**
     * Set demFormParaview
     *
     * @param boolean $demFormParaview
     *
     * @return Version
     */
    public function setDemFormParaview($demFormParaview)
    {
        $this->demFormParaview = $demFormParaview;

        return $this;
    }

    /**
     * Get demFormParaview
     *
     * @return boolean
     */
    public function getDemFormParaview()
    {
        return $this->demFormParaview;
    }

    /**
     * Get demFormAutres
     *
     * @return string
     */
    public function getDemFormAutres()
    {
        return $this->demFormAutres;
    }

    /**
     * Set demForm0
     *
     * @param boolean $demForm0
     *
     * @return Version
     */
    public function setDemForm0($demForm0)
    {
        $this->demForm0 = $demForm0;

        return $this;
    }

    /**
     * Get demForm0
     *
     * @return boolean
     */
    public function getDemForm0()
    {
        return $this->demForm0;
    }

    /**
     * Set demForm1
     *
     * @param boolean $demForm1
     *
     * @return Version
     */
    public function setDemForm1($demForm1)
    {
        $this->demForm1 = $demForm1;

        return $this;
    }

    /**
     * Get demForm1
     *
     * @return boolean
     */
    public function getDemForm1()
    {
        return $this->demForm1;
    }

    /**
     * Set demForm2
     *
     * @param boolean $demForm2
     *
     * @return Version
     */
    public function setDemForm2($demForm2)
    {
        $this->demForm2 = $demForm2;

        return $this;
    }

    /**
     * Get demForm2
     *
     * @return boolean
     */
    public function getDemForm2()
    {
        return $this->demForm2;
    }

    /**
     * Set demForm3
     *
     * @param boolean $demForm3
     *
     * @return Version
     */
    public function setDemForm3($demForm3)
    {
        $this->demForm3 = $demForm3;

        return $this;
    }

    /**
     * Get demForm3
     *
     * @return boolean
     */
    public function getDemForm3()
    {
        return $this->demForm3;
    }

    /**
     * Set demForm4
     *
     * @param boolean $demForm4
     *
     * @return Version
     */
    public function setDemForm4($demForm4)
    {
        $this->demForm4 = $demForm4;

        return $this;
    }

    /**
     * Get demForm4
     *
     * @return boolean
     */
    public function getDemForm4()
    {
        return $this->demForm4;
    }

    /**
     * Set demForm5
     *
     * @param boolean $demForm5
     *
     * @return Version
     */
    public function setDemForm5($demForm5)
    {
        $this->demForm5 = $demForm5;

        return $this;
    }

    /**
     * Get demForm5
     *
     * @return boolean
     */
    public function getDemForm5()
    {
        return $this->demForm5;
    }

    /**
     * Set demForm6
     *
     * @param boolean $demForm6
     *
     * @return Version
     */
    public function setDemForm6($demForm6)
    {
        $this->demForm6 = $demForm6;

        return $this;
    }

    /**
     * Get demForm6
     *
     * @return boolean
     */
    public function getDemForm6()
    {
        return $this->demForm6;
    }

    /**
     * Set demForm7
     *
     * @param boolean $demForm7
     *
     * @return Version
     */
    public function setDemForm7($demForm7)
    {
        $this->demForm7 = $demForm7;

        return $this;
    }

    /**
     * Get demForm7
     *
     * @return boolean
     */
    public function getDemForm7()
    {
        return $this->demForm7;
    }

    /**
     * Set demForm8
     *
     * @param boolean $demForm8
     *
     * @return Version
     */
    public function setDemForm8($demForm8)
    {
        $this->demForm8 = $demForm8;

        return $this;
    }

    /**
     * Get demForm8
     *
     * @return boolean
     */
    public function getDemForm8()
    {
        return $this->demForm8;
    }

    /**
     * Set demForm9
     *
     * @param boolean $demForm9
     *
     * @return Version
     */
    public function setDemForm9($demForm9)
    {
        $this->demForm9 = $demForm9;

        return $this;
    }

    /**
     * Get demForm9
     *
     * @return boolean
     */
    public function getDemForm9()
    {
        return $this->demForm9;
    }

    /**
     * Set libelleThematique
     *
     * @param string $libelleThematique
     *
     * @return Version
     */
    public function setLibelleThematique($libelleThematique)
    {
        $this->libelleThematique = $libelleThematique;

        return $this;
    }

    /**
     * Get libelleThematique
     *
     * @return string
     */
    public function getLibelleThematique()
    {
        return $this->libelleThematique;
    }

    /**
     * Set attrAccept
     *
     * @param boolean $attrAccept
     *
     * @return Version
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
     * Set rapConf
     *
     * @param integer $rapConf
     *
     * @return Version
     */
    public function setRapConf($rapConf)
    {
        $this->rapConf = $rapConf;

        return $this;
    }

    /**
     * Get rapConf
     *
     * @return integer
     */
    public function getRapConf()
    {
        return $this->rapConf;
    }

    /**
     * Set majInd
     *
     * @param App\Entity\Individu
     *
     * @return Version
     */
    public function setMajInd($majInd)
    {
        $this->majInd = $majInd;

        return $this;
    }

    /**
     * Get majInd
     *
     * @return App\Entity\Individu
     */
    public function getMajInd()
    {
        return $this->majInd;
    }

    /**
     * Set majStamp
     *
     * @param \DateTime $majStamp
     *
     * @return Version
     */
    public function setMajStamp($majStamp)
    {
        $this->majStamp = $majStamp;

        return $this;
    }

    /**
     * Get majStamp
     *
     * @return \DateTime
     */
    public function getMajStamp()
    {
        return $this->majStamp;
    }

    /**
     * Set fctStamp
     *
     * @param \DateTime $fctStamp
     *
     * @return Version
     */
    public function setFctStamp($fctStamp)
    {
        $this->fctStamp = $fctStamp;

        return $this;
    }

    /**
     * Get fctStamp
     *
     * @return \DateTime
     */
    public function getFctStamp()
    {
        return $this->fctStamp;
    }

    /**
     * Set sondVolDonnPerm
     *
     * @param string $sondVolDonnPerm
     *
     * @return Version
     */
    public function setSondVolDonnPerm($sondVolDonnPerm)
    {
        $this->sondVolDonnPerm = $sondVolDonnPerm;

        return $this;
    }

    /**
     * Get sondVolDonnPerm
     *
     * @return string
     */
    public function getSondVolDonnPerm()
    {
        return $this->sondVolDonnPerm;
    }

    /**
     * Set sondDureeDonnPerm
     *
     * @param string $sondDureeDonnPerm
     *
     * @return Version
     */
    public function setSondDureeDonnPerm($sondDureeDonnPerm)
    {
        $this->sondDureeDonnPerm = $sondDureeDonnPerm;

        return $this;
    }

    /**
     * Get sondDureeDonnPerm
     *
     * @return string
     */
    public function getSondDureeDonnPerm()
    {
        return $this->sondDureeDonnPerm;
    }

    /**
     * Set prjFicheLen
     *
     * @param integer $prjFicheLen
     *
     * @return Version
     */
    public function setPrjFicheLen($prjFicheLen)
    {
        $this->prjFicheLen = $prjFicheLen;

        return $this;
    }

    /**
     * Get prjFicheLen
     *
     * @return integer
     */
    public function getPrjFicheLen()
    {
        return $this->prjFicheLen;
    }

    /**
     * Set penalHeures
     *
     * @param integer $penalHeures
     *
     * @return Version
     */
    public function setPenalHeures($penalHeures)
    {
        $this->penalHeures = $penalHeures;

        return $this;
    }

    /**
     * Get penalHeures
     *
     * @return integer
     */
    public function getPenalHeures()
    {
        return $this->penalHeures;
    }

    /**
     * Set attrHeuresEte
     *
     * @param integer $attrHeuresEte
     *
     * @return Version
     */
    public function setAttrHeuresEte($attrHeuresEte)
    {
        $this->attrHeuresEte = $attrHeuresEte;

        return $this;
    }

    /**
     * Get attrHeuresEte
     *
     * @return integer
     */
    public function getAttrHeuresEte()
    {
        return $this->attrHeuresEte;
    }

    /**
     * Set sondJustifDonnPerm
     *
     * @param string $sondJustifDonnPerm
     *
     * @return Version
     */
    public function setSondJustifDonnPerm($sondJustifDonnPerm)
    {
        $this->sondJustifDonnPerm = $sondJustifDonnPerm;

        return $this;
    }

    /**
     * Get sondJustifDonnPerm
     *
     * @return string
     */
    public function getSondJustifDonnPerm()
    {
        return $this->sondJustifDonnPerm;
    }

    /**
     * Set demFormAutresAutres
     *
     * @param string $demFormAutresAutres
     *
     * @return Version
     */
    public function setDemFormAutresAutres($demFormAutresAutres)
    {
        $this->demFormAutresAutres = $demFormAutresAutres;

        return $this;
    }

    /**
     * Get demFormAutresAutres
     *
     * @return string
     */
    public function getDemFormAutresAutres()
    {
        return $this->demFormAutresAutres;
    }

    /**
     * Set idVersion
     *
     * @param string $idVersion
     *
     * @return Version
     */
    public function setIdVersion($idVersion)
    {
        $this->idVersion = $idVersion;

        return $this;
    }

    /**
     * Get idVersion
     *
     * @return string
     */
    public function getIdVersion()
    {
        return $this->idVersion;
    }

    /****
     * Get AutreIdVersion
     *
     * 	19AP01234 => 19BP01234
     *  19BP01234 => 19AP01234
     *
     * @return string
     *
     */
    public function getAutreIdVersion()
    {
        $id = $this->getIdVersion();
        $id[2] = ($id[2]==='A') ? 'B' : 'A';
        return $id;
    }

    /**
     * Set CGU
     *
     * @param boolean $CGU
     *
     * @return Version
     */
    public function setCGU($CGU)
    {
        $this->CGU = $CGU;

        return $this;
    }

    /**
     * Get CGU
     *
     * @return boolean
     */
    public function getCGU()
    {
        return $this->CGU;
    }

    /**
     * Get politique
     *
     * @return integer
     */
    public function getPolitique()
    {
        return $this->politique;
    }

    /**
     * Set politique
     *
     * @param integer $politique
     *
     * @return Version
     */
    public function setPolitique($politique)
    {
        $this->politique = $politique;

        return $this;
    }

    /**
     * Set prjThematique
     *
     * @param \App\Entity\Thematique $prjThematique
     *
     * @return Version
     */
    public function setPrjThematique(\App\Entity\Thematique $prjThematique = null)
    {
        $this->prjThematique = $prjThematique;

        return $this;
    }

    /**
     * Get prjThematique
     *
     * @return \App\Entity\Thematique
     */
    public function getPrjThematique()
    {
        return $this->prjThematique;
    }

    /**
     * Set prjRattachement
     *
     * @param \App\Entity\Rattachement $prjRattachement
     *
     * @return Version
     */
    public function setPrjRattachement(\App\Entity\Rattachement $prjRattachement = null)
    {
        $this->prjRattachement = $prjRattachement;

        return $this;
    }

    /**
     * Get prjRattachement
     *
     * @return \App\Entity\Rattachement
     */
    public function getPrjRattachement()
    {
        return $this->prjRattachement;
    }

    /**
     * Set session
     *
     * @param \App\Entity\Session $session
     *
     * @return Version
     */
    public function setSession(\App\Entity\Session $session = null)
    {
        $this->session = $session;

        return $this;
    }

    /**
     * Get session
     *
     * @return \App\Entity\Session
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Set projet
     *
     * @param \App\Entity\Projet $projet
     *
     * @return Version
     */
    public function setProjet(\App\Entity\Projet $projet = null)
    {
        $this->projet = $projet;

        return $this;
    }

    /**
     * Get projet
     *
     * @return \App\Entity\Projet
     */
    public function getProjet()
    {
        return $this->projet;
    }


    /**
     * Add collaborateurVersion
     *
     * @param \App\Entity\CollaborateurVersion $collaborateurVersion
     *
     * @return Version
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
     * Add rallonge
     *
     * @param \App\Entity\Rallonge $rallonge
     *
     * @return Version
     */
    public function addRallonge(\App\Entity\Rallonge $rallonge)
    {
        $this->rallonge[] = $rallonge;

        return $this;
    }

    /**
     * Remove rallonge
     *
     * @param \App\Entity\Rallonge $rallonge
     */
    public function removeRallonge(\App\Entity\Rallonge $rallonge)
    {
        $this->rallonge->removeElement($rallonge);
    }

    /**
     * Get rallonge
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRallonge()
    {
        return $this->rallonge;
    }

    // Expertise

    /**
     * Add expertise
     *
     * @param \App\Entity\Expertise $expertise
     *
     * @return Version
     */
    public function addExpertise(\App\Entity\Expertise $expertise)
    {
        $this->expertise[] = $expertise;

        return $this;
    }

    /**
     * Remove expertise
     *
     * @param \App\Entity\Expertise $expertise
     */
    public function removeExpertise(\App\Entity\Expertise $expertise)
    {
        $this->expertise->removeElement($expertise);
    }

    /**
     * Get expertise
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getExpertise()
    {
        return $this->expertise;
    }

    /***************************************************
     * Fonctions utiles pour la class Workflow
     * Autre nom pour getEtatVersion/setEtatVersion !
     ***************************************************/
    public function getObjectState()
    {
        return $this->getEtatVersion();
    }

    public function setObjectState($state)
    {
        $this->setEtatVersion($state);
        return $this;
    }

    //public function getSubWorkflow()    { return new \App\Workflow\RallongeWorkflow(); }
    //public function getSubObjects()     { return $this->getRallonge();   }

    ///////////////////////////////////////////////////////////////////////////////////

    /* pour bilan session depuis la table CollaborateurVersion
     *
     * getResponsable
     *
     * @return \App\Entity\Individu
     */
    public function getResponsable()
    {
        foreach ($this->getCollaborateurVersion() as $item) {
            if ($item->getResponsable() == true) {
                return $item->getCollaborateur();
            }
        }
        return null;
    }

    public function getResponsables()
    {
        $responsables   = [];
        foreach ($this->getCollaborateurVersion() as $item) {
            if ($item->getResponsable() == true) {
                $responsables[] = $item->getCollaborateur();
            }
        }
        return $responsables;
    }

    /*****************************************************
     * Renvoie les collaborateurs de la version
     *
     * $moi_aussi           == true : je peux être dans la liste éventuellement
     * $seulement_eligibles == true : Individu permanent et d'un labo régional à la fois
     * $moi                 == Individu connecté, qui est $moi (utile seulement si $moi_aussi est false)
     *
     ************************************************************/
    public function getCollaborateurs($moi_aussi=true, $seulement_eligibles=false, Individu $moi=null)
    {
        $collaborateurs = [];
        foreach ($this->getCollaborateurVersion() as $item) {
            $collaborateur   =  $item->getCollaborateur();
            if ($collaborateur == null) {
                //$sj->errorMessage("Version:getCollaborateur : collaborateur null pour CollaborateurVersion ". $item->getId() );
                continue;
            }
            if ($moi_aussi == false && $collaborateur->isEqualTo($moi)) {
                continue;
            }
            if ($seulement_eligibles == false || ($collaborateur->isPermanent() && $collaborateur->isFromLaboRegional())) {
                $collaborateurs[] = $collaborateur;
            }
        }
        return $collaborateurs;
    }

    /*
     *
     * getLabo
     *
     * @return \App\Entity\Laboratoire
     */
    public function getLabo()
    {
        foreach ($this->getCollaborateurVersion() as $item) {
            if ($item->getResponsable() == true) {
                return $item->getLabo();
            }
        }
        return null;
    }

//    public function hasRapportActivite()
//    {
//        $rapportsActitive = $this->getProjet()->getRapportActivite();
//        $annee = $this->getAnneeSession()-1; // rapport de l'année précédente
//        foreach( $rapportsActitive as $rapport )
//            if( $rapport->getAnnee() == $annee ) return true;
//        return false;
//    }

//    public function getDernierRapportActitive()
//    {
//        $rapportsActitive = $this->getProjet()->getRapportActivite();
//        $annee = $this->getAnneeSession()-1; // rapport de l'année précédente
//        foreach( $rapportsActitive as $rapport )
//            if( $rapport->getAnnee() == $annee ) return $rapport;
//        return null;
//    }

    public function getExpert()
    {
        $expertise =  $this->getOneExpertise();
        if ($expertise == null) {
            return null;
        } else {
            return $expertise->getExpert();
        }
    }

    // pour notifications ou affichage
    public function getExperts()
    {
        $experts    =   [];
        foreach ($this->getExpertise() as $item) {
            $experts[]  =  $item ->getExpert();
        }
        return $experts;
    }

    public function hasExpert()
    {
        $expertise =  $this->getOneExpertise();
        if ($expertise == null) {
            return false;
        }

        $expert = $expertise->getExpert();
        if ($expert != null) {
            return true;
        } else {
            return false;
        }
    }

    // pour notifications
    public function getExpertsThematique()
    {
        $thematique = $this->getPrjThematique();
        if ($thematique == null) {
            return null;
        } else {
            return $thematique->getExpert();
        }
    }

    public function getDemHeuresRallonge()
    {
        $demHeures  = 0;
        foreach ($this->getRallonge() as $rallonge) {
            $demHeures   +=  $rallonge->getDemHeures();
        }
        return $demHeures;
    }

    public function getAttrHeuresRallonge()
    {
        $attrHeures  = 0;
        foreach ($this->getRallonge() as $rallonge) {
            $attrHeures   +=  $rallonge->getAttrHeures();
        }
        return $attrHeures;
    }

    public function getAnneeSession()
    {
        return $this->getSession()->getAnneeSession() + 2000;
    }

    public function getLibelleEtat()
    {
        return Etat::getLibelle($this->getEtatVersion());
    }
    public function getTitreCourt()
    {
        $titre = $this->getPrjTitre();

        if (strlen($titre) <= 20) {
            return $titre;
        } else {
            return substr($titre, 0, 20) . "...";
        }
    }

    public function getAcroLaboratoire()
    {
        return preg_replace('/^\s*([^\s]+)\s+(.*)$/', '${1}', $this->getPrjLLabo());
    }

    /*
     * Raccourci vers getConsoCalcul du projet
     */
//    public function getConsoCalcul()
//    {
    //		$projet = $this->getProjet();
    //		$annee  = $this->getAnneeSession();
    //		return $projet->getConsoCalcul($annee);
    //	}

    /*
     * Raccourci vers getQuota du projet
     */
//    public function getQuota()
//    {
    //		$projet = $this->getProjet();
    //		$annee  = $this->getAnneeSession();
    //		return $projet->getQuota($annee);
    //	}

    /*
     * Nombre d'heures demandées, en comptant les rallonges
     */
    public function getDemHeuresTotal()
    {
        return $this->getDemHeures() + $this->getDemHeuresRallonge();
    }

    /*
     * Nombred'heures attribuées, en comptant les rallonges et les pénalités
     */
    public function getAttrHeuresTotal()
    {
        $h = $this->getAttrHeures() + $this->getAttrHeuresRallonge() - $this->getPenalHeures();
        return $h<0 ? 0 : $h;
    }

    // calcul de la consommation à partir de la table Consommation juste pour une session
    // TODOCONSOMMATION - Est  utilisé seulement pour les statistiques
    //	public function getConsoSession()
    //	{
    //		Functions::warningMessage(__FILE__ . ":" . __LINE__ . " getConsoSession n'est pas écrit");
    //		return 0;
    //	}

    // MetaEtat d'une version (et du projet associé)
    // Ne sert que pour l'affichage des états de version
    public function getMetaEtat()
    {
        $etat_version   =   $this->getEtatVersion();

        if ($etat_version == Etat::ACTIF) {
            return 'ACTIF';
        } elseif ($etat_version == Etat::ACTIF_TEST) {
            return 'ACTIF';
        } elseif ($etat_version == Etat::NOUVELLE_VERSION_DEMANDEE) {
            return 'PRESQUE TERMINE';
        } elseif ($etat_version == Etat::ANNULE) {
            return 'ANNULE';
        } elseif ($etat_version == Etat::EDITION_DEMANDE) {
            return 'EDITION';
        } elseif ($etat_version == Etat::EDITION_TEST) {
            return 'EDITION';
        } elseif ($etat_version == Etat::EDITION_EXPERTISE) {
            return 'EXPERTISE';
        } elseif ($etat_version == Etat::EXPERTISE_TEST) {
            return 'EXPERTISE';
        } elseif ($etat_version == Etat::EN_ATTENTE) {
            return 'EN ATTENTE';
        } elseif ($etat_version == Etat::TERMINE) {
            if ($this->getAttrAccept() == true) {
                return 'TERMINE';
            } else {
                return 'REFUSE';
            }
        }
        return 'INCONNU';
    }

    //
    // Individu est-il collaborateur ? Responsable ? Expert ?
    //

    public function isCollaborateur(Individu $individu)
    {
        if ($individu == null) {
            return false;
        }

        foreach ($this->getCollaborateurVersion() as $item) {
            if ($item->getCollaborateur() == null)
                //$sj->errorMessage('Version:isCollaborateur collaborateur null pour CollaborateurVersion ' . $item);
                ; elseif ($item->getCollaborateur()->isEqualTo($individu)) {
                    return true;
                }
        }

        return false;
    }

    public function isResponsable(Individu $individu)
    {
        if ($individu == null) {
            return false;
        }

        foreach ($this->getCollaborateurVersion() as $item) {
            if ($item->getCollaborateur() == null)
                //$sj->errorMessage('Version:isCollaborateur collaborateur null pour CollaborateurVersion ' . $item);
                ; elseif ($item->getCollaborateur()->isEqualTo($individu) && $item->getResponsable() == true) {
                    return true;
                }
        }

        return false;
    }

    public function isExpertDe(Individu $individu)
    {
        if ($individu == null) {
            return false;
        }

        foreach ($this->getExpertise() as $expertise) {
            $expert =  $expertise->getExpert();

            if ($expert == null)
                //$sj->errorMessage("Version:isExpert Expert null dans l'expertise " . $item);
                ; elseif ($expert->isEqualTo($individu)) {
                    return true;
                }
        }
        return false;
    }

    public function isExpertThematique(Individu $individu)
    {
        if ($individu == null) {
            return false;
        }

        ////$sj->debugMessage(__METHOD__ . " thematique : " . Functions::show($thematique) );

        $thematique = $this->getPrjThematique();
        if ($thematique != null) {
            foreach ($thematique->getExpert() as $expert) {
                if ($expert->isEqualTo($individu)) {
                    return true;
                }
            }
        }
        return false;
    }

    //////////////////////////////////

    public function typeSession()
    {
        return substr($this->getIdVersion(), 2, 1);
    }

    ////////////////////////////////////

    public function versionPrecedente()
    {
        // Contrairement au nom ne renvoie pas la version précédente, mais l'avant-dernière !!!
        // La fonction versionPrecedente1() renvoie pour de vrai la version précédente
        // TODO - Supprimer cette fonction, ou la renommer
        $versions   =  $this->getProjet()->getVersion();
        if (count($versions) <= 1) {
            return null;
        }

        $versions   =   $versions->toArray();
        usort(
            $versions,
            function (Version $b, Version $a) {
                    return strcmp($a->getIdVersion(), $b->getIdVersion());
                }
        );

        //$sj->debugMessage( __METHOD__ .':'. __LINE__ . " version ID 0 1 = " . $versions[0]." " . $versions[1] );
        return $versions[1];
    }

    public function versionPrecedente1()
    {
        $versions   =  $this->getProjet()->getVersion() -> toArray();
        // On trie les versions dans l'ordre croissant
        usort(
            $versions,
            function (Version $a, Version $b) {
                return strcmp($a->getIdVersion(), $b->getIdVersion());
            }
        );
        $k = array_search($this->getIdVersion(), $versions);
        if ($k===false || $k===0) {
            return null;
        } else {
            return $versions[$k-1];
        }
    }


    //////////////////////////////////////////////

    public function anneeRapport()
    {
        $anneeRapport = 0;
        $myAnnee    =  substr($this->getIdVersion(), 0, 2);
        foreach ($this->getProjet()->getVersion() as $version) {
            $annee = substr($version->getIdVersion(), 0, 2);
            if ($annee < $myAnnee) {
                $anneeRapport = max($annee, $anneeRapport);
            }
        }

        if ($anneeRapport < 10 && $anneeRapport > 0) {
            return '200' . $anneeRapport ;
        } elseif ($anneeRapport >= 10) {
            return '20' . $anneeRapport ;
        } else {
            return '0';
        }
    }


    ///////////////////////////////////////////////

    /*********
    * Renvoie l'expertise 0 si elle existe, null sinon
    ***************/
    public function getOneExpertise()
    {
        $expertises =   $this->getExpertise()->toArray();
        if ($expertises !=  null) {
            //$expertise  =   current( $expertises );
            $expertise = $expertises[0];

            //Functions::debugMessage(__METHOD__ . " expertise = " . Functions::show( $expertise )
            //    . " expertises = " . Functions::show( $expertises ));
            return $expertise;
        } else {
            //Functions::noticeMessage(__METHOD__ . " version " . $this . " n'a pas d'expertise !");
            return null;
        }
    }

    //////////////////////////////////////////////////

    public function getFullAnnee()
    {
        return '20' . substr($this->getIdVersion(), 0, 2);
    }

    //////////////////////////////////////////////////

    public function isProjetTest()
    {
        $projet =   $this->getProjet();
        if ($projet == null) {
            //$sj->errorMessage(__METHOD__ . ":" . __LINE__ . " version " . $this . " n'est pas associée à un projet !");
            return false;
        } else {
            return $projet->isProjetTest();
        }
    }

    ///////////////////////////////////////////////////

    public function isEdited()
    {
        $etat   =   $this->getEtatVersion();
        return $etat == Etat::EDITION_DEMANDE || $etat == Etat::EDITION_TEST;
    }

    ///////////////////////////////////////////////////

    ////    public function getData()
    ////    {

    //if( $this->getIdVersion()== '18BP18045' )
    //    //$sj->debugMessage(__METHOD__ . ":" . __LINE__ . " La politique de la version " . $this->getIdVersion() . " est (" . $this->getPolitique() .")");
    //return App::getPolitique( $this->getPolitique() )->getData( $this );

    ////    if( $this->getPolitique() == Politique::POLITIQUE || Politique::getLibelle( $this->getPolitique() ) == 'UNKNOWN' )
    ////        $politique = Politique::DEFAULT_POLITIQUE;
    ////    else
    ////       $politique = $this->getPolitique();

    //if( $this->getPolitique() == 2 )
    //    //$sj->debugMessage(__METHOD__ . ":" . __LINE__ . " La politique de la version " . $this->getIdVersion() . " est (" . $this->getPolitique() .")");

    //return App::getPolitique( $this->getPolitique() )->getData( $this );
    ////    return App::getPolitique( $politique )->getData( $this );
    ////    }

    ////////////////////////////////////////////

    public function getAcroEtablissement()
    {
        $responsable = $this->getResponsable();
        if ($responsable == null) {
            return "";
        }

        $etablissement  =   $responsable->getEtab();
        if ($etablissement == null) {
            return "";
        }

        return $etablissement->__toString();
    }

    ////////////////////////////////////////////

    public function getAcroThematique()
    {
        $thematique = $this->getPrjThematique();
        if ($thematique == null) {
            return "sans thématique";
        } else {
            return $thematique->__toString();
        }
    }
    ////////////////////////////////////////////

    public function getAcroMetaThematique()
    {
        $thematique = $this->getPrjThematique();
        if ($thematique == null) {
            return "sans thématique";
        }

        $metathematique =   $thematique->getMetaThematique();
        if ($metathematique == null) {
            return $thematique->__toString() . " sans métathématique";
        } else {
            return  $thematique->getMetaThematique()->__toString();
        }
    }

    /////////////////////////////////////////////////////
    public function getEtat()
    {
        return $this->getEtatVersion();
    }
    public function getId()
    {
        return $this->getIdVersion();
    }
}
