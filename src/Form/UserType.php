<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Get current role for editing (exclude ROLE_USER as it's always present)
        $currentRole = 'ROLE_USER';
        if (!$options['is_new'] && $options['data']) {
            $roles = $options['data']->getRoles();
            // Find the role that's not ROLE_USER
            foreach ($roles as $role) {
                if ($role !== 'ROLE_USER') {
                    $currentRole = $role;
                    break;
                }
            }
        }

        $builder
            ->add('username', TextType::class, [
                'label' => 'Username',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter username'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter email address',
                    'autocomplete' => 'email',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter an email address',
                    ]),
                    new Email([
                        'message' => 'Please enter a valid email address',
                    ]),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Password',
                'mapped' => false,
                'required' => $options['is_new'],
                'attr' => [
                    'class' => 'form-control',
                    'autocomplete' => 'new-password',
                    'placeholder' => $options['is_new'] ? 'Enter password' : 'Leave blank to keep current password'
                ],
                'constraints' => $options['is_new'] ? [
                    new NotBlank([
                        'message' => 'Please enter a password',
                    ]),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'Your password should be at least 8 characters',
                        'max' => 4096,
                    ]),
                ] : []
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Role',
                'choices' => [
                    'User' => 'ROLE_USER',
                    'Staff' => 'ROLE_STAFF',
                ],
                'multiple' => false,
                'expanded' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control'
                ],
                'data' => $currentRole,
            ])
        ;

        // Only show enable/disable checkbox when editing (not for new users)
        if (!$options['is_new']) {
            $builder->add('isEnabled', CheckboxType::class, [
                'label' => 'Enable Account',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'If enabled, account status is active and user can log in. If disabled, account status is inactive and user cannot log in.',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_new' => false,
        ]);
    }
}

