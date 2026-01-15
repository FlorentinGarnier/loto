<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum RuleType: string implements TranslatableInterface
{
    case QUINE = 'QUINE';
    case DOUBLE_QUINE = 'DOUBLE_QUINE';
    case FULL_CARD = 'FULL_CARD';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('app.rules.'.$this->value, locale: $locale);
    }

    public function getRequiredLines(): int
    {
        return match ($this) {
            self::QUINE => 1,
            self::DOUBLE_QUINE => 2,
            self::FULL_CARD => 3,
        };
    }
}
