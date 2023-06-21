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

use App\GramcServices\ServiceJournal;
use App\Validator\Constraints\Clessh;
//use App\Utils\Functions;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validateur de clés ssh
 * On calcule l'empreinte de la clé par keygen -l -f
 * Si l'empreinte est 2048 SHA256:owi8U7FsTjIs4HLga09zfgDd7TIpDgmcS7VYFI0qUlk manu@hplab2 (RSA)
 * On considère que l'ago est 2048-RSA
 * On doit retrouver cette chaine de caractères dans le paramètre clessh_algos
 */
class ClesshValidator extends ConstraintValidator
{
    public function __construct(private $clessh_algos, private ServiceJournal $sj) {}

    public function validate($pub, Constraint $constraint)
    {
        $sj = $this->sj;
        
        $clessh_algos = $this->clessh_algos;

        // ssh-keygen est-il disponible ?
        $o = [];
        $c = 0;
        exec("/bin/bash -c 'which ssh-keygen'",$o,$c);
        if ($c != 0)
        {
            $sj->errorMessage("ssh-keygen n'est PAS utilisable ! - code $c");
            $this->context->buildViolation($constraint->message3)->addViolation();
        }

        $o = [];
        exec("/bin/bash -c 'ssh-keygen -l -f <(echo $pub)' 2>&1",$o,$c);
        //dd("ssh-keygen -l -f <(echo $pub)", $o, $c);

        // ssh-keygen a renvoyé un code d'erreur != 0
        if ($c != 0)
        {
            $msg = " ssh-keygen a renvoyé le code $c";
            if (count($o) > 0) $msg .= $o[0];
            $sj->errorMessage(__METHOD__ .":" . __LINE__ . $msg);
            $this->context->buildViolation($constraint->message3)->addViolation();
            return;
        }

        // Il devrait y avoir quelque chose dans $o !
        if (count($o) == 0)
        {
            $msg = " ssh-keygen n'a rien renvoyé - code de retour 0";
            $sj->errorMessage(__METHOD__ .":" . __LINE__ . $msg);
            $this->context->buildViolation($constraint->message3)->addViolation();
            return;
        }
        
        // ssh-keygen a validé la clé
        $empreinte = explode(" ", $o[0]);

        // Il devrait y avoir au moins trois champs
        // Le dernier champ devrait avoir au moins 3 octets
        if (count($empreinte) < 3 || strlen($empreinte[2]) < 3)
        {
            $msg = " ssh-keygen a renvoyé un truc zarbi: $o[0] - code de retour 0"; 
            $sj->errorMessage(__METHOD__ .":" . __LINE__ . $msg);
            $this->context->buildViolation($constraint->message3)->addViolation();
            return;
        }

        // (RSA) -> RSA
        $a = $empreinte[count($empreinte)-1];
        $algo = substr($a,1,strlen($a)-2);
        $algo .= '-' . $empreinte[0];

        // L'algo est-il accepté ? Si oui c'est validé et on retourne !
        if (in_array($algo,$clessh_algos))
        {
            return;
        }
        else
        {
            $msg = " clessh: algo $algo, non supporté";
            $sj->errorMessage(__METHOD__ .":" . __LINE__ . $msg);
            $this->context->buildViolation($constraint->message1)->addViolation();
            return;
        }
    }
}
