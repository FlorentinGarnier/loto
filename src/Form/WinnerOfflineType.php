<?php

namespace App\Form;

use App\Entity\Event;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WinnerOfflineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reference', TextType::class, [
                'label' => 'Gagnant en salle (nom ou repère)',
                'required' => true,
            ])
            // Saisie de la référence lue sur le carton : une liste de plusieurs centaines de cartons est inutilisable
            ->add('cardReference', TextType::class, [
                'label' => 'Référence du carton (facultatif)',
                'required' => false,
                'attr' => ['autocomplete' => 'off', 'autocapitalize' => 'characters'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'event' => null,
            'csrf_protection' => true,
        ]);
        $resolver->setAllowedTypes('event', ['null', Event::class]);
    }
}
