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
 * Nettoyage dans les invitation
 *
 * Cette commande permet de SUPPRIMER les INVITATIONS EXPIREES
 * A executer tous les jours dans un cron
 *
 * UTILISATION:
 *
 *      bin/console app:nettinvit
 *
 **************************************************/

namespace App\Command;

use App\GramcServices\GramcDate;
use App\GramcServices\ServiceJournal;

use App\Entity\Invitation;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;

class NettInvit extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:nettinvit';

    public function __construct(private $invit_duree, private GramcDate $grdt, private ServiceJournal $sj, private EntityManagerInterface $em)
    {
        // best practices recommend to call the parent constructor first and
        // then set your own properties. That wouldn't work in this case
        // because configure() needs the properties set in this constructor

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Nettoyer les invitations: suppression des invitations expirées');
        $this->setHelp('suppression des invitations expirées');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // this method must return an integer number with the "exit status code"
        // of the command.

        // return this if there was no problem running the command

        $grdt = $this->grdt;
        $sj = $this->sj;
        $em = $this->em;

        $invit_duree = $this->invit_duree;
        $duree = new \DateInterval($invit_duree);
        $now = $grdt;

        $invitations = $em->getRepository(Invitation::class)->findAll();
        $i = 0;
        $j = 0;
        foreach ( $invitations as $inv)
        {
            $j += 1;
            $expiration = clone $inv->getCreationStamp();
            $expiration->add($duree);
            //$output->writeln("now ".$now->format('Y-m-d')." expiration ".$expiration->format('Y-m-d'));
            if ($now > $expiration)
            {
                $i += 1;
                //$output->writeln("Suppression de l'$inv" );
                $em->remove($inv);
            }
        }
        //$output->writeln("Suppression de $i invitations" );
        if ($j != 0)
        {
            $em->flush();
            $sj->infoMessage("Suppression de $i/$j invitations");
        }

        return 0;
    }
}
