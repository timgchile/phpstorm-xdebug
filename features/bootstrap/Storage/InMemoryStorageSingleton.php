<?php

declare(strict_types=1);

namespace Behat\Behat\Storage;

class InMemoryStorageSingleton
{
    private static ?InMemoryStorageSingleton $instance;

    private array $storage = [];

    protected function __construct()
    {
        $this->set('domain', getenv('DOMAIN'));
    }

    public function set(string $key, mixed $value = null): void
    {
        $this->storage[$key] = $value;
    }

    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public function get(string $key): mixed
    {
        return $this->storage[$key] ?? null;
    }
}
