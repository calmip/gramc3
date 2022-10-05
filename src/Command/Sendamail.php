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

// src/Command/CreateUserCommand.php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\GramcServices\ServiceNotifications;
use Symfony\Component\Console\Attribute\AsCommand;

// the name of the command (the part after "bin/console")

#[AsCommand( name: 'app:send-a-mail',)]
class Sendamail extends Command
{
    private $env;
    private $twig;
    private $sn;

    public function __construct($env, \Twig\Environment $twig, ServiceNotifications $sn)
    {
        // best practices recommend to call the parent constructor first and
        // then set your own properties. That wouldn't work in this case
        // because configure() needs the properties set in this constructor

        $this->env  = $env;
        $this->sn   = $sn;
        $this->twig = $twig;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Envoyer un mail');
        $this->setHelp('Envoyer un mail pour tester le système de mail');
        $this->addArgument('dest', InputArgument::REQUIRED, 'Adresse destinataire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // ... put here the code to create the user

        // this method must return an integer number with the "exit status code"
        // of the command.

        // return this if there was no problem running the command

        $sn   = $this->sn;
        $twig = $this->twig;
        $env  = $this->env;

        $address = $input->getArgument('dest');
        $twig_sujet   = $twig->createTemplate("Essai d'envoi de mails par gramc3");
        $twig_contenu = $twig->createTemplate("Bonjour " . $address . "\nPour essayer le système de mail en environnement ".$env."\nGramc\n");
        $sn -> sendMessage($twig_sujet, $twig_contenu, [], [$address]);
        $output->writeln('mail envoyé à '.$address);
        return 0;

        // or return this if some error happened during the execution
        // return 1;
    }
}
