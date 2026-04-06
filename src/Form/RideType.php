<?php

namespace App\Form;

use App\Entity\Ride;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class RideType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('clientName', TextType::class, [
                'label' => 'Nom du client',
                'attr' => ['placeholder' => 'Ex: Jean Dupont', 'class' => 'form-input'],
                'constraints' => [new NotBlank(['message' => 'Le nom est requis'])],
            ])
            ->add('clientPhone', TelType::class, [
                'label' => 'Téléphone client',
                'attr' => ['placeholder' => '+33 6 00 00 00 00', 'class' => 'form-input'],
                'required' => false,
            ])
            ->add('clientEmail', EmailType::class, [
                'label' => 'Email client',
                'attr' => ['placeholder' => 'client@email.com', 'class' => 'form-input'],
                'required' => false,
            ])
            ->add('pickupAddress', TextType::class, [
                'label' => 'Adresse de départ',
                'attr' => ['placeholder' => 'Ex: Aéroport Charles de Gaulle, Terminal 2', 'class' => 'form-input'],
                'constraints' => [new NotBlank()],
            ])
            ->add('destinationAddress', TextType::class, [
                'label' => 'Adresse de destination',
                'attr' => ['placeholder' => 'Ex: Hôtel Ritz, Place Vendôme, Paris', 'class' => 'form-input'],
                'constraints' => [new NotBlank()],
            ])
            ->add('pickupDatetime', DateTimeType::class, [
                'label' => 'Date et heure de prise en charge',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-input'],
                'constraints' => [new NotBlank()],
            ])
            ->add('rideType', ChoiceType::class, [
                'label' => 'Type de course',
                'choices' => Ride::RIDE_TYPES,
                'placeholder' => '-- Sélectionner --',
                'attr' => ['class' => 'form-select'],
                'required' => false,
            ])
            ->add('passengers', IntegerType::class, [
                'label' => 'Nombre de passagers',
                'attr' => ['min' => 1, 'max' => 20, 'class' => 'form-input'],
                'data' => 1,
            ])
            ->add('luggage', IntegerType::class, [
                'label' => 'Bagages',
                'attr' => ['min' => 0, 'max' => 30, 'class' => 'form-input'],
                'data' => 0,
                'required' => false,
            ])
            ->add('flightNumber', TextType::class, [
                'label' => 'Numéro de vol (optionnel)',
                'attr' => ['placeholder' => 'Ex: AF1234', 'class' => 'form-input'],
                'required' => false,
            ])
            ->add('distanceKm', HiddenType::class, [
                'required' => false,
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Prix (€)',
                'currency' => 'EUR',
                'attr' => ['placeholder' => '0.00', 'class' => 'form-input', 'readonly' => true, 'id' => 'ride_price_input'],
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes / Instructions spéciales',
                'attr' => ['rows' => 3, 'placeholder' => 'Instructions particulières pour le chauffeur...', 'class' => 'form-textarea'],
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ride::class,
        ]);
    }
}
