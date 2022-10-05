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

// src/Command/NettCompta.php

/***************************
 *
 * Nettoyage de la table comptabilité
 *
 * Cette commande permet de SUPPRIMER l'essentiel des données de comptabilité sur une année afin de diminuer la taille
 * de la base de données.
 *
 * UTILISATION
 *
 *      bin/console app:nettcompta 2020
 *
 * NOTES - Pour chaque ressource, on garde un enregistrement tous les 10 jours, ce qui revient à diviser lla quantité de données par 10
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
use App\Entity\Compta;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;

//use App\GramcServices\ServiceNotifications;

// the name of the command (the part after "bin/console")
#[AsCommand( name: 'app:nettcompta', )]
class NettCompta extends Command
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
        $this->setDescription('Faire place nette dans la compta, en gardant les données tous les 10 jours seulement');
        $this->setHelp('');
        $this->addArgument('year', InputArgument::REQUIRED, "année considérée pour faire le ménage");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // this method must return an integer number with the "exit status code"
        // of the command.

        // return this if there was no problem running the command

        $sd = $this->sd;
        $sp = $this->sp;
        $sj = $this->sj;
        $em = $this->em;

        $annee = intval($input->getArgument('year'));
        $anneeCourante = $sd->showYear();

        $sj->infoMessage("EXECUTION DE LA COMMANDE: nettcompta $annee");

        if ($annee <= 2000 || $annee >= $anneeCourante)
        {
            $output->writeln("ERREUR - $annee doit être entre 2000 et $anneeCourante");
            return 1;
        }

        $output->writeln("OK - Nous allons supprimer 90% des données de compta pour l'année $annee");

        $j0 = new \DateTime("$annee-01-01");
        
        $jd = new \DateTime("$annee-12-31");
        $j = clone $j0;
        $intervalle = new \DateInterval('P1D');
        $i = 0;
        $k = 0;
        $ttl_cpt = 0;
        while ($j < $jd)
        {
            if ( $i% 10 != 0)
            {
                $k++;
                $cpt = $this->supprimeDonnees($j);
                $ttl_cpt += $cpt;
                $output->writeln("Données de compta supprimées : " . $j->format("Y-m-d") . " => $cpt lignes supprimés");
            }
            else
            {
                $i = 0;
            }
            $j->add($intervalle);
            $i++;
        }
        $output->writeln("TERMINE - $k jours traités");
        $sj->infoMessage("Comptabilité de l'année $annee: $ttl_cpt lignes supprimées sur $k jours");

        return 0;
    }

    private function supprimeDonnees($j)
    {
        $em = $this->em;
        $repo = $em->getRepository(Compta::class);
        return $repo->removeDate($j);
    }
}
