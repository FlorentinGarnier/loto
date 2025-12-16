<?php
namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum GameStatus: string implements TranslatableInterface
{
    case PENDING = 'PENDING';
    case RUNNING = 'RUNNING';
    case FINISHED = 'FINISHED';

    public function trans(TranslatorInterface $translator, ?string $locale = null) : string
    {
        return $translator->trans('app.state.'.$this->value, locale: $locale);
    }
}
