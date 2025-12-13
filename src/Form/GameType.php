<?php
namespace App\Form;

use App\Entity\Game;
use App\Enum\GameStatus;
use App\Enum\RuleType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class GameType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('position', IntegerType::class, [
                'label' => 'Ordre',
            ])
            ->add('rule', ChoiceType::class, [
                'label' => 'Règle',
                'choices' => $this->choicesFromEnum(RuleType::cases()),
                'choice_value' => fn(?RuleType $r) => $r?->value,
                'choice_label' => fn(RuleType $r) => $r->value,
            ])
            ->add('prize', TextType::class, [
                'label' => 'Lot',
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => $this->choicesFromEnum(GameStatus::cases()),
                'choice_value' => fn(?GameStatus $s) => $s?->value,
                'choice_label' => fn(GameStatus $s) => $s->value,
            ])
        ;
    }

    /**
     * @template T of \UnitEnum
     * @param array<int, T> $cases
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
