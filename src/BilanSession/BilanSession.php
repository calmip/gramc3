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

namespace App\BilanSession;

use App\Entity\Session;
use App\Entity\Version;
use App\GramcServices\ServiceSessions;
use App\GramcServices\ServiceProjets;
use App\GramcServices\GramcDate;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManager;
use App\Form\ChoiceList\ExpertChoiceLoader;

use App\Utils\Functions;

/****************************************
 * BilanSession: cette classe encapsule les algorithmes utilisés par les calculs de bilan de session
 * TODO -> En faire un service ?
 **********************************************************/

abstract class BilanSession
{
    // Arguments:
    //            $ressources_conso_group Paramètre
    //            $request
    //            $grdt    GramcDate
    //            $session
    //            $ss      ServiceSession
    //            $em

    protected $ressources_conso_group;
    protected $em;
    protected $ss;
    protected $grdt;
    protected $sp;
    protected $session;
    protected $id_session;
    protected $annee_cour;
    protected $annee_prec;
    protected $full_annee_cour;
    protected $full_annee_prec;
    protected $session_courante_A;
    protected $session_courante_B;
    protected $session_precedente_A;
    protected $session_precedente_B;
    protected $annee_conso;
    protected $ress;
    protected $nom_ress;
    protected $t_fact;

    // Fonctions implémentées dans les classes dérivées
    protected function initTotaux()
    {
    }
    protected function getTotaux($totaux)
    {
    }
    protected function getEntetes()
    {
    }
    protected function getLigne(Version $version, &$totaux)
    {
    }


    public function __construct($ressources_conso_group, GramcDate $grdt, Session $session, ServiceProjets $sp, ServiceSessions $ss, EntityManager $em)
    {
        $this->ressources_conso_group = $ressources_conso_group;
        $this->grdt       = $grdt;
        $this->em         = $em;

        $this->ss         = $ss;
        $this->sp         = $sp;
        $this->session    = $session;

        $this->id_session = $session->getIdSession();
        $this->annee_cour = $session->getAnneeSession();
        $this->annee_prec = $this->annee_cour - 1;
        $this->full_annee_cour      = 2000 + $this->annee_cour;
        $this->full_annee_prec      = 2000 + $this->annee_prec;

        $this->session_courante_A   = $em->getRepository(Session::class)->findOneBy(['idSession' => $this->annee_cour .'A']);
        $this->session_courante_B   = $em->getRepository(Session::class)->findOneBy(['idSession' => $this->annee_cour .'B']);
        $this->session_precedente_A = $em->getRepository(Session::class)->findOneBy(['idSession' => $this->annee_prec .'A']);
        $this->session_precedente_B = $em->getRepository(Session::class)->findOneBy(['idSession' => $this->annee_prec .'B']);

        // Année de prise en compte pour le calcul de la conso passée:
        // 20A -> 2019, 20B -> 2020
        $type_session      = $session->getLibelleTypeSession(); // A ou B
        $this->annee_conso = ($type_session==='A') ? $this->annee_prec : $this->annee_cour;

        // Pour les ressources de stockage
        $ressources = $this->ressources_conso_group;
        foreach ($ressources as $k=>$r) {
            if ($r['type']==='stockage') {
                $this->ress     = $r['ress'];
                $this->nom_ress = $r['nom'];
            }
        }

        //		$t_fact = 1024*1024*1024;	// Conversion octets -> To
        $this->t_fact = 1024*1024*1024;
    }

    /*******
     * Appelée par bilanCsvAction
     *
     *********/
    public function getCsv()
    {
        $session              = $this->session;
        $id_session           = $this->id_session;
        $annee_cour           = $this->annee_cour;
        $annee_prec           = $this->annee_prec;
        $session_courante_A   = $this->session_courante_A;
        $session_precedente_A = $this->session_precedente_A;
        $session_precedente_B = $this->session_precedente_B;
        $t_fact               = $this->t_fact;

        // Juin 2021 - Non prise en compte des projets test
        //$versions             = $this->em->getRepository(Version::class)->findBy( ['session' => $session ] );
        $versions             = $this->em->getRepository(Version::class)->findVersionsSessionTypeSess($session);


        // Le tableau de totaux
        $totaux = $this->initTotaux();

        // première ligne = les entêtes
        $sortie = join("\t",$this->getEntetes()) . "\n";

        // boucle principale
        foreach ($versions as $version) {
            $sortie .= join("\t", $this->getLigne($version, $totaux));
            $sortie.= "\t";
            $sortie .= join("\t", $this->getLigneConso($version, $totaux));
            $sortie .= "\n";
        }

        // Dernière ligne = les totaux
        $sortie .= join("\t", $this->getTotaux($totaux));
        $sortie .= "\n";

        $file_name = 'bilan_session_'.$id_session.'.csv';

        return [$sortie, $file_name];
    }

    /*********************************************
     * Calcule la fin de  ligne du csv
     * Données de consommation par mois
     *
     * Params = $version = La version
     *          $totaux  = Le tableau des totaux
     *
     * Renvoie    = Un tableau correspondant à la FIN de la ligne csv
     * Voir aussi = getLigne
     *************************************************/
    protected function getLigneConso(Version $version, &$totaux)
    {
        $annee_conso = $this->annee_conso;
        $full_annee_prec = $this->full_annee_prec;
        $ligne = [];
        for ($m=0;$m<12;$m++) {
            $consmois= $this->sp->getConsoMois($version->getProjet(), $annee_conso, $m);
            $index   = 'm' . ($m<10 ? '0' : '') . $m;

            $ligne[]         = $consmois;
            $totaux[$index] += $consmois;
        };
        return $ligne;
    }
}
