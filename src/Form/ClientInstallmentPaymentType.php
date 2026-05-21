<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ClientInstallmentPaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('paymentMethod', ChoiceType::class, [
            'label' => 'Payment method',
            'choices' => [
                'Debit card' => 'debit_card',
                'Mobile transfer' => 'mobile_transfer',
                'Bank transfer' => 'bank_transfer',
                'Cash' => 'cash',
            ],
            'attr' => ['class' => 'portal-input'],
            'constraints' => [
                new NotBlank(['message' => 'Please select a payment method.']),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
