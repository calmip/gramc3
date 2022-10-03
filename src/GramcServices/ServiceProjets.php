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

use App\GramcServices\Etat;
use App\Entity\Projet;
use App\Entity\Version;
use App\Entity\Session;
use App\Entity\Compta;
use App\Entity\Individu;
use App\Entity\RapportActivite;
use App\Entity\Rattachement;

// Pour la suppression des projets RGPD
use App\Entity\CollaborateurVersion;
use App\Entity\Expertise;
use App\Entity\Sso;
use App\Entity\CompteActivation;

//use App\GramcServices\ServiceJournal;

use App\Utils\Functions;

//use Symfony\Bridge\Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Doctrine\ORM\EntityManagerInterface;

class ServiceProjets
{
    private $token = null;

    public function __construct(
        private $prj_prefix,
        private $ressources_conso_group,
        private $signature_directory,
        private $rapport_directory,
        private $fig_directory,
        private $dfct_directory,
        private GramcDate $grdt,
        private ServiceVersions $sv,
        private ServiceSessions $ss,
        private ServiceJournal $sj,
        private LoggerInterface $log,
        private AuthorizationCheckerInterface $sac,
        private TokenStorageInterface $tok,
        private EntityManagerInterface $em
    ) {
        $this->token = $tok->getToken();
    }


    /**************
     * Calcule le prochain id de projet, à partir des projets existants
     *
     * Params: $anne   L'année considérée
     *         $type   Le type de projet
     *
     *
     * Return: Le nouvel id, ou null en cas d'erreur
     *
     ***************/
    public function NextProjetId($annee, $type): string
    {
        if (intval($annee) >= 2000) {
            $annee = $annee - 2000;
        }

        $prefix = $this->prj_prefix[$type];
        $numero = $this->em->getRepository(Projet::class)->getLastNumProjet($annee, $prefix);
        //$this->sj->debugMessage("$annee -> $type -> $numero");
        //$this->sj->debugMessage(print_r($this->prj_prefix,true));

        $id = $prefix . $annee . sprintf("%'.03d", $numero+1);

        //$this->sj->debugMessage("$prefix $numero $id");
        return $id;
    }

    /***********
    * Renvoie le méta état du projet passé en paramètre, c'est-à-dire
    * un "état" qui n'est pas utilisé dans les workflows mais qui peut être
    * affiché et qui a du sens pour les utilisateurs
    ************************************************************/
    public function getMetaEtat(Projet $p): string
    {
        $etat_projet = $p->getEtatProjet();

        // Projet terminé
        if ($etat_projet == Etat::TERMINE) {
            return 'TERMINE';
        }

        // Projet non renouvelable:
        //    - Projet test   = toujours non renouvelable
        //    - Autres projets= sera bientôt terminé car expert a dit "refusé"
        //
        if ($etat_projet == Etat::NON_RENOUVELABLE && $p->getTypeProjet() != Projet::PROJET_TEST) {
            return 'REFUSE';
        }

        $veract  = $this->versionActive($p);
        $version = $p->derniereVersion();
        // Ne doit pas arriver: un projet a toujours une dernière version !
        // Peut-être la BD est-elle en rade donc on utilise le logger
        if ($version == null) {
            $this->log->error(__METHOD__ . ":" . __LINE__ . "Incohérence dans la BD: le projet " .
                                            $p->getIdProjet() . " version active: $veract n'a PAS de dernière version !");
            return 'STANDBY';
        }
        $etat_version   =   $version->getEtatVersion();

        if ($etat_version ==  Etat::EDITION_DEMANDE) {
            return 'EDITION';
        } elseif ($etat_version ==  Etat::EDITION_EXPERTISE) {
            return 'EXPERTISE';
        } elseif ($etat_version ==  Etat::EDITION_TEST) {
            return 'EDITION';
        } elseif ($etat_version ==  Etat::EXPERTISE_TEST) {
            return 'EXPERTISE';
        } elseif ($etat_version ==  Etat::ACTIF || $etat_version == Etat::ACTIF_TEST) {
            // Permet d'afficher un signe particulier pour les projets non renouvelés en période de demande pour une session A
            $session = $this->ss->getSessionCourante();
            if ($session->getEtatSession() == Etat::EDITION_DEMANDE &&  $session->getLibelleTypeSession() === 'A') {
                return 'NONRENOUVELE';
            } // Non renouvelé
            else {
                return 'ACCEPTE';
            } // Projet ou rallonge accepté par le comité d'attribution
        } elseif ($etat_version == Etat::ACTIF_TEST) {
            return 'ACCEPTE';
        } // projet-test non renouvelable
        elseif ($etat_version == Etat::EN_ATTENTE) {
            return 'ACCEPTE';
        } elseif ($etat_version == Etat::TERMINE) {
            if ($p->getNepasterminer()) {
                return 'AGARDER';
            } else {
                return 'STANDBY';
            }
        } elseif ($veract       == null) {
            return 'STANDBY';
        }
    }

    /**
      * Liste tous les projets qui ont une version cette annee
      *       Utilise par ProjetController et AdminuxController, et aussi par StatistiquesController
      *
      * Param : $annee
      *         $isRecupPrintemps (true/false, def=false) -> Calcule les heures récupérables au printemps
      *         $isRecupAutomne (true/false, def=false)   -> Calcule les heures récupérables à l'Automne
      *
      * Return: [ $projets, $total ] Un tableau de tableaux pour les projets, et les données consolidées
      *
      * NOTE - Si un projet a DEUX VERSIONS et change de responsable, donc de laboratoire, au cours de l'année,
      *        on affiche les données de la VERSION A (donc celles du début d'année)
      *        Cela peut conduire à une erreur à la marge dans les statistiques
      *
      */

    // Ajoute les champs 'c','g','q', 'cp', 'stk' au tableau $p (pour projetsParAnnee)
    // 'c' -> conso TOTALE (cpu + gpu consolidé)
    // 'g' -> conso GPU normalisée
    // 'q' -> quota
    // 'cp' -> Conso totale en %age du quota
    // 'stk'-> Quota de stockage en Ko
    private function ppa_conso(&$p, &$annee)
    {
        $conso_cpu = $this->getConsoRessource($p['p'], 'cpu', $annee);
        $conso_gpu = $this->getConsoRessource($p['p'], 'gpu', $annee);
        $conso_stk = $this->getConsoRessource($p['p'], 'work_space', $annee);
        $p['c'] = $conso_cpu[0] + $conso_gpu[0];
        $p['q'] = $conso_cpu[1];
        $p['g'] = $conso_gpu[0];
        $p['cp'] = ($p['q']>0) ? (100.0 * $p['c']) / $p['q'] : 0;
        $p['stk_c'] = $conso_stk[0];
        $p['stk_q'] = $conso_stk[1];
    }

    /***********
     * Renvoie la liste des projets par année -
     * $annee      = Année (4 charactères - ex. 2022)
     * $isRecup... = Pour gérer les heures de récupération
     * $sess_lbl   = Défaut 'AB' On ramène les projets de toute l'année, sessions A et B confondues
     *               'A' Seulement session 'A'
     *               'B' Seulement session 'B'
     *
     * Return: un tableau de deux tableaux:
     *         - Le tableau des projets
     *         - Le tableau des données consolidées
     *            
     *
     ********************/
    public function projetsParAnnee($annee, $isRecupPrintemps=false, $isRecupAutomne=false, string $sess_lbl = 'AB'): array
    {
        $em = $this->em;
        $ss = $this->ss;

        // une version dont l'état se retrouve dans ce tableau ne sera pas comptée dans les données consolidées
        // (nombre de projets, heures demandées etc)
        $a_filtrer = [ Etat::CREE_ATTENTE, Etat::EDITION_DEMANDE, Etat::ANNULE ];

        // Données consolidées - Projets de session
        $total = [];
        $total['sess'] = [];
        $total['fil'] = [];

        //$total['prj']         = 0;  // Nombre de projets (A ou B) (A JETER)
        //$total['demHeuresA']  = 0;  // Heures demandées en A (A JETER)
        //$total['attrHeuresA'] = 0;  // Heures attribuées en A (A JETER)
        //$total['demHeuresB']  = 0;  // Heures demandées en B (A JETER))
        $total['sess']['prj'] = 0;  // Nombre de projets (A ou B)
        $total['penalitesA']  = 0;  // Pénalités de printemps (sous-consommation entre Janvier et Juin) (A JETER)
        $total['penalitesB']  = 0;  // Pénalités d'Automne (sous-consommation l'été) (A JETER)
        $total['rall']        = 0;  // Nombre de rallonges (A JETER))
        //$total['demHeuresR']  = 0;  // Heures demandées dans des rallonges (A JETER)
        //$total['attrHeuresR'] = 0;  // Heures attribuées dans des rallonges (A JETER)
        //$total['consoHeuresP']= 0;           // Heures consommées - A JETER ASAP

        $total['sess']['demHeuresA']  = 0;  // Heures demandées en A
        $total['sess']['attrHeuresA'] = 0;  // Heures attribuées en A
        $total['sess']['demHeuresB']  = 0;  // Heures demandées en B
        $total['sess']['attrHeuresB'] = 0;  // Heures attribuées en B

        // Données consolidées - projets tests
        // Plus utilisées à partir de 2022
        $total['prjTest']     = 0;  // Nombre de projets tests
        $total['demHeuresT']  = 0;  // Heures demandées dans des projets tests
        $total['attrHeuresT'] = 0;  // Heures attribuées dans des projets tests

        // Données consolidées - projets fil de l'eau
        $total['fil']['prj'] = 0;  // Nombre de projets (A ou B)
        $total['fil']['demHeuresA']  = 0;  // Heures demandées en A
        $total['fil']['attrHeuresA'] = 0;  // Heures attribuées en A
        $total['fil']['demHeuresB']  = 0;  // Heures demandées en B
        $total['fil']['attrHeuresB'] = 0;  // Heures attribuées en B

        // Rallonges
        $total['sess']['rall']        = 0;  // Nombre de rallonges
        $total['sess']['demHeuresR']  = 0;  // Heures demandées dans des rallonges
        $total['sess']['attrHeuresR'] = 0;  // Heures attribuées dans des rallonges
        
        // Rallonges - Projets Fil de l'eau
        $total['fil']['rall']        = 0;  // Nombre de rallonges
        $total['fil']['demHeuresR']  = 0;  // Heures demandées dans des rallonges
        $total['fil']['attrHeuresR'] = 0;  // Heures attribuées dans des rallonges

        // Données consolidées - globales
        $total['demHeuresP']  = 0;  // Nombre d'heures demandées: A+B+Rallonges, sess+fil
        $total['attrHeuresP'] = 0;  // Heures attribuées aux Projets: A+B+Rallonges-Pénalité, sess+fil
        $total['recupHeuresP']= 0;  // Heures récupérables

        // Conso - Projets Fil de l'eau
        $total['fil']['consoHeuresCPU']= 0;  // Heures consommées - cpu
        $total['fil']['consoHeuresGPU']= 0;  // Heures consommées - gpu

        // Conso - Projets de session
        $total['sess']['consoHeuresCPU']= 0;  // Heures consommées - cpu
        $total['sess']['consoHeuresGPU']= 0;  // Heures consommées - gpu

        // Stockage
        $total['sess']['sondVolDonnPerm']= 0; // Demandes de stockage
        $total['sess']['consoVolDonnPerm']= 0; // Demandes de stockage: occupation
        $total['sess']['quotaVolDonnPerm']= 0; // Quota de stockage
        $total['fil']['sondVolDonnPerm']= 0; // Demandes de stockage
        $total['fil']['consoVolDonnPerm']= 0; // Demandes de stockage: occupation
        $total['fil']['quotaVolDonnPerm']= 0; // Quota de stockage
        
        // Les rattachements
        $rattachements = $em->getRepository(Rattachement::class)->findAll();
        if ($rattachements == null) {
            $rattachements = [];
        }

        $statsRattachements = [];
        foreach ($rattachements as $rattachement) {
            $statsRattachements[$rattachement->getLibelleRattachement()]    =   0;
        }

        // $annee = 2017, 2018, etc. (4 caractères)
        $session_id_A = substr($annee, 2, 2) . 'A';
        $session_id_B = substr($annee, 2, 2) . 'B';
        $session_A = $this->em->getRepository(Session::class)->findOneBy(['idSession' => $session_id_A ]);
        $session_B = $this->em->getRepository(Session::class)->findOneBy(['idSession' => $session_id_B ]);

        if ( strpos($sess_lbl, 'A') !== false )
        {
            $versions_A= $this->em->getRepository(Version::class)->findBy(['session' => $session_A ]);
        }
        else
        {
            $versions_A = [];
        }
        if ( strpos($sess_lbl, 'B') !== false )
        {
            $versions_B= $this->em->getRepository(Version::class)->findBy(['session' => $session_B ]);
        }
        else
        {
            $versions_B = [];
        }

        // $mois est utilisé pour calculer les éventuelles pénalités d'été
        // Si on n'est pas à l'année courante, on le met à 0 donc elles ne seront jamais calculées
        $annee_courante = $this->grdt->showYear();
        if ($annee == $annee_courante) {
            $mois = $this->grdt->showMonth();
        } else {
            $mois = -1;
        }

        $projets= [];

        // Boucle sur les versions de la session A
        foreach ($versions_A as $v) {
            if ($v->getTypeVersion() == 1 || $v->getTypeVersion() == null)
            {
                $type = 'sess';
            }
            else
            {
                $type = 'fil';
            }
            $p_id = $v->getProjet()->getIdProjet();
            $p = [];
            $p['p']        = $v->getProjet();
            $p['metaetat'] = $this->getMetaEtat($p['p']);
            $p['va']       = $v;
            $p['penal_a']  = $v->getPenalHeures();
            $p['labo']     = $v->getLabo();
            $p['resp']     = $v->getResponsable();

            // Ces champs seront renseignés en session B
            $p['vb']      = null;
            $p['penal_b'] = 0;
            $p['attrete'] = 0;
            $p['consoete']= 0;

            $rallonges = $v->getRallonge();
            $p['r'] = 0;
            $p['attrib']     = $v->getAttrHeures();
            $p['attrib']    -= $v->getPenalHeures();
            foreach ($rallonges as $r) {
                // filtrage
                if (! in_array($v->getEtatVersion(), $a_filtrer))
                {
                    $total['rall']        += 1;

                    //if ($type == 'sess')
                    //{
                    //    $total['demHeuresR']  += $r->getDemHeures();    // provisoire
                    //    $total['attrHeuresR'] += $r->getAttrHeures();   // provisoire
                    //}

                    $total[$type]['rall']        += 1;
                    $total[$type]['demHeuresR']  += $r->getDemHeures();
                    $total[$type]['attrHeuresR'] += $r->getAttrHeures();
                    $total['demHeuresP']  += $r->getDemHeures();
                    $total['attrHeuresP'] += $r->getAttrHeures();
                    
                    $p['r']               += $r->getAttrHeures();
                    $p['attrib']          += $r->getAttrHeures();
                }
            }

            // filtrage
            if (! in_array($v->getEtatVersion(), $a_filtrer))
            {
                $total[$type]['prj'] += 1;
                $total['demHeuresP']  += $v->getDemHeures();
                $total['attrHeuresP'] += $v->getAttrHeures();
                $total[$type]['demHeuresA']  += $v->getDemHeures();
                $total[$type]['attrHeuresA'] += $v->getAttrHeures();
                $total['penalitesA']  += $v->getPenalHeures();
                $total['attrHeuresP'] -= $v->getPenalHeures();

            }
            if ($v->getProjet()->isProjetTest()) {
                // filtrage
                if (! in_array($v->getEtatVersion(), $a_filtrer))
                {
                    $total['prjTest']     += 1;
                    $total['demHeuresT']  += $v->getDemHeures();
                    $total['attrHeuresT'] += $v->getAttrHeures();
                }
            }

            // La conso
            $this->ppa_conso($p, $annee);
            
            //$total['consoHeuresP'] += $p['c'];
            $total[$type]['consoHeuresCPU'] += $p['c'] - $p['g'];
            $total[$type]['consoHeuresGPU'] += $p['g'];
            $total[$type]['sondVolDonnPerm']+= intval($v->getSondVolDonnPerm());
            $total[$type]['consoVolDonnPerm']+= $p['stk_c'];
            $total[$type]['quotaVolDonnPerm']+= $p['stk_q'];
            
            // Les rattachements
            $ratt = $v->getPrjRattachement();
            if (! empty($ratt)) {
                $statsRattachements[$ratt->getLibelleRattachement()] += $p['c'];
            }

            // Récup de Printemps
            if ($isRecupPrintemps==true) {
                $p['recuperable']       = $this->ss->calc_recup_heures_printemps($p['c'], intval($p['attrib'])+intval($p['r']));
                $total['recupHeuresP'] += ($v->getPenalHeures()==0) ? $p['recuperable'] : 0;
            } else {
                $p['recuperable'] = 0;
            }

            $projets[$p_id] = $p;
        }

        // Boucle sur les versions de la session B
        foreach ($versions_B as $v) {

            
            if ($v->getTypeVersion() == 1 || $v->getTypeVersion() == null)
            {
                $type = 'sess';
            }
            else
            {
                $type = 'fil';
            }
            $p_id = $v->getProjet()->getIdProjet();
            if (isset($projets[$p_id])) {
                $p = $projets[$p_id];
            } else {
                $total[$type]['prj'] += 1;
                $p = [];
                $p['p']           = $v->getProjet();
                $p['metaetat']    = $this->getMetaEtat($p['p']);
                $p['va']          = null;
                $p['penal_a']     = 0;
                $p['recuperable'] = 0;
                $p['r']      = 0;
                $p['attrib'] = 0;
                $p['labo']   = $v->getLabo();         // Si version A et B on choisit le labo
                $p['resp']   = $v->getResponsable();  // et le responsable de la version B (pas obligatoirement le même)
            }
            $p['vb']      = $v;
            $rallonges    = $v->getRallonge();
            foreach ($rallonges as $r) {
                // filtrage
                if (! in_array($v->getEtatVersion(), $a_filtrer))
                {
                    $total[$type]['rall']        += 1;
                    $total[$type]['demHeuresR']  += $r->getDemHeures();
                    $total['demHeuresP']  += $r->getDemHeures();
                    $total['attrHeuresP'] += $r->getAttrHeures();
                    $total[$type]['attrHeuresR'] += $r->getAttrHeures();
                    $p['r']               += $r->getAttrHeures();
                    $p['attrib']          += $r->getAttrHeures();
                }
            }

            // S'il y a eu une attrib en session A, on verifie que la demande B ne soit pas toomuch
            if (!empty($p['va'])) {
                $p['toomuch'] = $this->sv->is_demande_toomuch($p['va']->getAttrHeures(), $p['vb']->getDemHeures());
            } else {
                $p['toomuch'] = false;
            }

            $p['attrib'] += $v->getAttrHeures();

            // Pénalités déja appliquée en session B
            $p['penal_b'] = $v->getPenalHeures();
            $p['attrib'] -= $p['penal_b'];

            $total['demHeuresP']  += $v->getDemHeures();
            $total['attrHeuresP'] += $v->getAttrHeures();
            $total[$type]['demHeuresB']  += $v->getDemHeures();
            $total[$type]['attrHeuresB'] += $v->getAttrHeures();
            $total['penalitesB']  += $v->getPenalHeures();
            $total['attrHeuresP'] -= $v->getPenalHeures();

            // La conso (attention à ne pas compter deux fois la conso pour les projets déjà entamés !)
            //
            $this->ppa_conso($p, $annee);

            // Les rattachements: seulement pour les Nouveaux projets également
            //                    NB - Un projet qui change son rattachement entre A et B
            //                         à partir de son état en session A !!!
            //                         Ce cas exceptionnel est ignoré.
            
            // Ici également les quotas de stockage, le stockage à chaque session mais ça ne se cumule pas
            // TODO - Si un projet demande plus de stockage en B qu'en A cela ne sera pas pris en compte !!!
            //        Si un projet est renouvelé sa conso sera celle de la session A !
            if ($this->sv->isNouvelle($v))
            {
                //$total['consoHeuresP'] += $p['c'];
                $total[$type]['consoHeuresCPU'] += $p['c'] - $p['g'];
                $total[$type]['consoHeuresGPU'] += $p['g'];
                $total[$type]['sondVolDonnPerm']+= intval($v->getSondVolDonnPerm());
                $total[$type]['consoVolDonnPerm']+= $p['stk_c'];
                $total[$type]['quotaVolDonnPerm']+= $p['stk_q'];

                $ratt = $v->getPrjRattachement();
                if (! empty($ratt)) {
                    $statsRattachements[$ratt->getLibelleRattachement()] += $p['c'];
                }
            }

            // Pour le calcul des pénalités d'Automne
            $p['attrete'] = $v->getAttrHeuresEte();

            // Penalites d'automne. Elles dépendent de la consommation des mois de Juillet et d'Août
            if ($isRecupAutomne==true) {
                $d = $annee_courante.'-07-01';
                $f = $annee_courante.'-09-01';
                $p['consoete']          = $this->getConsoIntervalle($v->getProjet(), ['cpu','gpu'], [$d,$f]);
                $p['recuperable']       = $ss->calc_recup_heures_automne($p['consoete'], $p['attrete']);
                $total['recupHeuresP'] += ($v->getPenalHeures()==0) ? $p['recuperable'] : 0;

            // Si recuPrintemps est à true, 'recuperable' est déjà calculé, ne pas y toucher
            // NB - Oui il y a des gens qui ne consomment pas en A et qui demandent des heures en B !
            } elseif ($isRecupPrintemps==false) {
                $p['recuperable'] = 0;
            }

            $projets[$p_id] = $p;
        }

        $total['rattachements'] = $statsRattachements;
        return [$projets,$total];
    }

    /*
     * Appelle projetsParAnnee et renvoie les tableaux suivants, indexés par le critère
     *
     *    - Nombre de projets
     *    - Heures demandées
     *    - Heures attribuées
     *    - Heures consommées
     *    - Liste des projets
     *
     * $annee   = Année
     * $sess_lbl= 'A', 'B', 'AB'
     * $critere = Un nom de getter de Version permettant de consolider partiellement les données
     *            Le getter renverra un acronyme (laboratoire, établissement etc)
     *            (ex = getAcroLaboratoire())
     *
     * Fonction utilisée pour les statistiques et pour le bilan annuel
     *
     * NOTE - Si $sess_lbl vaut A ou B on ne renvoie PAS les projets fil de l'eau
     *        Si $sess_lbl vaut AB on renvoie AUSSI les projets fil de l'eau
     *        On ne tient PAS compte des versions en état EDITION_DEMANDE
     *

     */
    public function projetsParCritere($annee, $sess_lbl, $critere): array
    {
        $sv = $this->sv;
        
        $projets = $this->projetsParAnnee($annee, false, false, $sess_lbl)[0];

        // On filtre complètement les projets qui ont déjà été partiellement filtrés dans projetsParAnnee
        $a_filtrer = [ Etat::CREE_ATTENTE, Etat::EDITION_DEMANDE, Etat::ANNULE];

        
        // La liste des acronymes
        $acros       = [];

        // Ces quatre tableaux sont indexés par l'acronyme ($acro)
        $num_projets   = [];
        $num_projets_n = [];    // nouveaux projets
        $num_projets_r = [];    // renouvellements
        $liste_projets = [];
        $dem_heures    = [];
        $attr_heures   = [];
        $conso         = [];
        $conso_gpu     = [];

        // Remplissage des quatre tableaux précédents
        foreach ($projets as $p) {
            $v    = ($p['vb']==null) ? $p['va'] : $p['vb'];

            // Filtrage !
            if (in_array($v->getEtatVersion(), $a_filtrer)) continue;
            if ($sess_lbl != 'AB' && $v->getTypeVersion() != 1)
            {
                continue;
            }
            
            $acro = $v -> $critere();
            if ($acro == "") {
                $acro = "Autres";
            }

            if (! in_array($acro, $acros)) {
                $acros[]              = $acro;
            }
            if (!array_key_exists($acro, $num_projets)) {
                $num_projets[$acro]   = 0;
            }
            if (!array_key_exists($acro, $num_projets_n)) {
                $num_projets_n[$acro]   = 0;
            }
            if (!array_key_exists($acro, $num_projets_r)) {
                $num_projets_r[$acro]   = 0;
            }
            if (!array_key_exists($acro, $dem_heures)) {
                $dem_heures[$acro]    = 0;
            }
            if (!array_key_exists($acro, $attr_heures)) {
                $attr_heures[$acro]   = 0;
            }
            if (!array_key_exists($acro, $conso)) {
                $conso[$acro]         = 0;
            }
            if (!array_key_exists($acro, $conso_gpu)) {
                $conso_gpu[$acro]         = 0;
            }
            if (!array_key_exists($acro, $liste_projets)) {
                $liste_projets[$acro] = [];
            }

            $num_projets[$acro] += 1;
            if ($sv->isNouvelle($v))
            {
                $num_projets_n[$acro] += 1;
            }
            else
            {
                $num_projets_r[$acro] += 1;
            }
            
            $liste_projets[$acro][] = $p['p']->getIdProjet();

            if ($p['va'] != null) {
                $dem_heures[$acro] += $p['va']->getDemHeuresTotal();
            }
            if ($p['vb'] != null) {
                $dem_heures[$acro] += $p['vb']->getDemHeuresTotal();
            }
            //if ($acro=='LA') echo 'LA '.$p['p']->getIdProjet().' ';
            $attr_heures[$acro] += $p['attrib'];
            $conso[$acro]       += $p['c'];
            $conso_gpu[$acro]   += $p['g'];
        }
        asort($acros);

        return [$acros, $num_projets, $liste_projets, $dem_heures, $attr_heures, $conso, $num_projets_n, $num_projets_r, $conso_gpu];
    }

    /**
     * Filtre la version passee en paramètres, suivant qu'on a demandé des trucs sur les données ou pas
     *        Utilise par donneesParProjet
     *        Modifie le paramètre $p
     *        Renvoie true/false suivant qu'on veut garder la version ou pas
     *
     * Param : $v La version
     *         $p [inout] Tableau représentant le projet
     *
     * Ajoute des champs à $p (voir le code), ainsi que deux flags:
     *         - 'stk' projet ayant demandé du stockage
     *         - 'ptg' projet ayant demandé du partage
     *
     * Return: true/false le 'ou' de ces deux flags
     *
     */

    private function donneesParProjetFiltre($v, &$p): bool
    {
        $keep_it = false;
        $p = [];
        $p['p'] = $v->getProjet();
        $p['stk'] = false;
        $p['ptg'] = false;
        $p['sondVolDonnPerm'] = $v->getSondVolDonnPerm();
        $p['sondVolDonnPermTo'] = preg_replace('/^(\d+) .+/', '${1}', $p['sondVolDonnPerm']);
        $p['sondJustifDonnPerm'] = $v->getSondJustifDonnPerm();
        $p['dataMetaDataFormat'] = $v->getDataMetaDataFormat();
        $p['dataNombreDatasets'] = $v->getDataNombreDatasets();
        $p['dataTailleDatasets'] = $v->getDataTailleDatasets();
        if ($p['sondVolDonnPerm']   != null
            && $p['sondVolDonnPerm'] != '< 1To'
            && $p['sondVolDonnPerm'] != '1 To'
            && strpos($p['sondVolDonnPerm'], 'je ne sais') === false
            ) {
            $keep_it = $p['stk'] = true;
        }
        if ($p['dataMetaDataFormat'] != null && strstr($p['dataMetaDataFormat'], 'intéressé') == false) {
            $keep_it = $p['ptg'] = true;
        }
        if ($p['dataNombreDatasets'] != null && strstr($p['dataNombreDatasets'], 'intéressé') == false) {
            $keep_it = $p['ptg'] = true;
        }
        if ($p['dataTailleDatasets'] != null && strstr($p['dataTailleDatasets'], 'intéressé') == false) {
            $keep_it = $p['ptg'] = true;
        }
        return $keep_it;
    }

    /*
    *  Ajoute le champ 'c,q,f' au tableau $p:
    *         c => conso
    *         q => quota en octets
    *         q => quota en To (nombre entier)
    *
    */
    private function addConsoStockage(&$p, $annee, $ress): void
    {
        if ($ress === "") {
            $p['q']  = 0;
            $p['qt'] = 0;
            $p['c']  = 0;
            $p['ct'] = 0;
            $p['cp'] = 0;
        } else {
            $conso = $this->getConsoRessource($p['p'], $ress, $annee);
            $p['q']  = $conso[1];
            $p['qt'] = intval($p['q']/(1024*1024*1024));
            $p['c']  = $conso[0];
            $p['ct'] = intval($p['c']/(1024*1024*1024));
            $p['cp'] = ($p['q'] != 0) ? 100*$p['c']/$p['q'] : 0;
        }
    }

    /**
     * Liste tous les projets pour lesquels on a demandé des données en stockage ou en partage
     *       Utilise par ProjetController
     *
     * Param : $annee
     * Return: [ $projets, $total ] Un tableau de tableaux pour les projets, et les données consolidées
     *
     */
    public function donneesParProjet($annee): array
    {
        $total   = [];
        $projets = [];

        $total['prj']     = 0;	// Nombre de projets
        $total['sprj']    = 0;	// Nombre de projets ayant demandé du stockage
        $total['pprj']    = 0;	// Nombre de projet ayant demandé du partage
        $total['autostk'] = 0;	// Nombre de To attribués automatiquement (ie 1 To / projet)
        $total['demstk']  = 0;	// Nombre de To demandés (> 1 To / projet)
        $total['attrstk']  = 0; // Nombre de To alloués suite à une demande

        // $annee = 2017, 2018, etc. (4 caractères)
        $session_id_A = substr($annee, 2, 2) . 'A';
        $session_id_B = substr($annee, 2, 2) . 'B';
        $session_A = $this->em->getRepository(Session::class)->findOneBy(['idSession' => $session_id_A ]);
        $session_B = $this->em->getRepository(Session::class)->findOneBy(['idSession' => $session_id_B ]);

        $versions_A= $this->em->getRepository(Version::class)->findBy(['session' => $session_A ]);
        $versions_B= $this->em->getRepository(Version::class)->findBy(['session' => $session_B ]);

        /* Ressource utilisée pour déterminer l'occupation et le quota:
         *
         * Regarde le paramètre ressources_conso_group et prend la première de type 'stockage'
         *         S'il y en a plusieurs... problème !
         *         S'il n'y en a aucune... on ne fait rien
         */
        $ress = "";
        $ressources = $this->ressources_conso_group;
        if ($ressources != null) {
            foreach ($ressources as $k=>$r) {
                if ($r['type']==='stockage') {
                    $ress = $r['ress'];
                }
            }
        }

        // Boucle sur les versions de la session B
        $projets_b = [];
        foreach ($versions_B as $v) {
            $total['prj'] += 1;
            $p = [];
            $p_id = $v->getProjet()->getIdProjet();
            $keep_it = $this->donneesParProjetFiltre($v, $p);
            //if ($keep_it === true)
            //{
            $this->addConsoStockage($p, $annee, $ress);
            if ($p['stk']) {
                $total['sprj']    += 1;
                $total['demstk']  += $p['sondVolDonnPermTo'];
                $total['attrstk'] += $p['qt'];
            } else {
                $total['autostk'] += 1;
            }
            if ($p['ptg']) {
                $total['pprj'] += 1;
            }
            $projets[$p_id] = $p;
            //}
            //else
            if ($keep_it === false) {
                $total['autostk'] += 1;
            }
            $projets_b[] = $p_id;
        }

        // Boucle sur les versions de la session A
        foreach ($versions_A as $v) {
            $p_id = $v->getProjet()->getIdProjet();
            if (!in_array($p_id, $projets_b)) {
                $p = [];
                $total['prj'] += 1;

                $keep_it = $this->donneesParProjetFiltre($v, $p);

                //if ($keep_it === true) {
                $this->addConsoStockage($p, $annee, $ress);
                $projets[$p_id] = $p;
                if ($p['stk']) {
                    $total['sprj']    += 1;
                    $total['demstk']  += $p['sondVolDonnPermTo'];
                    $total['attrstk'] += $p['qt'];
                } else {
                    $total['autostk'] += 1;
                }
                if ($p['ptg']) {
                    $total['pprj'] += 1;
                }
                //}
                //else
                if ($keep_it === false) {
                    $total['autostk'] += 1;
                }
            }
        }

        return [$projets,$total];
    }

    /************************************
    * calcul de la consommation et du quota d'une ressource à une date donnée
    * N'est utilisée que par les méthodes de cette classe
    *
    * Renvoie [ $conso, $quota ]
    * NOTE - Si la table est chargée à 8h00 du matin, toutes les consos de l'année courante seront = 0 avant 8h00
    *
    ************/
    private function getConsoDate(Projet|CollaborateurVersion $projet, $ressource, \DateTime $date): array
    {
        if ($projet instanceof Projet) {
            $loginName = strtolower($projet->getIdProjet());
            $type = 2;
        } else {
            $loginName = strtolower($projet->getLoginname());
            $type = 1;
        }
        $conso = 0;
        $quota = 0;
        $compta = $this->em->getRepository(Compta::class)->findOneBy(
            [
                'date'      => $date,
                'ressource' => $ressource,
                'loginname' => $loginName,
                'type'      => $type
            ]
        );
        if ($compta != null) {
            $conso = $compta->getConso();
            $quota = $compta->getQuota();
        }

        return [$conso, $quota];
    }

    /***********************
    * calcul de la consommation et du quota d'une ressource (cpu, gpu, work_space, etc.)
    *
    * param $projet: Le projet
    *       $ressource: La ressource
    * param $annee_ou_date    : L'année ou la date
    *       Si $annee_ou_date==null                -> On considère la date du jour
    *       Si $annee_ou_date est annee courante   -> On considère la date du jour
    *       Si $annee_ou_date est une autre année  -> On considère le 31 décembre de $annee_ou_date
    *       Si $annee_ou_date est une DateTime     -> On considère la date
    *       ATTENTION Si $annee_ou_date est un string qui représente une date... ça va merder !
    *
    * S'il n'y a pas de données à la date considérée (par exemple si c'est dans le futur), on renvoie [0,0]
    *
    * Renvoie [ $conso, $quota ]
    *
    * NOTE - Si la table est chargée à 8h00 du matin, toutes les consos seront mesurées à hier
    *        Si on utilise avant 8h00 du matin toutes les consos sont à 0 !
    *
    *******************/
    public function getConsoRessource(Projet|CollaborateurVersion $projet, $ressource, $annee_ou_date=null): array
    {
        //return [0,0];
        $annee_ou_date_courante = $this->grdt->showYear();
        if ($annee_ou_date==$annee_ou_date_courante || $annee_ou_date===null) {
            $date  = $this->grdt;
        } elseif (is_object($annee_ou_date)) {
            $date = $annee_ou_date;
        } else {
            $date = new \DateTime($annee_ou_date . '-12-31');
        }
        return $this->getConsoDate($projet, $ressource, $date);
    }

    /*******
    * calcul de la consommation cumulée d'une ou plusieurs ressources dans un intervalle de dates données
    *
    * params: $projet     -> Un projet
    *         $ressources -> Un tableau de ressources
    *         $dates      -> Un tableau de deux strings représentant des dates [debut,fin(
    *
    * Retourne: La somme de la consommation pour les deux ressources dans l'intervalle de dates considéré
    *
    * Prérequis: Il ne doit pas y avoir eu de remise à zéro dans l'intervalle
    *
    * TODO - Diminuer le nombre de requêtes SQL avec une seule requête plus complexe
    *
    ***********************/
    public function getConsoIntervalle(Projet|CollaborateurVersion $projet, $ressources, $dates): int
    {
        if (! is_array($ressources) || ! is_array($dates)) {
            $this->sj->throwException(__METHOD__ . ":" . __LINE__ . " Erreur interne - \$ressources ou \$dates n'est pas un array");
        }
        if (count($ressources) < 1 || count($dates) < 2) {
            $this->sj->throwException(__METHOD__ . ":" . __LINE__ . " Erreur interne - \$ressources ou \$dates est un array trop petit");
        }

        $debut = new \DateTime($dates[0]);
        $fin   = new \DateTime($dates[1]);

        $conso_debut = 0;
        $conso_fin   = 0;
        foreach ($ressources as $r) {
            //$this->sj->debugMessage('koukou '.$r.' '.$dates[0].' '.print_r($this->getConsoDate($r,$debut),true).$dates[1].' '.print_r($this->getConsoDate($r,$fin),true));
            $conso_debut += $this->getConsoDate($projet, $r, $debut)[0];
            $conso_fin   += $this->getConsoDate($projet, $r, $fin)[0];
        }
        // $conso_fin peut être nulle si la date de fin est dans le futur !
        // Dans ce cas on renvoie 0
        return ($conso_fin) ? $conso_fin-$conso_debut : 0;
    }

    /*******************
    * calcul de la consommation "calcul" à une date donnée ou pour une année donnée
    *
    * Retourne: la consommation cpu + gpu à la date ou pour l'année donnée
    *           Ne retourne pas le quota
    *
    *************************/
    public function getConsoCalcul(Projet|CollaborateurVersion $projet, $annee_ou_date): int
    {
        $conso_gpu = $this->getConsoRessource($projet, 'gpu', $annee_ou_date);
        $conso_cpu = $this->getConsoRessource($projet, 'cpu', $annee_ou_date);
        return $conso_gpu[0] + $conso_cpu[0];
    }

    /********
     * La conso d'une version...
     *****/
    public function getConsoCalculVersion(Version $version): int
    {
        $projet = $version->getProjet();
        $annee  = $version->getAnneeSession();
        return $this->getConsoCalcul($projet, $annee);
    }

    /*******************
    * calcul de la consommation "calcul" à une date donnée ou pour une année donnée, en pourcentage du quota
    *
    * Retourne: la consommation cpu + gpu à la date ou pour l'année donnée
    *           en %age du quota cpu
    *
    *************************/
    public function getConsoCalculP(Projet|CollaborateurVersion $projet, $annee_ou_date=null): float
    {
        $conso_gpu = $this->getConsoRessource($projet, 'gpu', $annee_ou_date);
        $conso_cpu = $this->getConsoRessource($projet, 'cpu', $annee_ou_date);
        if ($conso_cpu[1] <= 0) {
            return 0;
        } else {
            return 100.0*($conso_gpu[0] + $conso_cpu[0])/$conso_cpu[1];
        }
    }

    /***************
    * Renvoie la consommation calcul (getConsoCalcul() de l'année et du mois
    *
    * params: $projet
    *         $annee (2019 ou 19)
    *         $mois (0..11)
    *
    * Retourne: La conso cpu+gpu, ou 0 si le mois se situe dans le futur
    *
    **************************/
    public function getConsoMois(Projet|CollaborateurVersion $projet, $annee, $mois) : int
    {
        $now = $this->grdt;
        $annee_courante = $now->showYear();
        $mois_courant   = $now->showMonth();
        $mois += 1;	// 0..11 -> 1..12 !

        // 2019 - 2000 !
        if (($annee==$annee_courante || abs($annee-$annee_courante)==2000) && $mois==$mois_courant)
        {
            $conso_fin = $this->getConsoCalcul($projet, $now);
        }
        else
        {
            // Pour décembre on mesure la consomation au 31 car il y a risque de remise à zéro le 1er Janvier
            // Du coup on ignore la consommation du 31 Décembre...
            if ($mois==12) {
                $d = strval($annee)."-12-31";
                $conso_fin = $this->getConsoCalcul($projet, new \DateTime($d));
            //App::getLogger()->error("koukou1 " . $this->getIdProjet() . "$d -> $conso_fin");
            }
            // Pour les autres mois on prend la conso du 1er du mois suivant
            else {
                $m = strval($mois + 1);
                $conso_fin = $this->getConsoCalcul($projet, new \DateTime($annee.'-'.$m.'-01'));
            }
        }

        // Pour Janvier on prend zéro, pas la valeur au 1er Janvier
        // La remise à zéro ne se fait jamais le 1er Janvier
        if ($mois==1)
        {
            $conso_debut = 0;
        }
        else
        {
            $conso_debut = $this->getConsoCalcul($projet, new \DateTime("$annee-$mois-01"));
        }
        if ($conso_fin>$conso_debut)
        {
            return $conso_fin-$conso_debut;
        }
        else
        {
            return 0;
        }
    }

    /*
     * Renvoie le quota seul (pas la conso) des ressources cpu
     *
     * param : $projet, $annee ou $date (cf getConsoRessource)
     * return: La consommation "calcul" pour l'année
     *
     */
    public function getQuota(Projet $projet, $annee=null): int
    {
        $conso_cpu = $this->getConsoRessource($projet, 'cpu', $annee);
        return $conso_cpu[1];
    }

    /********
     * Le quota d'une version...
     *****/
    public function getQuotaCalculVersion(Version $version): int
    {
        $projet = $version->getProjet();
        $annee  = $version->getAnneeSession();
        return $this->getQuota($projet, $annee);
    }

    /*
     * Le user connecté a-t-il accès à $projet ?
     * Si OBS (donc ADMIN) ou PRESIDENT = La réponse est Oui
     * Sinon c'est plus compliqué, on appelle userProjetACL...
     *
     * param:  $projet
     * return: true/false
     *
     *****/
    public function projetACL(Projet $projet): bool
    {
        if ($this->sac->isGranted('ROLE_OBS') ||  $this->sac->isGranted('ROLE_PRESIDENT')) {
            return true;
        } else {
            return $this->userProjetACL($projet);
        }
    }

    /***
     *
     * Le user connecté a-t-il accès à au moins une version de $projet ?
     *
     *****/
    private function userProjetACL(Projet $projet): bool
    {
        $user = $this->token->getUser();
        foreach ($projet->getVersion() as $version) {
            if ($this->userVersionACL($version, $user)==true) {
                return true;
            }
        }
        return false;
    }

    // nous vérifions si un utilisateur a le droit d'accès à une version
    public static function userVersionACL(Version $version, Individu $user): bool
    {
        // nous vérifions si $user est un collaborateur de cette version
        if ($version->isCollaborateur($user)) {
            return true;
        }

        // nous vérifions si $user est un expert de cette version
        if ($version->isExpertDe($user)) {
            return true;
        }

        // nous vérifions si $user est un expert d'une rallonge
        foreach ($version->getRallonge() as $rallonge) {
            //$e = $rallonge->getExpert();
            //if ($e != null && $user->isEqualTo($rallonge->getExpert())) return true;
            if ($rallonge->isExpertDe($user)) {
                return true;
            }
        }

        // nous vérifions si $user est un expert de la thématique
        if ($version->isExpertThematique($user)) {
            return true;
        }

        return false;
    }

    /********************************
     * Création des répertoires de données
     *
     ************************************************************/
    public function createDirectories($annee = null, $session = null): void
    {
        $rapport_directory = $this->rapport_directory;
        if ($rapport_directory != null) {
            $this->createDirectory($rapport_directory);
        } else {
            $this->sj->throwException(__METHOD__ . ":" . __FILE__ . " rapport_directory est null !");
        }

        $signature_directory = $this->signature_directory;
        if ($signature_directory != null) {
            $this->createDirectory($signature_directory);
        } else {
            $this->sj->throwException(__METHOD__ . ":" . __FILE__ . " signature_directory est null !");
        }

        $fig_directory = $this->fig_directory;
        if ($fig_directory != null) {
            $this->createDirectory($fig_directory);
        } else {
            $this->sj->throwException(__METHOD__ . ":" . __FILE__ . " fig_directory est null !");
        }

        $dfct_directory = $this->dfct_directory;
        if ($dfct_directory != null) {
            $this->createDirectory($dfct_directory);
        } else {
            $this->sj->throwException(__METHOD__ . ":" . __FILE__ . " dfct_directory est null !");
        }

        if ($session == null) {
            $session = $this->ss->getSessionCourante();
        }
        if ($annee == null) {
            $annee   = $session->getAnneSession() + 2000;
        }

        # Création des répertoires pour l'année ou la session demandée
        $this->createDirectory($rapport_directory . '/' . $annee);
        $this->createDirectory($signature_directory . '/' . $session->getIdSession());
        $this->createDirectory($dfct_directory . '/' . $annee);
    }

    private function createDirectory($dir): void
    {
        if ($dir != null && ! file_exists($dir)) {
            mkdir($dir);
        } elseif ($dir != null && ! is_dir($dir)) {
            $this->sj->errorMessage(__METHOD__ . ":" . __FILE__ . " " . $dir . " n'est pas un répertoire ! ");
            unlink($dir);
            mkdir($dir);
        }
    }

    /************************************************
     * Renvoie le chemin vers le rapport d'activité s'il existe, null s'il n'y a pas de RA
     *
     * Si $annee==null, calcule l'année précédente l'année de la session
     * (OK pour sessions de type A !)
     *
     ***********************/
    public function getRapport(Projet $projet, $annee): ?string
    {
        $rapport_directory = $this->rapport_directory;
        //if ( $annee == null )
        //    $annee  = $this->getAnneeSession()-1;

        $dir    =  $rapport_directory;
        if ($dir == null) {
            return null;
        }

        $file = $dir . '/' . $annee . '/' . $annee . $projet->getIdProjet() . '.pdf';
        if (file_exists($file) && ! is_dir($file)) {
            return $file;
        } else {
            return null;
        }
    }

    /*************************************************
     * Teste pour savoir si un projet donné a un rapport d'activité
     * On regarde dans la base de données ET dans les fichiers (!)
     *
     * Return: true/false
     *
     ********************************/
    public function hasRapport(Projet $projet, $annee): bool
    {
        $rapportActivite = $this->em->getRepository(RapportActivite::class)->findOneBy(
            [
                                'projet' => $projet,
                                'annee'  => $annee,
                                ]
        );

        if ($rapportActivite == null) {
            return false;
        }
        if ($this->getRapport($projet, $annee) == null) {
            return false;
        } else {
            return true;
        }
    }

    /**************************
     * Renvoie la taille du rapport d'activité en Ko
     * On lit la taille dans la base de données
     *
     *************************************/
    public function getSizeRapport(Projet $projet, $annee): int
    {
        $rapportActivite = $this->em->getRepository(RapportActivite::class)->findOneBy(
            [
                                'projet' => $projet,
                                'annee'  => $annee,
                                ]
        );

        if ($rapportActivite != null) {
            return  intdiv($rapportActivite->getTaille(), 1024);
        } else {
            return  0;
        }
    }

    /*
     * Renvoie un tableau contenant la ou les versions de l'année passée en paramètres
     */
    public function getVersionsAnnee(Projet $projet, $annee): array
    {
        $subAnnee   = substr(strval($annee), -2);
        $repository = $this->em->getRepository(Version::class);
        $versionA   = $this->em->getRepository(Version::class)->findOneBy([ 'idVersion' => $subAnnee . 'A' . $projet->getIdProjet(), 'projet' => $projet ]);
        $versionB   = $this->em->getRepository(Version::class)->findOneBy([ 'idVersion' => $subAnnee . 'B' . $projet->getIdProjet(), 'projet' => $projet ]);

        $versions = [];
        if ($versionA != null) {
            $versions['A'] = $versionA;
        }
        if ($versionB != null) {
            $versions['B'] = $versionB;
        }
        return $versions;
    }

    /*
     * Effacer les utilisateurs qui n'ont pas de structures de données associées:
     *         - Pas collaborateurs
     *         - Pas d'expertises
     *         - Pas de privilèges
     *
     * Renvoie un tableau contenant les clones des individus effacés
     * TODO - Un peu zarbi tout de même
     */

    public function effacer_utilisateurs($individus = null): array
    {
        $individus_effaces = [];
        $em = $this->em;
        $repo_ind = $em->getRepository(Individu::class);
        $repo_cv = $em->getRepository(CollaborateurVersion::class);
        $repo_exp = $em->getRepository(Expertise::class);

        $individus = $repo_ind->findAll();
        foreach ($individus as $individu) {
            if ( $individu -> getAdmin() ) continue;
            if ( $individu -> getPresident() ) continue;
            if ( $individu -> getObs() ) continue;
            if ( $individu -> getExpert() ) continue;
            
            if ( ! ($repo_cv->findOneBy([ 'collaborateur' => $individu ]) === null)) continue;
            if ( ! ($repo_exp->findOneBy([ 'expert' => $individu ]) === null)) continue;

            $individus_effaces[] = clone $individu;
            foreach ($em->getRepository(Sso::class)->findBy(['individu' => $individu]) as $sso) {
                $em->remove($sso);
            }

            $this->sj->infoMessage("L'individu " . $individu . ' a été effacé ');
            $em->remove($individu);
        }

        $em->flush();
        return $individus_effaces;
    }

    /**
    * calculVersionDerniere
    *
    * NOTE - la B.D. doit être cohérente, c-à-d que s'il y a des flush à faire, ils doivent
    *        être faits en entrant dans cette fonction
    *        Inversement, cette fonction refait le flush du projet afin de garder la cohérence
    *        TODO - Est-ce bien certain ? Cette fonction est appelée uniquement à partir de l'EventListener...
    *
    * @return \App\Entity\Version
    */
    public function calculVersionDerniere(Projet $projet): ?Version
    {
        //$this->sj->debugMessage( __FILE__ . ":" . __LINE__ . " coucou1");
        if ($projet->getVersion() == null) {
            return null;
        }

        $iterator = $projet->getVersion()->getIterator();
        //$cnt = count(iterator_to_array($iterator));
        //$this->sj->debugMessage( __FILE__ . ":" . __LINE__ . " coucou1.1 " . $cnt);

        $iterator->uasort(function ($a, $b) {
            if ($a->getSession() == null) {
                return true;
            } elseif ($b->getSession() == null) {
                return false;
            } else {
                return strcmp($a->getSession()->getIdSession(), $b->getSession()->getIdSession());
            }
        });

        $sortedVersions =  iterator_to_array($iterator) ;
        //$this->sj->debugMessage( __FILE__ . ":" . __LINE__ . " coucou2");
        $result = end($sortedVersions);
        //$this->sj->debugMessage( __FILE__ . ":" . __LINE__ . " coucou3 ".$result);

        if (! $result instanceof Version) {
            return null;
        }

        // update BD
        $projet->setVersionDerniere($result);
        $em = $this->em;
        $em->persist($projet);
        $em->flush();

        return $result;
    }
    //public function calculDerniereVersion(Projet $projet) { return $this->calculVersionDerniere($projet); }

    /**
     * calculVersionActive
     *
     * @return \App\Entity\Version
     */
    public function calculVersionActive(Projet $projet): ?Version
    {
        $em            = $this->em;
        $versionActive = $projet->getVersionActive();

        // Si le projet est terminé = renvoyer null
        if ($projet->getEtatProjet() == Etat::TERMINE) {
            if ($versionActive != null) {
                $projet->setVersionActive(null);
                $em->persist($projet);
                // $em->flush();
            }
            return null;
        }

        // Vérifie que la version active est vraiment active
        if ($versionActive != null &&
          ($versionActive->getEtatVersion() == Etat::ACTIF || $versionActive->getEtatVersion()  == Etat::NOUVELLE_VERSION_DEMANDEE)
          ) {
            return $versionActive;
        }

        // Sinon on la recherche, on la garde, puis on la renvoie
        $result = null;
        foreach (array_reverse($projet->getVersion()->toArray()) as $version) {
            if ($version->getEtatVersion() == Etat::ACTIF ||
                $version->getEtatVersion() == Etat::NOUVELLE_VERSION_DEMANDEE ||
                $version->getEtatVersion() == Etat::EN_ATTENTE ||
                $version->getEtatVersion() == Etat::ACTIF_TEST) {
                $result = $version;
                break;
            }
        }

        // update BD
        if ($versionActive != $result) { // seulement s'il y a un changement
            $projet->setVersionActive($result);
            $em->persist($projet);
            //$em->flush();
        }
        return $result;
    }
    public function versionActive(Projet $projet): ?Version
    {
        return $this->calculVersionActive($projet);
    }
}
