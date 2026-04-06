<?php

namespace App\Form;

use App\Entity\Hotel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HotelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => "Nom de l'hôtel", 'attr' => ['class' => 'form-input']])
            ->add('address', TextType::class, ['label' => 'Adresse', 'attr' => ['class' => 'form-input']])
            ->add('city', TextType::class, ['label' => 'Ville', 'attr' => ['class' => 'form-input']])
            ->add('phone', TelType::class, ['label' => 'Téléphone', 'required' => false, 'attr' => ['class' => 'form-input']])
            ->add('email', EmailType::class, ['label' => 'Email', 'required' => false, 'attr' => ['class' => 'form-input']])
            ->add('stars', ChoiceType::class, [
                'label' => 'Étoiles',
                'choices' => ['5 étoiles' => '5', '4 étoiles' => '4', '3 étoiles' => '3', '2 étoiles' => '2'],
                'placeholder' => '--',
                'required' => false,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('commissionRate', NumberType::class, [
                'label' => 'Taux de commission (%)',
                'scale' => 2,
                'attr' => ['min' => 0, 'max' => 100, 'step' => '0.5', 'class' => 'form-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Hotel::class]);
    }
}
