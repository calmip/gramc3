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

namespace App\GramcServices\Workflow;

use App\Utils\Functions;
use App\GramcServices\Etat;
use App\GramcServices\ServiceNotifications;
use App\GramcServices\ServiceJournal;
use App\GramcServices\ServiceSessions;

use Doctrine\ORM\EntityManagerInterface;

/***********************************************************************************************
 * Workflow - Implémente des changements d'état entre objets
 *            Workflow est une classe abstraite, seules ses classes dérivées sont utilisables
 *            Il y a une classe dérivée par type d'objet (Projet, Version, Session, Rallonge)
 *            Workflow contient un tableau d'objets de type State décrivant les états de l'objet
 *
 **********************************************************************/
abstract class Workflow
{
    protected $states             = [];	// Contient les objets State encapsulés par ce workflow
    protected $workflowIdentifier = null;

    /*************************
     * Le constructeur = Sera surchargé par les classes dérivées afin
     * de mettre les transitions possibles
     ******************************************/
    public function __construct(protected ServiceNotifications $sn,
                                protected ServiceJournal $sj,
                                protected ServiceSessions $ss,
                                protected EntityManagerInterface $em)
    {
        $this->workflowIdentifier = get_class($this);
    }

    public function getIdentifier()
    {
        return $this->workflowIdentifier;
    }

    public function getWorkflowIdentifier()
    {
        $reflect = new \ReflectionClass($this);
        return $reflect->getShortName();
    }

    /***********************************************
     * Renvoie l'état de l'objet $object sous forme numérique (cf. Utils/Etat.php)
     ************************************************************************/
    protected function getObjectState(object $object): int
    {
        if ($object == null)
        {
            $this->sj->errorMessage(__METHOD__  . ":" . __LINE__ . " getObjectState on object null");
            return Etat::INVALIDE;
        }
        elseif (method_exists($object, 'getObjectState'))
        {
            return $object->getObjectState($this->workflowIdentifier);
        }
        else
        {
            $this->sj->errorMessage(__METHOD__ . ":" . __LINE__ . " getObjectState n'existe pas pour la class ". get_class($object));
            return Etat::INVALIDE;
        }
    }

    /*************************************************
     * Ajoute un état avec ses transitions - Appelé par les constructeurs des classes filles
     *
     * params: $stateConstant Un entier représentant l'état
     * params: $transition_array Tableau associatif représentant les transitions possibles
     *           key est un Entier représentant le signal (cf. Utils/Signal.php)
     *           val est un objet qui dérive de Transition
     *
     *******************************************************************/
    protected function addState(int $stateConstant, array $transition_array): self
    {
        $this->addStateObject($stateConstant, new State($stateConstant, $transition_array));

        foreach ($transition_array as $t) {
            $t->setServices($this->sn, $this->sj, $this->ss, $this->em);
        }
        return $this;
    }

    /*************************************************
     * Crée un objet State avec ses transitions, et l'ajoute au workflow
     * Fonction privée appelée par addState
     *
     * params: $stateConstant l'entier représentant l'état
     * params: $stateObject L'état (objet State)
     *
     ***/
    private function addStateObject(int $stateConstant, State $stateObject): void
    {
        $this->states[$stateConstant] = $stateObject;
    }

    /*************************************************
     * Renvoie l'objet $state associé à $stateConstant
     * Fonction privée appelée par execute
     *
     * params: $stateConstant Nombre entier représentant un état (cf. Utils/Etat.php)
     *
     * Return: le $state ou null s'il n'existe pas
     *************************************************/
    public function getState(int $stateConstant): State|null
    {
        if (isset($this->states[$stateConstant]))
        {
            return $this->states[$stateConstant];
        }
        else
        {
            return null;
        }
    }

    /**************************************************
     * Execute la transition $transition_code sur l'object $object
     *
     * params: $transition_code Un signal (entier), cf Utils/Signal.php
     *         $object Un objet sur lequel agit le workflow
     *
     * return: true si l'état est dans le workflow et si la transition est possible
     *         false sinon
     **************************************************************/
    public function execute(int $transition_code, object $object): bool
    {
        if ($object == null) {
            $this->sj->warningMessage(__METHOD__ ." on a null object dans " . $this->workflowIdentifier);
            return  false;
        }

        $state = $this->getObjectState($object);
        if ($this->hasState($state)) {
            return $this->getState($state)->execute($transition_code, $object);
        }

        else {
            $this->sj->warningMessage(__METHOD__ .  ":" . __LINE__ . " état " . Etat::getLibelle($state)
                    . "(" . $state . ") n'existe pas dans " . $this->getWorkflowIdentifier());
            return false;
        }
    }

    /***************************************************
     * Renvoie true/false selon que la transition est possible ou pas
     *
     * params: $transition_code Un signal (entier), cf Utils/Signal.php
     *         $object Un objet sur lequel agit le workflow
     *
     * return: true si l'état est dans le workflow et si la transition est possible
     *         false sinon
     **************************************************************/
    public function canExecute(int $transition_code, object $object)
    {
        $state = $this->getObjectState($object);
        if ($this->hasState($state)) {
            return $this->states[$state]->canExecute($transition_code, $object);
            
        } else {
            return false;
        }
    }

    public function hasState($state): bool
    {
        return isset($this->states[$state]);
    }

    public function __toString(): string
    {
        $output = "workflow(" . $this->getWorkflowIdentifier() . ":";
        foreach ($this->states as $state) {
            $output .= $state->__toString().',';
        }
        return $output . ")";
    }
}
