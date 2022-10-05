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
 * Conformité au RGPD
 *
 * Cette commande permet de SUPPRIMER les PROJETS TERMINES depuis N années.
 * On supprime les données des projets (fichiers images, fiches, et base de données),
 * ainsi que les expertises associées et les utilisateurs qui deviennent "orphelins".
 *
 * UTILISATION:
 *
 *      bin/console app:rgpd 5
 *
 * pour supprimer les projets terminés depuis 5 ans ou plus
 *
 * NOTES - On ne supprime pas les fichiers de rapports d'activité, qui sont considérés comme des articles
 *         On ne supprime pas non plus les références de publications, ce ne sont pas des données personnelles
 *         Mais on supprime les données de comptabilité (table compta)
 *         L'exécution de cette commande peut être assez longue (> 1h00) suivant les données à supprimer
 *
 * ATTENTION:
 *     Cette commande est DANGEREUSE il est recommandé d'avor une SAUVEGARDE de la BASE DE DONNEES
 *     et du répertoire de DONNEES
 *     On vous aura prévenus....
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
use Symfony\Component\Console\Attribute\AsCommand;

//use App\GramcServices\ServiceNotifications;

// the name of the command (the part after "bin/console")
#[AsCommand( name: 'app:rgpd', )]
class Rgpd extends Command
{
    public function __construct(private GramcDate $sd, private ServiceProjets $sp, private ServiceVersions $sv, private ServiceJournal $sj, private EntityManagerInterface $em)
    {
        // best practices recommend to call the parent constructor first and
        // then set your own properties. That wouldn't work in this case
        // because configure() needs the properties set in this constructor

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Nettoyer pour conformité au RGPD: suppression des vieux projets et des utilisateurs etc. associés');
        $this->setHelp('Envoyer un mail pour tester le système de mail');
        $this->addArgument('years', InputArgument::REQUIRED, "Nombre d'années à conserver");
    }

    // effacer toutes les versions d'un projet
    protected function effacerVersions(Projet $projet, OutputInterface $output)
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
    protected function effacerRallonges(Version $version, OutputInterface $output)
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
    protected function effacerDocuments(Projet $projet, OutputInterface $output)
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

    // Effacer les enregistrements de compta correspondant aux logins
    protected function effacerCompta(OutputInterface $output, array $loginnames) : void
    {
        $em = $this->em;
        $output->writeln("");
        $output->writeln("======");
        $output->writeln("COMPTA");
        $output->writeln("======");
        foreach (array_keys($loginnames) as $log)
        {
            list($l,$d) = explode('-',$log);
            if ($d == "0") continue;
            $annee_limite = intval($d) + 1;
            $date = new \DateTime("$annee_limite-01-01");
            $del = $em->getRepository(compta::class)->removeLoginname($l,$date);
            $output->writeln ("   $log -> lignes supprimées = $del");
        }
    }

    // Effacer les projets
    protected function effacerProjets(OutputInterface $output, array $projets_annee) : void
    {
        $em = $this->em;
        $sj = $this->sj;
        foreach ($projets_annee as $a => $pAnnee)
        {
            $output->writeln("    ANNEE $a");

            // effacer les données des versions de projets
            foreach ($projets_annee[$a] as $projet) {

                $output->writeln("        PROJET $projet");

                // Effacer la version active
                $projet->setVersionActive(null);

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
    }

    //
    // Construit un tableau des projets classés par années: un tableau indexé par l'année,
    //           l'année étant l'année de fermeture du projet
    //
    // params: $limite  = Les projets terminés APRES $limite sont ignorés.
    //                    Si $limite = 2020, on ignore les projets dont la dernière version date de 2021 ou plus tard
    //         $projets = Tableau des projets à classer
    //         $toSkip  = Tableau des ids de projets à ignorer
    //
    // Retour: Le tableau des projets par année
    //
    protected function buildProjetsByYear(int $limite, array $projets, array $toSkip = []): array
    {
        //
        // $projets_annee[2015] -> un array contenant la liste des projets arrêtés ou en standby depuis 2015
        //                on le remplit pour les années des projets à supprimer (<= $anneeAncienne)

        $projets_annee = [];
        foreach ($projets as $projet) {
            if (in_array($projet->getIdProjet(),$toSkip))
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
    
            if (intval($annee) <= $limite)
            {
                $projets_annee[$annee][] = $projet;
            }

        }
        return $projets_annee;
    }

    //
    // Construit un tableau des loginnames collaborateurs des projets classés par année
    //
    // params: $projetsAnnee = Sortie de buildProjetsByYear
    //
    // retour: Le tableau est utilisateurs, sous la forme loginname-2015
    //
    
    protected function buildUsersList(array $projets_annee) : array
    {
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
        return $loginnames;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // this method must return an integer number with the "exit status code"
        // of the command.

        // return this if there was no problem running the command

        //$sn   = $this->sn;
        $sd = $this->sd;
        $sp = $this->sp;
        $sj = $this->sj;
        $em = $this->em;
        $years = $input->getArgument('years');

        $anneeCourante = $sd->showYear();
        $anneeLimite = intval($anneeCourante) - intval($years);

        $output->writeln("");
        $output->writeln("======================================================");
        $output->writeln("Les projets terminés en $anneeLimite ou avant seront supprimés");
        $output->writeln("======================================================");

        if ($anneeLimite <= 2000) {
            $output->writeln("ERREUR - vous devez rester au 21ème siècle !");
            return 1;
        }
        
        $allProjets = $em->getRepository(Projet::class)->findAll();
        $mauvais_projets = [];
        $projets_annee = $this->buildProjetsByYear($anneeLimite, $allProjets);

        // On affiche le tableau $projets_annee
        foreach ($projets_annee as $a => $pAnnee) {
            $output->writeln("");
            $output->writeln("PROJETS TERMINES EN $a");

            foreach ($pAnnee as $p) {
                $output->writeln("PROJET $p");
            }
        }

        // On les affiche
        $loginnames = $this->buildUsersList($projets_annee);        

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
        $sj->infoMessage("EXECUTION DE LA COMMANDE: rgpd $years");


        // effacer les données de compta, les projets, les utilisateurs sans projet
        $this->effacerCompta($output, $loginnames);
        $this->effacerProjets($output, $projets_annee);
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
