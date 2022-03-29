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
use App\GramcServices\Signal;

use App\GramcServices\ServiceNotifications;
use App\GramcServices\ServiceJournal;
use App\GramcServices\ServiceSessions;
use Doctrine\ORM\EntityManager;

/************************
 * Transition - Implémente une transition d'états
 *              C'est une classe abstraite, elle a deux fonctions abstraites:
 *              canExecute permet de définir des ACL: suivant la personne connectée la transition peut être exécutée ou pas.
 *                         retourne true/false
 *              execute essaie d'exécuter la transition:
 *                         retourne true  -> la transition est exécutée
 *                         retourne false -> il y a eu un pb (voir le journal) la transition ne s'est pas faite (ou s'est faite en partie)
 ************************/
abstract class Transition
{
    public const DEBUG = false;   // Activer - ou pas - le debug dans les transitions
                                  // ATTENTION ! Mettre à false pour la prod, sinon perte de perfs !
    public const FAST  = false;   // TODO - VIRER TOUTE PROPAGATION DANS canExecute !!!
                                  // Si FAST est à false, on appelle canExecute pour TOUS les objets
                                  // Si canExecute sur Session on appelle canExecute pour tous les versions,
                                  // Les versions appellent canExecute pour tous les projets etc.
                                  // Cela est lent et pas très utile et pratique

    abstract public function canExecute(object $object): bool;
    abstract public function execute(object $object): bool;

    // TODO - Les services devraient être des variables de classe !
    protected $sn = null;
    protected $sj = null;
    protected $ss = null;
    protected $em = null;

    /*********************************************
     * Le constructeur commun à toutes les classes dérivées
     * Les classes filles peuvent ajouter des paramètres après les 4 paramètres prédéfinis
     *****************************************************************************/
    public function __construct(private int $etat,
                                private int $signal,
                                private array $mail=[],
                                private bool $propage_signal = false) {}

    /******************************
     * Il est préférable de passer ces services après construction !
     * cf. la méthode addState de Workflow !
     *
     *******************************************************************/
    public function setServices(ServiceNotifications $sn, ServiceJournal $sj, ServiceSessions $ss, EntityManager $em): void
    {
        $this->sn = $sn;
        $this->sj = $sj;
        $this->ss = $ss;
        $this->em = $em;
    }

    /**********************************************
     * Les accesseurs pour accéder aux 4 variables privées
     * Il n'y a pas de mutateurs, donc l'état des variables est protégé
     *******************************************************************/
    protected function getEtat(): int
    {
        return $this->etat;
    }
    protected function getSignal(): int
    {
        return $this->signal;
    }
    protected function getMail(): array
    {
        return $this->mail;
    }
    protected function getPropageSignal(): bool
    {
        return $this->propage_signal;
    }

    /**********************************************
     * Pour imprimer (logs etc)
     *******************************************************************/
    public function __toString(): string
    {
        $reflect    = new \ReflectionClass($this);
        $output  = $reflect->getShortName().':etat='. Etat::getLibelle($this->etat);
        $output .= ', signal=' . Signal::getLibelle($this->signal);
        if ($this->mail != []) {
            $output .= ', mail=' .Functions::show($this->mail);
        }
        if ($this->propage_signal) {
            $output .= ', propagation ON';
        } else {
            $output .= ', propagation OFF';
        }
        return $output;
    }

    /********************************************************************
     * Changer l'état de l'objet passé en paramètre
     * NB - L'objet doit avoir une méthode appelée getObjectState
     *      (version, projet, session, rallonge)
     * TODO- $object devrait sans doute implémenter un interface mais comment ça se
     *       comporterait sachant que ces objets sont des Entitiy Synfony ?
     **************************************************************************************/
    protected function changeEtat(object $object): void
    {
        if (Transition::DEBUG) {
            $old_etat = $object->getObjectState();
            $object->setObjectState($this->getEtat());
            Functions::sauvegarder($object, $this->em);
            $reflect = new \ReflectionClass($object);
            $classe  = $reflect->getShortName();
            $this->sj->debugMessage(__FILE__ . ":" . __LINE__ . " $classe " . $object . " est passé de l'état " . $old_etat . " à " . $object->getObjectState() . " suite au signal " . $this->getSignal());

        } else {
            $object->setObjectState($this->getEtat());
            Functions::sauvegarder($object, $this->em);
        }
    }

    /*********************************************************************
     * Envoyer notification aux utilisateurs correspondant aux rôles de mail
     * cf. ServiceNotifications::mailUsers
     ************************************************************************/
    protected function sendNotif(object $object): void
    {
        foreach ($this->getMail() as $mail_role => $template) {
            $users = $this->sn->mailUsers([$mail_role], $object);
            $params['object'] = $object;
            $params['liste_mail_destinataires'] =   implode(',', $this->sn->usersToMail($users));
            $this->sn->sendMessage(
                'notification/'.$template.'-sujet.html.twig',
                'notification/'.$template.'-contenu.html.twig',
                $params,
                $users
            );
        }
    }
}
