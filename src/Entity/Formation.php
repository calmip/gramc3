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
 * Formation
 *
 * @ORM\Table(name="formation")
 * @ORM\Entity
 */
class Formation
{
    /**
     * @var integer
     *
     * @ORM\Column(name="numero_form", type="integer", nullable=false)
     */
    private $numeroForm = '0';

    /**
     * @var string
     *
     * @ORM\Column(name="acro_form", type="string", length=15, nullable=false)
     */
    private $acroForm = '';

    /**
     * @var string
     *
     * @ORM\Column(name="nom_form", type="string", length=100, nullable=false)
     */
    private $nomForm = '';

    /**
     * @var integer
     *
     * @ORM\Column(name="id_labo", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $idForm;

    public function __toString()
    {
		return $this->getNomForm();
    }
    
    public function getId(){ return $this->getIdForm(); }
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->individu = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set numeroForm
     *
     * @param integer $numeroForm
     *
     * @return Formation
     */
    public function setNumeroForm($numeroForm)
    {
        $this->numeroForm = $numeroForm;

        return $this;
    }

    /**
     * Get numeroForm
     *
     * @return integer
     */
    public function getNumeroForm()
    {
        return $this->numeroForm;
    }

    /**
     * Set acroLabo
     *
     * @param string $acroForm
     *
     * @return Formation
     */
    public function setAcroForm($acroForm)
    {
        $this->acroForm = $acroForm;

        return $this;
    }

    /**
     * Get acroForm
     *
     * @return string
     */
    public function getAcroForm()
    {
        return $this->acroForm;
    }

    /**
     * Set nomForm
     *
     * @param string $nomForm
     *
     * @return Formation
     */
    public function setNomForm($nomForm)
    {
        $this->nomForm = $nomForm;

        return $this;
    }

    /**
     * Get nomForm
     *
     * @return string
     */
    public function getNomForm()
    {
        return $this->nomForm;
    }

    /**
     * Get idForm
     *
     * @return integer
     */
    public function getIdForm()
    {
        return $this->idForm;
    }
}
