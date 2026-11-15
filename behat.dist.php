<?php

declare(strict_types=1);

use App\Kernel;
use App\Tests\Behat\AdminActionContext;
use App\Tests\Behat\CardContext;
use App\Tests\Behat\DatasetContext;
use App\Tests\Behat\ErrorContext;
use App\Tests\Behat\EventContext;
use App\Tests\Behat\FeatureContext;
use App\Tests\Behat\GamePlayContext;
use App\Tests\Behat\PlayerContext;
use App\Tests\Behat\TransfertContext;
use App\Tests\Behat\WinnerContext;
use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Behat\MinkExtension\ServiceContainer\MinkExtension;
use FriendsOfBehat\SymfonyExtension\ServiceContainer\SymfonyExtension;

// Configuration de Behat. Pour des réglages locaux, créer behat.php (ignoré par git) : il remplace ce fichier.
return (new Config())
    ->withProfile(
        (new Profile('default'))
            ->withSuite(
                (new Suite('default'))
                    ->withPaths('%paths.base%/features')
                    ->withContexts(
                        FeatureContext::class,
                        EventContext::class,
                        PlayerContext::class,
                        CardContext::class,
                        GamePlayContext::class,
                        WinnerContext::class,
                        ErrorContext::class,
                        TransfertContext::class,
                        AdminActionContext::class,
                        DatasetContext::class,
                    )
            )
            ->withExtension(new Extension(SymfonyExtension::class, [
                // Charge .env.test : les scénarios tournent sans variables d'environnement à fournir
                'bootstrap' => 'tests/bootstrap.php',
                'kernel' => [
                    'class' => Kernel::class,
                    'environment' => 'test',
                    'debug' => true,
                ],
            ]))
            ->withExtension(new Extension(MinkExtension::class, [
                'base_url' => 'http://localhost',
                'sessions' => [
                    'symfony' => ['symfony' => null],
                ],
            ]))
    );
