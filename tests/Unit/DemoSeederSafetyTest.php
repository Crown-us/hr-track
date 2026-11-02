<?php

namespace Tests\Unit;

use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class DemoSeederSafetyTest extends TestCase
{
    public function test_demo_seeders_never_access_database_outside_local_or_testing(): void
    {
        $original = Model::getConnectionResolver();
        $resolver = \Mockery::mock(ConnectionResolverInterface::class);
        $resolver->shouldReceive('connection')->andThrow(new \RuntimeException('Demo seeder attempted database access'));
        Model::setConnectionResolver($resolver);
        try {
            foreach (['production', 'staging'] as $environment) {
                $this->app->instance('env', $environment);
                foreach ([DemoAccountSeeder::class, DemoSeeder::class] as $class) {
                    $seeder = new $class;
                    $seeder->setContainer($this->app);
                    $seeder->run();
                    $this->addToAssertionCount(1);
                }
            }
        } finally {
            Model::setConnectionResolver($original);
            $this->app->instance('env', 'testing');
        }
    }
}
