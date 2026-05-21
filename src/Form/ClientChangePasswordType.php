<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ClientChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Current password',
                'mapped' => false,
                'attr' => ['class' => 'portal-input', 'autocomplete' => 'current-password'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter your current password.']),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'The password fields must match.',
                'first_options' => [
                    'label' => 'New password',
                    'attr' => ['class' => 'portal-input', 'autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Confirm new password',
                    'attr' => ['class' => 'portal-input', 'autocomplete' => 'new-password'],
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a new password.']),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Password must be at least {{ limit }} characters.',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
