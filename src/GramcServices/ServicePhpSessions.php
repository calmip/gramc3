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

namespace App\GramcServices;

use App\Entity\Param;
use App\GramcServices\ServiceJournal;
use Doctrine\ORM\EntityManagerInterface;

/*
 * Gestion des sessions php
 */
class ServicePhpSessions
{
    public function __construct(private EntityManagerInterface $em, private ServiceJournal $sj) {}

    /*******
     *
     * Supprime TOUS les fichiers du répertoire de sessions
     * Tant pis s'il y avait des fichiers autres que des fichiers de session symfony
     * (ils n'ont rien à faire ici de toute manière)
     *
     * NOTE - Il s'agit d'une fonction statique car elle est appelée par
     *        App\GramcServices\Workflow\Session\SessionTransition, qui n'EST PAS un service, du coup elle n'a
     *        pas accès au service ServicePhpSessions.
     *
     *****************************************/
    static public function clearPhpSessions(): bool
    {
        $dir = session_save_path();
        $scan = scandir($dir);
        $result = true;
        foreach ($scan as $filename) {
            if ($filename != '.' && $filename != '..') {
                $path = $dir . '/' . $filename;
                if (@unlink($path)==false) {
                    Functions::errorMessage(__METHOD__ . ':' . __LINE__ . " Le fichier $path n'a pas pu être supprimé !");
                    $result = false;
                }
            }
        }
        return $result;
    }

    /*******************************
     * 
     * Renvoie un tableau avec la liste des connexions actives
     *
     * TODO - On fait du bas niveau ici peut-être y a-t-il
     *        des moyens plus symfoniques de faire la même chose !
     * 
     **********************************************************/
    public function getConnexions(): array
    {
        $em = $this->em;
        $sj = $this->sj;
        
        $connexions = [];
        $dir = session_save_path();
        $sj->debugMessage(__METHOD__ . ':' . __LINE__ . "session directory = " . $dir);

        $scan = scandir($dir);

        $save = $_SESSION;

        $time = time();
        foreach ($scan as $filename) {
            if ($filename != '.' && $filename != '..') {
                $atime = fileatime($dir . '/' . $filename);
                $mtime = filemtime($dir . '/' . $filename);
                $ctime = filectime($dir . '/' . $filename);

                $diff  = intval(($time - $mtime) / 60);
                $min   = $diff % 60;
                $heures= intVal($diff/60);
                $contents = file_get_contents($dir . '/' . $filename);
                session_decode($contents);
                if (! array_key_exists('_sf2_attributes', $_SESSION)) {
                    $sj->errorMessage(__METHOD__ . ':' . __LINE__ . " Une session autre que gramc3 !");
                }
                else
                {
                    // Utilisateur du gui
                    if (array_key_exists('_security_global_security_context', $_SESSION['_sf2_attributes']))
                    {
                        $secu_data = unserialize($_SESSION['_sf2_attributes']['_security_global_security_context']);
                        $individu = $secu_data->getUser();
                        $rest_individu = null;
                    }

                    // Api REST - firewall calc - cf. security.yaml
                    elseif (array_key_exists('_security_calc', $_SESSION['_sf2_attributes']))
                    {
                        $secu_data = unserialize($_SESSION['_sf2_attributes']['_security_calc']);
                        //dd($secu_data);
                        $individu = null;
                        $rest_individu = $secu_data->getUser();
                        //dd($rest_individu);
                    }
                    else
                    {
                        $individu = null;
                        $rest_individu = null;                        
                    }
                    if ($individu == null && $rest_individu == null) {
                        $sj->errorMessage(__METHOD__ . ':' . __LINE__ . " Problème d'individu ");
                    //dd($secu_data);
                    }
                    else
                    {
                        $connexions[] = [ 'user' => $individu, 'rest_user' => $rest_individu, 'minutes' => $min, 'heures' => $heures ];
                    }
                }
            }
        }
        $_SESSION = $save;
        return $connexions;
    }
}
