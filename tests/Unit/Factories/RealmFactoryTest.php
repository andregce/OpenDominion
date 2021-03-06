<?php

namespace OpenDominion\Tests\Unit\Factories;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use OpenDominion\Factories\RealmFactory;
use OpenDominion\Models\Realm;
use OpenDominion\Models\Round;
use OpenDominion\Tests\AbstractBrowserKitTestCase;

class RealmFactoryTest extends AbstractBrowserKitTestCase
{
    use DatabaseTransactions;

    /** @var Round */
    protected $round;

    /** @var RealmFactory */
    protected $realmFactory;

    protected function setUp()
    {
        parent::setUp();

        $this->truncateGameData();
        $this->round = $this->createRound();

        $this->realmFactory = $this->app->make(RealmFactory::class);
    }

    public function testCreate()
    {
        $this->assertEquals(0, Realm::count());

        $realm = $this->realmFactory->create($this->round, 'good');

        $this->assertEquals(1, Realm::count());
        $this->assertEquals($realm->id, Realm::first()->id);
    }
}
