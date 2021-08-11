<?php

declare(strict_types=1);

namespace Behat\Behat\Context;

use Symfony\Component\Dotenv\Dotenv;

class DotenvContext implements Context
{
    /**
     * @BeforeSuite
     */
    public static function setupSuite(): void
    {
        (new Dotenv(true))->loadEnv(__DIR__.'/../../../.env');
    }
}
