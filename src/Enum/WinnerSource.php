<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum WinnerSource: string implements TranslatableInterface
{
    case SYSTEM = 'SYSTEM';
    case OFFLINE = 'OFFLINE';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('app.winner.'.$this->value, locale: $locale);
    }
}
