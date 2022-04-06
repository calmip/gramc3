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
 * 6 Avril 2022 - On vient d'ajouter le champ TypeVersion sur l'entité Version 
 *
 **************************************************/

namespace App\Command;

use App\GramcServices\GramcDate;
use App\GramcServices\ServiceProjets;
use App\GramcServices\ServiceVersions;
use App\GramcServices\ServiceJournal;

use App\Entity\Projet;
use App\Entity\Version;
use App\Utils\Etat;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Doctrine\ORM\EntityManagerInterface;

//use App\GramcServices\ServiceNotifications;


class InitTypeVersion extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:inittypeversion';

    public function __construct(private ServiceJournal $sj, private EntityManagerInterface $em)
    {
        // best practices recommend to call the parent constructor first and
        // then set your own properties. That wouldn't work in this case
        // because configure() needs the properties set in this constructor
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Initialiser le type de chaque version à partir du type de son projet');
        $this->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // this method must return an integer number with the "exit status code"
        // of the command.

        // return this if there was no problem running the command
        $em = $this->em;
        $sj = $this->sj;
        
        $versions = $em->getRepository(Version::class)->findAll();

        $i = 0;
        foreach ($versions as $v)
        {
            $etat = $v->getEtatVersion();
            if ($etat != Etat::CREE_ATTENTE && $etat != Etat::EDITION_DEMANDE
                && $etat != Etat::EDITION_EXPERTISE && $etat != Etat::EN_ATTENTE
                && $etat != Etat::ACTIF) continue;
            $p = $v -> getProjet();
            $v->SetTypeVersion($p->getTypeProjet());
            $em->persist($v);
            if ($i % 10 == 0) $em->flush();
            $output->writeln("Version $v - type " . $v->getTypeVersion());
            $i++;
        }
        $em->flush();
        $sj->infoMessage("Type de version mis à jour pour $i versions");
        $output->writeln("Type de version mis à jour pour $i versions");
        return 0;
    }
}
