<?php

declare(strict_types=1);

namespace Behat\Behat\Context;

use Behat\Behat\Storage\StorageTrait;
use RuntimeException;

class ResponseContext implements Context
{
    use StorageTrait;

    public function __construct()
    {
    }

    /**
     * @Then I get a SUCCESSFUL response
     */
    public function iGetASuccessfulResponse(): void
    {
        $this->validateResponseCode(200);
    }

    private function validateResponseCode(int $code): void
    {
        if ($this->storage()->get('responseCode') !== $code) {
            throw new RuntimeException(sprintf('Response status code does not match expected, current is %s', $this->storage()->get('responseCode')));
        }
    }
}
