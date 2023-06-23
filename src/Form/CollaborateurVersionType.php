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

use App\Entity\Clessh;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;


// NOTE - Pour attribuer une clé ssh à un CollaborateurVersion
//        L'interface graphique ne permet PAS de faire d'AUTRES MODIFS sur CollaborateurVersion
//        PROVISOIRE - Voir les notes dans Entity/CollaborateurVersion et Clessh
class CollaborateurVersionType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
                    'clessh',
                    EntityType::class,
                    [
                        'label' => 'Choisissez une clé ssh: ',
                        'required' => true,
                        'multiple' => false,
                        'expanded' => true,
                        'class' => clessh::class,
                        'choices' =>  $options['clessh']
                    ]
                )
                ->add(
                    'cgu',
                    CheckBoxType::class,
                    [
                        'required'  =>  false,
                        'label'     => '',
                    ]
                )
                ->add('submit', SubmitType::class, ['label' => 'modifier' ])
                ->add('reset', ResetType::class, ['label' => 'reset' ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(array(
            'data_class' => 'App\Entity\CollaborateurVersion',
            'clessh' => []
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'collaborateurversion';
    }
}
