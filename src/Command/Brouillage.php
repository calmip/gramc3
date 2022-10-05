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

// src/Command/Brouillage.php

/***************************
 *
 * Brouillage de la base de données avant intervention de prestataires extérieurs
 *
 * Cette commande permet de REMPLACER les descriptions de versions et autres données confidentielles par du
 * goulbi-goulba incompréhensible.
 *
 * Chaque lettre a-z ou A-Z est remplacée par une lettre choisie au hasard
 * Chaque chiffre 0-9 est remplacé par un chiffre choisi au hasard
 *
 * Toutes les versions de tous les rpojets de la base de données sont concernées.
 * Pour chaque version, les champs suivants sont traités:
 *      - prj_titre
 *      - prj_sous_thematique
 *      - prj_financement
 *      - prj_resume
 *      - prj_expose
 *      - prj_justif_renouv
 *      - prj_algorithme
 *      - prj_genci_dari
 *      - code_nom
 *      - code_licence
 *      - sond_justif_donn_perm
 *      - dem_form_autres_autres
 *
 * La table individus est également traitée:
 *
 * TOUS LES INDIVIDUS SAUF LES ADMINS / OBS / PRESIDENTS
 *      - nom
 *      - prenom
 *      - mail
 *
 * UTILISATION
 *
 *      bin/console app:brouillage
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
use App\Entity\Compta;
use App\Entity\Individu;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;

// the name of the command (the part after "bin/console")
#[AsCommand(name: 'app:brouillage',)]
class Brouillage extends Command
{

    private $sd = null;
    private $sp = null;
    private $sv = null;
    private $sj = null;
    private $em = null;

    public function __construct(private $debug, GramcDate $sd, ServiceProjets $sp, ServiceVersions $sv, ServiceJournal $sj, EntityManagerInterface $em)
    {
        // best practices recommend to call the parent constructor first and
        // then set your own properties. That wouldn't work in this case
        // because configure() needs the properties set in this constructor

        $this->sd = $sd;
        $this->sp = $sp;
        $this->sv = $sv;
        $this->sj = $sj;
        $this->em = $em;

        $this->minuscules = [ 'a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z'];
        $this->majuscules = [ 'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','x','Y','Z'];
        $this->chiffres = [ '0','1','2','3','4','5','6','7','8','9'];

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Brouiller tout ce qui pourrait ressembler à des données personnelles');
        $this->setHelp('');
    }

    private function brouilleString($string)
    {
        $a_string = mb_str_split($string);
        foreach ($a_string as &$c)
        {
            if ($c >='a' && $c <= 'z')
            {
                shuffle($this->minuscules);
                $c = $this->minuscules[0];
            }
            else if ($c >= 'A' && $c <= 'Z')
            {
                shuffle($this->majuscules);
                $c = $this->majuscules[0];
            }
            else if ($c >= '0' && $c <= '9')
            {
                shuffle($this->chiffres);
                $c = $this->chiffres[0];
            }
        }
        return implode($a_string);        
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
            $output->writeln("EXECUTION DE LA COMMANDE: brouillage");
            $this->sj->infoMessage("EXECUTION DE LA COMMANDE: brouillage");
        }

        $sd = $this->sd;
        $sp = $this->sp;
        $sj = $this->sj;
        $em = $this->em;


        //dd($this->brouilleString("é~#è£\$azerty toto"));
        $projets = $em->getRepository(Projet::class)->findAll();
        foreach ($projets as $p)
        {
            $output->writeln("Projet: $p");
            $output->writeln("=========");

            foreach ( $p->getVersion() as $v)
            {
                $output->writeln("Version: $v");
                $output->writeln("=========");
                
                $v->setPrjTitre($this->brouilleString($v->getPrjTitre()));
                $v->setPrjSousThematique($this->brouilleString($v->getPrjSousThematique()));
                $v->setPrjFinancement($this->brouilleString($v->getPrjFinancement()));
                $v->setPrjResume($this->brouilleString($v->getPrjResume()));
                $v->setPrjExpose($this->brouilleString($v->getPrjExpose()));
                $v->setPrjJustifRenouv($this->brouilleString($v->getPrjJustifRenouv()));
                $v->setPrjAlgorithme($this->brouilleString($v->getPrjAlgorithme()));
                $v->setPrjGenciDari($this->brouilleString($v->getPrjGenciDari()));
                $v->setCodeNom($this->brouilleString($v->getCodeNom()));
                $v->setCodeLicence($this->brouilleString($v->getCodeLicence()));
                $v->setSondJustifDonnPerm($this->brouilleString($v->getSondJustifDonnPerm()));
                $v->setDemFormAutresAutres($this->brouilleString($v->getDemFormAutresAutres()));
                $em->persist($v);
                $em->flush();
            }
        }

        $individus = $em->getRepository(Individu::class)->findAll();
        foreach ( $individus as $i)
        {
            if ($i->getAdmin()) continue;
            if ($i->getObs()) continue;
            if ($i->getPresident()) continue;
            $i->setNom($this->brouilleString($i->getNom()));
            $i->setPrenom($this->brouilleString($i->getPrenom()));
            $i->setMail($this->brouilleString($i->getMail()));
            $em->persist($v);
            $em->flush();
        }
        return 0;
    }

    
    private function supprimeDonnees($j)
    {
        $em = $this->em;
        $repo = $em->getRepository(Compta::class);
        return $repo->removeDate($j);
    }
}
