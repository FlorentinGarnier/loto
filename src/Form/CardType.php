<?php
namespace App\Form;

use App\Entity\Card;
use App\Entity\Event;
use App\Entity\Player;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('event', EntityType::class, [
                'class' => Event::class,
                'choice_label' => 'name',
                'label' => 'Événement',
                'placeholder' => '— Choisir —',
                'constraints' => [new NotBlank(message: 'Choisissez un événement.')],
            ])
            ->add('reference', TextType::class, [
                'label' => 'Référence du carton',
                'constraints' => [new NotBlank(message: 'La référence est requise.'), new Length(max: 50)],
            ])
            ->add('player', EntityType::class, [
                'class' => Player::class,
                'choice_label' => 'name',
                'label' => 'Joueur (optionnel)',
                'required' => false,
                'placeholder' => '— Aucun —',
            ])
            ->add('gridText', TextareaType::class, [
                'label' => 'Grille (3 lignes de 5 nombres 1–90)',
                'mapped' => false,
                'help' => "Saisir 3 lignes, chacune avec 5 nombres séparés par des espaces. Ex:\n1 2 3 4 5\n6 7 8 9 10\n11 12 13 14 15",
                'attr' => ['rows' => 5],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Card::class,
        ]);
    }
}
