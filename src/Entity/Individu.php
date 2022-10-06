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
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;

use App\Utils\Functions;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Individu
 *
 * @ORM\Table(name="individu", uniqueConstraints={@ORM\UniqueConstraint(name="mail", columns={"mail"})}, indexes={@ORM\Index(name="id_labo", columns={"id_labo"}), @ORM\Index(name="id_statut", columns={"id_statut"}), @ORM\Index(name="id_etab", columns={"id_etab"})})
 * @ORM\Entity(repositoryClass="App\Repository\IndividuRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Individu implements UserInterface, EquatableInterface, PasswordAuthenticatedUserInterface
{
    public const INCONNU       = 0;
    public const POSTDOC       = 1;
    public const ATER          = 2;
    public const DOCTORANT     = 3;
    public const ENSEIGNANT    = 11;
    public const CHERCHEUR     = 12;
    public const INGENIEUR     = 14;

    /* LIBELLE DES STATUTS */
    public const LIBELLE_STATUT =
        [
        self::INCONNU     => 'INCONNU',
        self::POSTDOC     => 'Post-doctorant',
        self::ATER        => 'ATER',
        self::DOCTORANT   => 'Doctorant',
        self::ENSEIGNANT  => 'Enseignant',
        self::CHERCHEUR   => 'Chercheur',
        self::INGENIEUR   => 'Ingénieur'
        ];

    /////////////////////////////////////////////////////////////////////////////////

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="creation_stamp", type="datetime", nullable=false)
     */
    private $creationStamp;

    /**
     * @var string
     *
     * @ORM\Column(name="nom", type="string", length=50, nullable=true)
     */
    private $nom;

    /**
     * @var string
     *
     * @ORM\Column(name="prenom", type="string", length=50, nullable=true)
     */
    private $prenom;

    /**
     * @var string
     *
     * @ORM\Column(name="mail", type="string", length=200, nullable=false)
     * @Assert\Email(
     *     message = "The email '{{ value }}' is not a valid email."
     * )
     */
    private $mail;

    /**
     * @var boolean
     *
     * @ORM\Column(name="admin", type="boolean", nullable=false)
     */
    private $admin = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="sysadmin", type="boolean", nullable=false)
     */
    private $sysadmin = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="obs", type="boolean", nullable=false)
     */
    private $obs = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="expert", type="boolean", nullable=false)
     */
    private $expert = false;

    /**
     * @var boolean
     *
     * @ORM\Column(name="president", type="boolean", nullable=false)
     */
    private $president = false;

    /**
     * @var \App\Entity\Projet
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_statut", referencedColumnName="id_statut")
     * })
     */
    private $statut;

    /**
     * @var boolean
     *
     * @ORM\Column(name="desactive", type="boolean", nullable=false)
     */
    private $desactive = false;

    /**
     * @var integer
     *
     * @ORM\Column(name="id_individu", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $idIndividu;

    /**
     * @var \App\Entity\Laboratoire
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Laboratoire",cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_labo", referencedColumnName="id_labo")
     * })
     */
    private $labo;

    /**
     * @var \App\Entity\Etablissement
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Etablissement")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_etab", referencedColumnName="id_etab")
     * })
     */
    private $etab;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="App\Entity\Thematique", mappedBy="expert")
     */
    private $thematique;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\ManyToMany(targetEntity="App\Entity\Rattachement", mappedBy="expert")
     */
    private $rattachement;

    ///////////////////////////////////////

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="\App\Entity\Sso", mappedBy="individu")
     */
    private $sso;



    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="\App\Entity\CollaborateurVersion", mappedBy="collaborateur")
     */
    private $collaborateurVersion;

    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="\App\Entity\Expertise", mappedBy="expert")
     */
    private $expertise;


    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Journal", mappedBy="individu")
     */
    private $journal;

    ///////////////////////////////////////////

    /**
    * @ORM\PrePersist
    */
    public function setInitialMajStamp()
    {
        $this->creationStamp = new \DateTime();
    }

    //////////////////////////////////////////

    public function __toString()
    {
        if ($this->getPrenom() != null ||  $this->getNom() != null) {
            return $this->getPrenom() . ' ' . $this->getNom();
        } elseif ($this->getMail() != null) {
            return $this->getMail();
        } else {
            return 'sans prénom, nom et mail';
        }
    }

    ////////////////////////////////////////////////////////////////////////////

    /* Pour verifier que deux objets sont égaux, utiliser cet interface et pas == ! */
    public function isEqualTo(UserInterface $user) : bool
    {
        if ($user == null || !$user instanceof Individu) {
            return false;
        }

        if ($this->idIndividu !== $user->getId()) {
            return false;
        } else {
            return true;
        }
    }

    public function getId()
    {
        return $this->idIndividu;
    }

    // implementation UserInterface
    public function getUserIdentifier(): string { return $this->getId();}
    public function getUsername(): string { return $this->getMail();}
    public function getSalt(): ?string { return null;}
    public function getPassword(): ?string { return "";}
    public function eraseCredentials() {}


    ////////////////////////////////////////////////////////////////////////////

    /* LES ROLES DEFINIS DANS L'APPLICATION
     *     - ROLE_DEMANDEUR = Peut demander des ressoureces - Le minimum
     *     - ROLE_ADMIN     = Peut paramétrer l'application et intervenir dans les projets ou le workflow
     *     - ROLE_OBS       = Peut tout observer, mais ne peut agir
     *     - ROLE_EXPERT    = Peut être affecté à un projet pour expertise
     *     - ROLE_PRESIDENT = Peut affecter les experts à des projets
     *     - ROLE_SYSADMIN  = Administrateur système, est observateur et reçoit certains mails
     *     - ROLE_ALLOWED_TO_SWITCH = Peut changer d'identité (actuellement kifkif admin)
     */
    public function getRoles(): array
    {
        $roles[] = 'ROLE_DEMANDEUR';

        if ($this->getAdmin() == true) {
            $roles[] = 'ROLE_ADMIN';
            $roles[] = 'ROLE_OBS';
            $roles[] = 'ROLE_ALLOWED_TO_SWITCH';
        }

        if ($this->getPresident() == true) {
            $roles[] = 'ROLE_PRESIDENT';
            $roles[] = 'ROLE_EXPERT';
        } elseif ($this->getExpert() == true) {
            $roles[] = 'ROLE_EXPERT';
        }

        if ($this->getObs() == true) {
            $roles[] = 'ROLE_OBS';
        }

        if ($this->getSysadmin() == true) {
            $roles[] = 'ROLE_SYSADMIN';
            $roles[] = 'ROLE_OBS';
        }

        return $roles;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->thematique = new \Doctrine\Common\Collections\ArrayCollection();
        $this->rattachement = new \Doctrine\Common\Collections\ArrayCollection();
        $this->sso = new \Doctrine\Common\Collections\ArrayCollection();
        $this->collaborateurVersion = new \Doctrine\Common\Collections\ArrayCollection();
        $this->expertise = new \Doctrine\Common\Collections\ArrayCollection();
        $this->journal = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set creationStamp
     *
     * @param \DateTime $creationStamp
     *
     * @return Individu
     */
    public function setCreationStamp($creationStamp)
    {
        $this->creationStamp = $creationStamp;

        return $this;
    }

    /**
     * Get creationStamp
     *
     * @return \DateTime
     */
    public function getCreationStamp()
    {
        return $this->creationStamp;
    }

    /**
     * Set nom
     *
     * @param string $nom
     *
     * @return Individu
     */
    public function setNom($nom)
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * Get nom
     *
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * Set prenom
     *
     * @param string $prenom
     *
     * @return Individu
     */
    public function setPrenom($prenom)
    {
        $this->prenom = $prenom;

        return $this;
    }

    /**
     * Get prenom
     *
     * @return string
     */
    public function getPrenom()
    {
        return $this->prenom;
    }

    /**
     * Set mail
     *
     * @param string $mail
     *
     * @return Individu
     */
    public function setMail($mail)
    {
        // Suppression des accents et autres ç
        // voir https://stackoverflow.com/questions/1284535/php-transliteration
        //$mail_ascii = transliterator_transliterate('Any-Latin;Latin-ASCII;', $mail);
        //$this->mail = $mail_ascii;
        // Ne fonctionne pas ! plantage dans connection_dbg (???)
        $this->mail = $mail;
        return $this;
    }

    /**
     * Get mail
     *
     * @return string
     */
    public function getMail()
    {
        return $this->mail;
    }

    /**
     * Set admin
     *
     * @param boolean $admin
     *
     * @return Individu
     */
    public function setAdmin($admin)
    {
        $this->admin = $admin;

        return $this;
    }

    /**
     * Set sysadmin
     *
     * @param boolean $sysadmin
     *
     * @return Individu
     */
    public function setSysadmin($sysadmin)
    {
        $this->sysadmin = $sysadmin;

        return $this;
    }

    /**
     * Set obs
     *
     * @param boolean $obs
     *
     * @return Individu
     */
    public function setObs($obs)
    {
        $this->obs = $obs;

        return $this;
    }

    /**
     * Get admin
     *
     * @return boolean
     */
    public function getAdmin()
    {
        return $this->admin;
    }

    /**
     * Get sysadmin
     *
     * @return boolean
     */
    public function getSysadmin()
    {
        return $this->sysadmin;
    }

    /**
     * Get obs
     *
     * @return boolean
     */
    public function getObs()
    {
        return $this->obs;
    }

    /**
     * Set expert
     *
     * @param boolean $expert
     *
     * @return Individu
     */
    public function setExpert($expert)
    {
        $this->expert = $expert;

        return $this;
    }

    /**
     * Get expert
     *
     * @return boolean
     */
    public function getExpert()
    {
        return $this->expert;
    }

    /**
     * Set president
     *
     * @param boolean $president
     *
     * @return Individu
     */
    public function setPresident($president)
    {
        $this->president = $president;
        return $this;
    }

    /**
     * Get president
     *
     * @return boolean
     */
    public function getPresident()
    {
        return $this->president;
    }

    /**
     * Set desactive
     *
     * @param boolean $desactive
     *
     * @return Individu
     */
    public function setDesactive($desactive)
    {
        $this->desactive = $desactive;

        return $this;
    }

    /**
     * Get desactive
     *
     * @return boolean
     */
    public function getDesactive()
    {
        return $this->desactive;
    }

    /**
     * Get idIndividu
     *
     * @return integer
     */
    public function getIdIndividu()
    {
        return $this->idIndividu;
    }

    /**
     * Set statut
     *
     * @param \App\Entity\Statut $statut
     *
     * @return Individu
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
     * Set labo
     *
     * @param \App\Entity\Laboratoire $labo
     *
     * @return Individu
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
     * @return Individu
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
     * Add thematique
     *
     * @param \App\Entity\Thematique $thematique
     *
     * @return Individu
     */
    public function addThematique(\App\Entity\Thematique $thematique)
    {
        $this->thematique[] = $thematique;

        return $this;
    }

    /**
     * Remove thematique
     *
     * @param \App\Entity\Thematique $thematique
     */
    public function removeThematique(\App\Entity\Thematique $thematique)
    {
        $this->thematique->removeElement($thematique);
    }

    /**
     * Get thematique
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getThematique()
    {
        return $this->thematique;
    }

    /**
     * Add rattachement
     *
     * @param \App\Entity\Rattachement $rattachement
     *
     * @return Individu
     */
    public function addRattachement(\App\Entity\Rattachement $rattachement)
    {
        $this->rattachement[] = $rattachement;

        return $this;
    }

    /**
     * Remove rattachement
     *
     * @param \App\Entity\Rattachement $rattachement
     */
    public function removeRattachement(\App\Entity\Rattachement $rattachement)
    {
        $this->rattachement->removeElement($rattachement);
    }

    /**
     * Get rattachement
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRattachement()
    {
        return $this->rattachement;
    }

    /**
     * Add sso
     *
     * @param \App\Entity\Sso $sso
     *
     * @return Individu
     */
    public function addSso(\App\Entity\Sso $sso)
    {
        $this->sso[] = $sso;

        return $this;
    }

    /**
     * Remove sso
     *
     * @param \App\Entity\Sso $sso
     */
    public function removeSso(\App\Entity\Sso $sso)
    {
        $this->sso->removeElement($sso);
    }

    /**
     * Get sso
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getSso()
    {
        return $this->sso;
    }



    /**
     * Add collaborateurVersion
     *
     * @param \App\Entity\CollaborateurVersion $collaborateurVersion
     *
     * @return Individu
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
     * Add expertise
     *
     * @param \App\Entity\Expertise $expertise
     *
     * @return Individu
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

    /**
     * Add journal
     *
     * @param \App\Entity\Journal $journal
     * @return Individu
     */
    public function addJournal(\App\Entity\Journal $journal)
    {
        if (!$this->journal->contains($journal)) {
            $this->journal[] = $journal;
        }

        return $this;
    }

    /**
     * Remove journal
     *
     * @param \App\Entity\Journal $journal
     */
    public function removeJournal(\App\Entity\Journal $journal)
    {
        $this->journal->removeElement($journal);
    }

    /**
      * Get journal
      *
      * @return \Doctrine\Common\Collections\Collection
      */
    public function getJournal()
    {
        return $this->journal;
    }

    ///////////////////////////////////////////////////////////////////////////

    public function getIDP()
    {
        return implode(',', $this->getSso()->toArray());
    }

    // TODO - Revoir cette fonction !!!!
    //        Suppression de Functions::warningMessage pas cool
    public function getEtablissement()
    {
        $server =  Request::createFromGlobals()->server;
        if ($server->has('REMOTE_USER') || $server->has('REDIRECT_REMOTE_USER')) {
            $eppn = '';
            if ($server->has('REMOTE_USER')) {
                $eppn =  $server->get('REMOTE_USER');
            }
            if ($server->has('REDIRECT_REMOTE_USER')) {
                $eppn =  $server->get('REDIRECT_REMOTE_USER');
            }
            preg_match('/^.+@(.+)$/', $$eppn, $matches);
            if ($matches[0] != null) {
                return $matches[0];
            }
            //else
            //    Functions::warningMessage('Individu::getEtablissements user '. $this .' a un EPPN bizarre');
        }
        return 'aucun établissement connu';
    }

    public function isExpert()
    {
        return $this->expert;
    }

    ////

    public function isPermanent()
    {
        $statut = $this->getStatut();
        if ($statut != null && $statut->isPermanent()) {
            return true;
        } else {
            return false;
        }
    }

    public function isFromLaboRegional()
    {
        $labo = $this->getLabo();
        if ($labo != null && $labo->isLaboRegional()) {
            return true;
        } else {
            return false;
        }
    }

    ///

    public function getEppn()
    {
        $ssos = $this->getSso();
        $eppn = [];
        foreach ($ssos as $sso) {
            $eppn[] =   $sso->getEppn();
        }
        return $eppn;
    }

    ///

    public function peutCreerProjets()
    {
        if ($this->isPermanent() && $this->isFromLaboRegional()) {
            return true;
        } else {
            return false;
        }
    }
}
