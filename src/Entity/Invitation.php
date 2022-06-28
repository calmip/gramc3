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
 *  authors : Emmanuel Courcelle - C.N.R.S. - UMS 3667 - CALMIP
 *            Nicolas Renon - Université Paul Sabatier - CALMIP
 **/

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Invitation
 *
 * @ORM\Table(name="invitation",
 *            uniqueConstraints={@ORM\UniqueConstraint(name="clef", columns={"clef"}),
 *                               @ORM\UniqueConstraint(name="invit", columns={"id_inviting","id_invited"})
 *                              })
 * @ORM\Entity
 */
class Invitation
{

    /**
     * @var integer
     *
     * @ORM\Column(name="id_invitation", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $idInvitation;

    /**
     * @var string
     *
     * @ORM\Column(name="clef", type="string", length=50, nullable=false)
     */
    private $clef;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="creation_stamp", type="datetime", nullable=false)
     */
    private $creationStamp;

    /**
     * @var \App\Entity\Individu
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Individu")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_inviting", referencedColumnName="id_individu")
     * })
     */
    private $inviting;

    /**
     * @var \App\Entity\Individu
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Individu")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_invited", referencedColumnName="id_individu")
     * })
     */
    private $invited;

    /**
     * Get idInvitation
     *
     * @return integer
     */
    public function getIdInvitation()
    {
        return $this->idInvitation;
    }

    /**
     * Set inviting
     *
     * @param \App\Entity\Individu $inviting
     *
     * @return Invitation
     */
    public function setInviting(Individu $inviting)
    {
        $this->inviting = $inviting;

        return $this;
    }

    /**
     * Get inviting
     *
     * @return \App\Entity\Individu
     */
    public function getInviting()
    {
        return $this->inviting;
    }

   /**
     * Set invited
     *
     * @param \App\Entity\Individu $invited
     *
     * @return Invitation
     */
    public function setInvited(Individu $invited)
    {
        $this->invited = $invited;

        return $this;
    }

    /**
     * Get invited
     *
     * @return \App\Entity\Individu
     */
    public function getInvited()
    {
        return $this->invited;
    }

    /**
     * Set clef
     *
     * @param string $clef
     *
     * @return Invitation
     */
    public function setClef($clef)
    {
        $this->clef = $clef;

        return $this;
    }

    /**
     * Get clef
     *
     * @return string
     */
    public function getClef()
    {
        return $this->clef;
    }

    /**
     * Set creationStamp
     *
     * @param \DateTime $creationStamp
     *
     * @return Invitation
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
}
