<?php

namespace App\Form;

use App\Entity\Payment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class PaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isRent = $options['is_rent'] ?? false;
        $totalPrice = $options['total_price'] ?? 0;

        if ($isRent) {
            // For rented properties: downpayment + payment plan
            $builder
                ->add('downpayment', NumberType::class, [
                    'label' => 'Downpayment',
                    'mapped' => false,
                    'required' => true,
                    'attr' => [
                        'class' => 'form-control',
                        'min' => 0,
                        'max' => $totalPrice,
                        'step' => '0.01',
                    ],
                    'constraints' => [
                        new NotBlank(['message' => 'Please enter a downpayment amount']),
                        new GreaterThanOrEqual([
                            'value' => 0,
                            'message' => 'Downpayment must be 0 or greater'
                        ]),
                    ],
                ])
                ->add('paymentPlan', ChoiceType::class, [
                    'label' => 'Payment Plan',
                    'choices' => [
                        '12 Months' => 12,
                        '24 Months' => 24,
                        '36 Months' => 36,
                    ],
                    'required' => true,
                    'attr' => [
                        'class' => 'form-control',
                    ],
                    'mapped' => false,
                    'constraints' => [
                        new NotBlank(['message' => 'Please select a payment plan']),
                    ],
                ]);
        }

        $builder
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Payment Method',
                'choices' => [
                    'Debit Card' => 'debit_card',
                    'Mobile Transfer' => 'mobile_transfer',
                    'Bank Transfer' => 'bank_transfer',
                    'Cash' => 'cash',
                ],
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please select a payment method']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Payment::class,
            'is_rent' => false,
            'total_price' => 0,
        ]);
    }
}

