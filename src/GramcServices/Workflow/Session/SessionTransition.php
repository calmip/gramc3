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

namespace App\GramcServices\Workflow\Session;

use App\GramcServices\Workflow\Transition;
use App\GramcServices\Workflow\Projet\ProjetWorkflow;
use App\GramcServices\Workflow\Version\VersionWorkflow;

use App\GramcServices\ServicePhpSessions;

use App\Utils\Functions;

use App\GramcServices\Etat;
use App\GramcServices\Signal;
use App\Entity\Session;
use App\Entity\Projet;
use App\Entity\Version;

class SessionTransition extends Transition
{
    public function canExecute(object $session): bool
    {
        $session instanceof Session || throw new \InvalidArgumentException();
        $rtn = true;
        if (Transition::FAST == false && $this->getPropageSignal()) {
            // Propagation vers les versions
            $versions = $this->em->getRepository(Version::class)->findBy(['session' => $session]);
            $workflow = new VersionWorkflow($this->sn, $this->sj, $this->ss, $this->em);
            if ($versions == null && Transition::DEBUG) {
                $this->sj->debugMessage(__METHOD__ . ':' . __LINE__ . " aucune version pour la session " . $session);
            }

            foreach ($versions as $version) {
                $output = $workflow->canExecute($this->getSignal(), $version);
                $rtn = Functions::merge_return($rtn, $output);
                if ($output != true && Transition::DEBUG) {
                    $this->sj->debugMessage(__METHOD__ . ':' . __LINE__ . " Version " . $version . "  ne passe pas en état "
                        . Etat::getLibelle($version->getEtatVersion()) . " signal = " . signal::getLibelle($this->getSignal()));
                }
            }
        }
        return $rtn;
    }

    public function execute(object $session): bool
    {
        $session instanceof Session || throw new \InvalidArgumentException();
        $rtn = true;

        // Si on ne peut pas remettre toutes les sessions php à zéro, renvoie false
        // La transition n'a pas eu lieu
        // Cela est une sécurité afin de s'assurer que personne ne reste connecté, ne sachant pas que la session
        // a changé d'état !
        if (ServicePhpSessions::clearPhpSessions()==false) {
            $rtn = false;
            $this->sj->errorMessage(__METHOD__ . ':' . __LINE__ . " clear_phpSessions renvoie false");
            return $rtn;
        } else {
            if (Transition::DEBUG) {
                $this->sj->debugMessage(__FILE__ . ":" . __LINE__ . " nettoyage des sessions php");
            }
        }

        if ($this->getSignal() == null) {
            $this->sj->ErrorMessage(__FILE__ . ":" . __LINE__ . " signal null");
            return false;
        }

        $versions = $this->em->getRepository(Version::class)->findBy(['session' => $session]);
        if ($this->getPropageSignal()) {
            if (Transition::DEBUG) {
                $this->sj->debugMessage(__FILE__ . ":" . __LINE__ . " propagation du signal ".$this->getSignal()." à ".count($versions)." versions");
            }

            $workflow = new VersionWorkflow($this->sn, $this->sj, $this->ss, $this->em);

            // Propage le signal à toutes les versions qui dépendent de la session
            foreach ($versions as $version) {
                $output = $workflow->execute($this->getSignal(), $version);
                $rtn = Functions::merge_return($rtn, $output);
            }
        }

        // Change l'état de la session
        $this->changeEtat($session);

        return $rtn;
    }
}
