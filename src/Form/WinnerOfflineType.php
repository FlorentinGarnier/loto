<?php
namespace App\Form;

use App\Entity\Card;
use App\Entity\Event;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

final class WinnerOfflineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Event|null $event */
        $event = $options['event'] ?? null;

        $builder
            ->add('reference', TextType::class, [
                'label' => 'Référence libre (obligatoire)',
                'required' => true,
            ])
            ->add('card', EntityType::class, [
                'class' => Card::class,
                'required' => false,
                'label' => 'Associer à un carton (optionnel)',
                'placeholder' => '— Aucun —',
                'choice_label' => function (Card $c) {
                    return sprintf('#%d · %s%s', $c->getId(), $c->getReference(), $c->getPlayer() ? ' · '.$c->getPlayer()->getName() : '');
                },
                'query_builder' => function (EntityRepository $er) use ($event) {
                    $qb = $er->createQueryBuilder('c');
                    if ($event) {
                        $qb->andWhere('c.event = :event')->setParameter('event', $event);
                    }
                    return $qb->orderBy('c.id', 'ASC');
                },
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
