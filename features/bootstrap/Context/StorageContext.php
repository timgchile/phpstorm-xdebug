<?php

declare(strict_types=1);

namespace Behat\Behat\Context;

use Behat\Behat\Storage\StorageTrait;
use Behat\Gherkin\Node\PyStringNode;

final class StorageContext implements Context
{
    use StorageTrait;

    /**
     * @Then I have the following data on storage :name
     */
    public function iHaveTheFollowingDataOnStorage(string $name, PyStringNode $stringData): void
    {
        $this->storage()->set($name, $stringData->getRaw());
    }
}
