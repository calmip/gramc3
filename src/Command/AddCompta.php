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
 * Ajout de données à la table comptabilité
 *
 * Cette commande permet d'AJOUTER des données de consommation pour "boucher le trou" entre la dernière date entrée et
 * la date d'aujourd'hui
 *
 * UTILISATION - SEULEMENT EN DEBUG !
 *
 *      bin/console app:addcompta
 *
 * ATTENTION:
 *     Cette commande est DANGEREUSE il est recommandé d'avor une SAUVEGARDE de la BASE DE DONNEES
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

// the name of the command (the part after "bin/console")
#[AsCommand( name: 'app:addcompta', )]
class AddCompta extends Command
{
    public function __construct(private $debug, private GramcDate $sd, private ServiceProjets $sp, private ServiceVersions $sv, private ServiceJournal $sj, private EntityManagerInterface $em)
    {
        // best practices recommend to call the parent constructor first and
        // then set your own properties. That wouldn't work in this case
        // because configure() needs the properties set in this constructor
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Compléter les données de compta manquantes - SEULEMENT EN debug !');
        $this->setHelp('');
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

        $today = $this->sd->getNew();
        $anneeCourante = $today->showYear();
        $intervalle = new \DateInterval('P1D');
        
        if ($this->debug == false)
        {
            $output->writeln("OUPS - VOUS N'ETES PAS EN MODE DEBUG !");
            return 1;
        }
        else
        {
            $output->writeln("EXECUTION DE LA COMMANDE: addcompta");
            $sj->infoMessage("EXECUTION DE LA COMMANDE: addcompta");
        }

        $repo = $em->getRepository(Version::class);
        $versions = $repo->findNonTermines();

        // On ne complète QUE les versions
        foreach ($versions as $v)
        {
            //$output->writeln("Projet " . $v->getProjet());

            [$compta_cpu, $compta_gpu] = $this->addCompta($v, $anneeCourante);
            
            if ( $compta_cpu == null )
            {
                $output->writeln("Projet " . $v->getProjet() . " PAS DE COMPTA");
            }
            else
            {
                $der_compta_cpu = end($compta_cpu);
                $der_compta_gpu = end($compta_gpu);
                $der_date   = $der_compta_cpu->getDate();

                $i = 0;
                while ($der_date < $today)
                {
                    //$output->writeln($der_date->format('Y-n-d'));
                    $der_date->add($intervalle);
                    $i++;

                    // Compta cpu
                    $c = new Compta();
                    $c->setType($der_compta_cpu->getType());
                    $c->setConso($der_compta_cpu->getConso());
                    $c->setQuota($der_compta_cpu->getQuota());
                    $c->setRessource($der_compta_cpu->getRessource());
                    $c->setLoginname($der_compta_cpu->getLoginname());
                    $c->setDate(clone $der_date);
                    $em->persist($c);

                    // Compta gpu
                    $c = new Compta();
                    $c->setType($der_compta_gpu->getType());
                    $c->setConso($der_compta_gpu->getConso());
                    $c->setQuota($der_compta_gpu->getQuota());
                    $c->setRessource($der_compta_gpu->getRessource());
                    $c->setLoginname($der_compta_gpu->getLoginname());
                    $c->setDate(clone $der_date);
                    $em->persist($c);
                }
                $em->flush();
                $output->writeln("Projet " . $v->getProjet() . " $i jours traités");
            }
        }
        
        return 0;
    }

    private function addCompta(Version $v, int $anneeCourante)
    {
        $repo  = $this->em->getRepository(Compta::class);
        
        $conso_cpu = $repo->conso($v->getProjet()->getIdProjet(),$anneeCourante, 2, ['cpu']);
        $conso_gpu = $repo->conso($v->getProjet()->getIdProjet(),$anneeCourante, 2, ['gpu']);
        return [$conso_cpu,$conso_gpu];
    }    
}
