<?php

namespace App\Form;

use App\Entity\Driver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DriverType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'Prénom', 'attr' => ['class' => 'form-input']])
            ->add('lastName', TextType::class, ['label' => 'Nom', 'attr' => ['class' => 'form-input']])
            ->add('phone', TelType::class, ['label' => 'Téléphone', 'attr' => ['class' => 'form-input']])
            ->add('email', EmailType::class, ['label' => 'Email', 'required' => false, 'attr' => ['class' => 'form-input']])
            ->add('vehicleModel', TextType::class, ['label' => 'Modèle véhicule', 'attr' => ['placeholder' => 'Ex: Mercedes Classe E', 'class' => 'form-input']])
            ->add('vehicleType', ChoiceType::class, [
                'label' => 'Type de véhicule',
                'choices' => Driver::VEHICLE_TYPES,
                'placeholder' => '-- Sélectionner --',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('licensePlate', TextType::class, ['label' => "Plaque d'immatriculation", 'attr' => ['class' => 'form-input']])
            ->add('licenseNumber', TextType::class, ['label' => 'N° permis de conduire', 'required' => false, 'attr' => ['class' => 'form-input']])
            ->add('licenseExpiry', DateType::class, [
                'label' => "Expiration permis",
                'widget' => 'single_text',
                'required' => false,
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Disponible' => Driver::STATUS_AVAILABLE,
                    'En mission' => Driver::STATUS_BUSY,
                    'Hors ligne' => Driver::STATUS_OFFLINE,
                ],
                'attr' => ['class' => 'form-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Driver::class]);
    }
}
