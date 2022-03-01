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

// src/Command/Rgpd.php

/***************************
 *
 * KeepOnly
 *
 * Cette commande permet de supprimer TOUS LES PROJETS, quelque soit leur état,
 * sauf quelques-uns
 *
 * ATTENTION:
 *     Cette commande est DANGEREUSE il est recommandé d'avoir une SAUVEGARDE de la BASE DE DONNEES
 *     et du répertoire de DONNEES
 *     A réserver au développement !!!

 *
 **************************************************/

namespace App\Command;

use App\GramcServices\GramcDate;
use App\GramcServices\ServiceProjets;
use App\GramcServices\ServiceVersions;
use App\GramcServices\ServiceJournal;

use App\Entity\Projet;
use App\Entity\Version;
use App\Entity\Compta;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Doctrine\ORM\EntityManagerInterface;

//use App\GramcServices\ServiceNotifications;


class KeepOnly extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:keeponly';

    public function __construct(private GramcDate $sd, private ServiceProjets $sp, private ServiceVersions $sv, private ServiceJournal $sj, private EntityManagerInterface $em)
    {
        // best practices recommend to call the parent constructor first and
        // then set your own properties. That wouldn't work in this case
        // because configure() needs the properties set in this constructor

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('KeepOnly: Vide presque complètement la base de données: ne garde que quelques projets, et les utilisateurs associés');
        $this->setHelp('Vidage massif de la base de données');
        $this->addArgument('keeponly', InputArgument::REQUIRED, "Projets à conserver: P123456,P234567");
    }

    // effacer toutes les versions d'un projet
    private function effacerVersions(Projet $projet, OutputInterface $output)
    {
        $em = $this->em;
        
        // Effacer les versions
        foreach ($projet->getVersion() as $version) {

            $output->writeln("                VERSION $version");

            // Effacer les collaborateurs
            foreach ($version->getCollaborateurVersion() as $item) {
                $em->remove($item);
            }
            $em->flush();

            // Effacer les expertises
            foreach ($version->getExpertise() as $item) {
                $em->remove($item);
            }
            $em->flush();

            // Effacer les rallonges
            $this->effacerRallonges($version, $output);

            // Maintenant, on peut effacer la version !
            // Pour ne pas avoir d'ennuis, effacer d'abord version dernière !
            $projet->setVersionDerniere(null);
            $em->persist($projet);
            $em->flush();

            $em->remove($version);
            $em->flush();
        }
    }

    // effacer toutes les rallonges d'une version
    private function effacerRallonges(Version $version, OutputInterface $output)
    {
        $em = $this->em;
        
        // Effacer les rallonges
        foreach ( $version->getRallonge() as $item)
        {
            //$output->writeln("Rallonge $item");
            $em->remove($item);
        }
        $em->flush();
    }

    // Effacer les documents joints au projet, ainsi que les infos sur le RA
    // Attention on fait ça AVANT de supprimer les versions !
    private function effacerDocuments(Projet $projet, OutputInterface $output)
    {
        $sv = $this->sv;
        $sp = $this->sp;
        $em = $this->em;

        // Pour chaque version, efface les documents joints
        foreach ($projet->getVersion() as $version) {
            $sv->effacerDonnees($version);
        }

        // NOTE - On laisse le rapport d'activité, qui est considéré comme une publication
        // Donc ce n'est pas une donnée personnelle
        // Mais on efface les infos sur le rapport d'activité
        foreach ($projet->getRapportActivite() as $rapport) {
            $em->remove($rapport);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // this method must return an integer number with the "exit status code"
        // of the command.

        // return this if there was no problem running the command

        //$sn   = $this->sn;
        $sd = $this->sd;
        $sp = $this->sp;
        $sj = $this->sj;
        $em = $this->em;
        $kept = $input->getArgument('keeponly');
        $a_kept = explode(',',$kept);
        
        $anneeCourante = $sd->showYear();
        $anneeLimite   = $anneeCourante;    // On supprime même les projets de cette année !

        $output->writeln("");
        $output->writeln("======================================================");
        $output->writeln("Tous les projets seront supprimés... sauf $kept");
        $output->writeln("======================================================");

        $allProjets = $em->getRepository(Projet::class)->findAll();
        $mauvais_projets = [];

        // $projets_annee[2015] -> un array contenant la liste des projets arrêtés ou en standby depuis 2015
        //                on le remplit pour les années des projets à supprimer (<= $anneeAncienne)
        $projets_annee = [];
        foreach ($allProjets as $projet) {
            if (in_array($projet->getIdProjet(),$a_kept))
            {
                continue;
            }
            $derniereVersion    =  $projet->derniereVersion();

            // Projet merdique - On le met de côté
            if ($derniereVersion == null) {
                $mauvais_projets[$projet->getIdProjet()] = $projet;
                $annee = 0;
            }
            else
            {
                $annee = $projet->derniereVersion()->getAnneeSession();
            }

            if (intval($annee) <= $anneeLimite)
            {
                $projets_annee[$annee][] = $projet;
            }
        }

        foreach ($projets_annee as $a => $pAnnee) {
            $output->writeln("");
            $output->writeln("PROJETS TERMINES EN $a");

            foreach ($pAnnee as $p) {
                $output->writeln("PROJET $p");
            }
        }

        // Rechercher la liste d'utilisateurs correspondant à ces projets
        $loginnames = [];
        foreach ($projets_annee as $a => $pAnnee) {
            foreach ($pAnnee as $p) {
                //$output->writeln("coucou " . $p->getIdProjet());
                $loginnames[strtolower($p->getIdProjet()).'-'.$a] = 1;
                foreach ($p->getVersion() as $v) {
                    foreach ($v->getCollaborateurVersion() as $cv) {
                        if ($cv->getLoginname() !== null) {
                            $loginnames[$cv->getLoginname().'-'.$a] = 1;
                        }
                    }
                }
            }
        }

        $output->writeln("");
        $output->writeln("=============================================================================================================");
        $output->writeln("Les enregistrements de compta des groupes ou utilisateurs suivants seront supprimés (loginname - date limite)");
        $output->writeln("=============================================================================================================");
        foreach (array_keys($loginnames) as $l)
        {
            $output->writeln($l);
        }

        $output->writeln("==========");
        $ans = readline(" On y va ? (o/N) ");
        if (strtolower($ans) != "o") {
            $output->writeln("ANNULATION");
            return 0;
        }

        // On y va: on commence par écrire dans le journal
        $sj->infoMessage("EXECUTION DE LA COMMANDE: keeponly $kept");


        // effacer les données de compta
        $output->writeln("");
        $output->writeln("======");
        $output->writeln("COMPTA");
        $output->writeln("======");
        foreach (array_keys($loginnames) as $log)
        {
            list($l,$d) = explode('-',$log);
            if ($d == "0") continue;
            $annee_limite = intval($a) + 1;
            $date = new \DateTime("$annee_limite-01-01");
            $del = $em->getRepository(compta::class)->removeLoginname($l,$date);
            $output->writeln ("   $log -> lignes supprimées = $del");
        }
        
        foreach ($projets_annee as $a => $pAnnee)
        {
            $output->writeln("    ANNEE $a");

            // effacer les données des versions de projets
            foreach ($projets_annee[$a] as $projet) {

                $output->writeln("        PROJET $projet");

                // effacer les documents joints
                $output->writeln("                Documents joints");
                $this->effacerDocuments($projet, $output);

                // effacer les versions du projet
                $this->effacerVersions($projet, $output);
                
                $sj->infoMessage('Le projet ' . $projet . ' a été effacé ');

                $em->remove($projet);
                $em->flush();

                $output->writeln("                Projet supprimé");

            }
        }
        
        // effacer les utilisateurs qui n'ont pas de projet
        $individus_effaces = $sp->effacer_utilisateurs();
        $output->writeln("");
        $output->writeln("=================");
        $output->writeln("INDIVIDUS EFFACES");
        $output->writeln("=================");            
        foreach ($individus_effaces as $i) {
            $output->writeln("$i ".$i->getIdIndividu()." ".$i->getMail());
        }

        $output->writeln("bye");
        return 0;

        // or return this if some error happened during the execution
        // return 1;
    }
}
