<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\MinkContext;

final class FeatureContext extends MinkContext implements Context
{
    /**
     * @Then /^Behat is set up$/
     */
    public function behatIsSetUp(): void
    {
        // Pure assertion that does not require HTTP or the database
        // If this step runs, Behat + autoload + context wiring are OK
        if (false) {
            throw new \RuntimeException('Unreachable');
        }
    }
}
