<?php

namespace App\Form;

use App\Entity\Game;
use App\Enum\GameStatus;
use App\Enum\RuleType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class GameType extends AbstractType
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('position', IntegerType::class, [
                'label' => 'Ordre',
            ])
            ->add('rule', ChoiceType::class, [
                'label' => 'Règle',
                'choices' => $this->choicesFromEnum(RuleType::cases()),
                'choice_value' => fn (?RuleType $r) => $r?->trans($this->translator),
                'choice_label' => fn (RuleType $r) => $r->trans($this->translator),
            ])
            ->add('prize', TextType::class, [
                'label' => 'Lot',
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => $this->choicesFromEnum(GameStatus::cases()),
                'choice_value' => fn (?GameStatus $s) => $s?->trans($this->translator),
                'choice_label' => fn (GameStatus $s) => $s->trans($this->translator),
            ])
            ->add('hallOnly', CheckboxType::class, [
                'label' => 'Salle uniquement (pas de détection automatique)',
                'required' => false,
            ])
        ;
    }

    /**
     * @template T of \UnitEnum
     *
     * @param array<int, T> $cases
     *
     * @return array<string, T>
     */
    private function choicesFromEnum(array $cases): array
    {
        $choices = [];
        foreach ($cases as $case) {
            $choices[$case->name] = $case; // label => case object
        }

        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Game::class,
        ]);
    }
}
