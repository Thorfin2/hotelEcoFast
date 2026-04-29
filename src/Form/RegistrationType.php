<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'Prénom', 'attr' => ['class' => 'form-input']])
            ->add('lastName', TextType::class, ['label' => 'Nom', 'attr' => ['class' => 'form-input']])
            ->add('email', EmailType::class, ['label' => 'Email', 'attr' => ['class' => 'form-input']])
            ->add('phone', TelType::class, ['label' => 'Téléphone', 'required' => false, 'attr' => ['class' => 'form-input']])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Hôtel (Admin complet)' => 'ROLE_HOTEL',
                    'Hôtel (Employé — création de course uniquement)' => 'ROLE_HOTEL_EMPLOYEE',
                    'Chauffeur' => 'ROLE_DRIVER',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'expanded' => false,
                'multiple' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Mot de passe', 'attr' => ['class' => 'form-input']],
                'second_options' => ['label' => 'Confirmer le mot de passe', 'attr' => ['class' => 'form-input']],
                'constraints' => [
                    new NotBlank(),
                    new Length(['min' => 8, 'minMessage' => 'Minimum 8 caractères']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
