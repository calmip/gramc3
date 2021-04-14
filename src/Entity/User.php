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
 * User
 *
 * @ORM\Table(name="user", uniqueConstraints={@ORM\UniqueConstraint(name="loginname", 
 *                         columns={"loginname"})},
 *                         indexes={@ORM\Index(name="loginname", columns={"loginname"})})
 * @ORM\Entity(repositoryClass="App\Repository\CollaborateurVersionRepository")
 */
class User
{
    /**
     * @var string
     *
     * @ORM\Column(name="loginname", type="string", nullable=true,length=20 )
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $loginname;

    /**
     * @var string
     *
     * @ORM\Column(name="password", type="string", nullable=true,length=200 )
     */
    private $password;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="pass_expiration", type="datetime", nullable=true)
     */
    private $passexpir;

    public function __toString()
    {
	    $output = '{';
	    $output .= 'loginname=' . $this->getLoginname() .'}';
	    return $output;
    }

    public function __construct(Individu $individu = null, Version $version = null )
    {
	    $this->password  = null;
	    $this->passexpir = null;
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
     * Set password
     *
     * @param string $password
     *
     * @return CollaborateurVersion
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Get password
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Set passexpir
     *
     * @param \DateTime $passexpir
     *
     * @return CollaborateurVersion
     */
    public function setPassexpir($passexpir)
    {
        $this->passexpir = $passexpir;

        return $this;
    }

    /**
     * Get passexpir
     *
     * @return \DateTime
     */
    public function getPassexpir()
    {
        return $this->passexpir;
    }
}
