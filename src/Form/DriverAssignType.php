<?php

namespace App\Form;

use App\Entity\Driver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DriverAssignType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('driver', EntityType::class, [
            'class' => Driver::class,
            'choice_label' => function (Driver $driver) {
                return sprintf('%s — %s (%s) — %s',
                    $driver->getFullName(),
                    $driver->getVehicleModel(),
                    $driver->getLicensePlate(),
                    $driver->getStatusLabel()
                );
            },
            'query_builder' => function (\App\Repository\DriverRepository $repo) {
                return $repo->createQueryBuilder('d')
                    ->where('d.isActive = true')
                    ->orderBy('d.status', 'ASC')
                    ->addOrderBy('d.firstName', 'ASC');
            },
            'label' => 'Sélectionner un chauffeur',
            'placeholder' => '-- Choisir un chauffeur --',
            'attr' => ['class' => 'form-select'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
