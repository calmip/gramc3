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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Expertise
 *
 * @ORM\Table(name="commentaireExpert", uniqueConstraints={@ORM\UniqueConstraint(columns={"annee", "id_expert"})}, indexes={@ORM\Index(columns={"id_expert"}), @ORM\Index(columns={"annee"})} )
 * @ORM\Entity(repositoryClass="App\Repository\CommentaireExpertRepository")
 *
 */
class CommentaireExpert
{
    /**
     * @var string
     *
     * Commentaire général sur les projets expertisés cette année
     *
     * @ORM\Column(name="commentaire", type="text", length=65535, nullable=true)
     */
    private $commentaire = "";

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="annee", type="integer", nullable=false)
     */
    private $annee;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="maj_stamp", type="datetime", nullable=false)
     */
    private $majStamp;

    /**
     * @var \App\Entity\Individu
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Individu")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_expert", referencedColumnName="id_individu")
     * })
     */
    private $expert;

    public function __toString(): string
    {
        return 'Commentaire '. $this->getId() . " par l'expert " . $this->getExpert();
    }

    /**
     * Set annee
     *
     * @param integer $annee
     *
     * @return comentaireExpert
     */
    public function setAnnee($annee): self
    {
        $this->annee = $annee;

        return $this;
    }

    /**
     * Get annee
     *
     * @return integer
     */
    public function getAnnee(): int
    {
        return $this->annee;
    }

    /**
     * Set commentaire
     *
     * @param string $commentaire
     *
     * @return comentaireExpert
     */
    public function setCommentaire($commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    /**
     * Get commentaire
     *
     * @return string
     */
    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set expert
     *
     * @param \App\Entity\Individu $idExpert
     *
     * @return comentaireExpert
     */
    public function setExpert(\App\Entity\Individu $expert = null): self
    {
        $this->expert = $expert;

        return $this;
    }

    /**
     * Get expert
     *
     * @return \App\Entity\Individu
     */
    public function getExpert(): \App\Entity\Individu
    {
        return $this->expert;
    }

    /**
     * Set majStamp
     *
     * @param \DateTime $majStamp
     *
     * @return comentaireExpert
     */
    public function setMajStamp($majStamp): self
    {
        $this->majStamp = $majStamp;

        return $this;
    }

    /**
     * Get majStamp
     *
     * @return \DateTime
     */
    public function getMajStamp(): \DateTime
    {
        return $this->majStamp;
    }
}
