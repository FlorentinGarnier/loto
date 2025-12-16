<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum RuleType: string implements TranslatableInterface
{
    case LINE = 'LINE';
    case DOUBLE_LINE = 'DOUBLE_LINE';
    case FULL_CARD = 'FULL_CARD';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('app.rules.'.$this->value, locale: $locale);
    }
}
