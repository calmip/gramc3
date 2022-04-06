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

// src/Validator/Constraints/PagesNumberValidator.php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use App\GramcServices\ServiceJournal;

use App\Validator\Constraints\PagesNumber;
use App\Utils\Functions;

/**
 * @Annotation
 */
class PagesNumberValidator extends ConstraintValidator
{
    private $max_page_nb;
    private $sj;

    public function __construct($max_page_nb, ServiceJournal $sj)
    {
        $this->max_page_nb = $max_page_nb;
        $this->sj          = $sj;
    }

    public function validate($path, Constraint $constraint)
    {
        $max_page_nb = $this->max_page_nb;

        // pdfinfo est-il disponible ?
        $o=[];
        $c=0;
        exec("which pdfinfo",$o,$c);
        if ($c != 0)
        {
            $this->sj->errorMessage("pdfinfo pas utilisable !");
            $this->context->buildViolation($constraint->message3)->addViolation();
        }
        else
        {
            if ($path != null && ! empty($path) && $path != "")
            {
                $num = exec("pdfinfo " . $path . '| awk -e \'/^Pages:/ {print $2}\' ');
                $num = intval($num);
            }
            else
            {
                $this->sj->errorMessage("PagesNumberValidator: " . $path . " pas trouvé");
                $num  = 999999;
            }
            $this->sj->debugMessage("PagesNumberValidator: Le fichier PDF a " . $num . " pages");
    
            if ($num > $max_page_nb)
            {
                if ($max_page_nb == 1)
                {
                    $this->context->buildViolation($constraint->message1)
                        ->setParameter('{{ pages }}', $num)
                        ->addViolation();
                }
                else
                {
                    $this->context->buildViolation($constraint->message2)
                        ->setParameter('{{ pages }}', $num)
                        ->setParameter('{{ max_pages }}', $max_page_nb)
                        ->addViolation();
                }
                //Functions::debugMessage("PagesNumberValidator: violation ajoutée");
            }
        }
    }
}
