<?php

declare(strict_types=1);

namespace Behat\Behat\Storage;

trait StorageTrait
{
    protected function storage(): InMemoryStorageSingleton
    {
        return InMemoryStorageSingleton::getInstance();
    }
}
