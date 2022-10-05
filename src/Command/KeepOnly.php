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
use Symfony\Component\Console\Attribute\AsCommand;

//use App\GramcServices\ServiceNotifications;

// the name of the command (the part after "bin/console")
#[AsCommand( name: 'app:keeponly', )]
class KeepOnly extends Rgpd
{
    public function __construct(protected $debug, protected GramcDate $sd, protected ServiceProjets $sp, protected ServiceVersions $sv, protected ServiceJournal $sj, protected EntityManagerInterface $em)
    {
        // best practices recommend to call the parent constructor first and
        // then set your own properties. That wouldn't work in this case
        // because configure() needs the properties set in this constructor

        parent::__construct($sd,$sp,$sv,$sj,$em);
    }

    protected function configure()
    {
        $this->setDescription('KeepOnly: Vide presque complètement la base de données: ne garde que quelques projets, et les utilisateurs associés');
        $this->setHelp('Vidage massif de la base de données');
        $this->addArgument('keeponly', InputArgument::REQUIRED, "Projets à conserver: P123456,P234567");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // this method must return an integer number with the "exit status code"
        // of the command.

        // return this if there was no problem running the command

        if ($this->debug == false)
        {
            $output->writeln("OUPS - VOUS N'ETES PAS EN MODE DEBUG !");
            return 1;
        }
        else
        {
            $output->writeln("EXECUTION DE LA COMMANDE: keeponly");
            $this->sj->infoMessage("EXECUTION DE LA COMMANDE: keeponly");
        }

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
        $projets_annee = $this->buildProjetsByYear($anneeLimite, $allProjets, $a_kept);

        // On affiche le tableau $projets_annee
        foreach ($projets_annee as $a => $pAnnee) {
            $output->writeln("");
            $output->writeln("PROJETS TERMINES EN $a");

            foreach ($pAnnee as $p) {
                $output->writeln("PROJET $p");
            }
        }

        $loginnames = $this->buildUsersList($projets_annee);

        // On les affiche
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

        // effacer les données de compta, les projets
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
