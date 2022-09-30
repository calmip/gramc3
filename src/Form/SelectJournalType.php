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

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use App\Utils\Functions;
use App\Entity\Journal;

class SelectJournalType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'dateDebut',
            DateTimeType::class,
            [
                    'data'          => $options['from'], // valeur par défaut
                    'label'         => 'Heure de début d\'affichage',
                    'with_seconds'  => true,
                    'years'         => Functions::years($options['from'], new \DateTime()),
                    ]
        )
                ->add(
                    'dateFin',
                    DateTimeType::class,
                    [
                    'data'          => $options['untill'],
                    'label'         => 'Heure de fin d\'affichage',
                    'with_seconds'  => true,
                    'years'         => Functions::years($options['untill'], new \DateTime()),
                    ]
                )
                ->add(
                    'niveau',
                    ChoiceType::class,
                    [
                        'choices'           =>  array_flip(Journal::LIBELLE),
                        'data'              =>  Journal::INFO , // valeur par défaut
                        'label'             => 'Niveau de log',
                    ]
                )
                ->add(
                    'submit',
                    SubmitType::class,
                    [
                        'label'         => 'chercher',
                    ]
                );
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $from = new \DateTime();
        $from->setTime(0, 0, 0);

        $until= new \DateTime();
        $until->add(\DateInterval::createFromDateString('1 day'));
        $resolver->setDefaults([
            'from'    => $from,
            'untill'  => $until
            ]);
    }
}
