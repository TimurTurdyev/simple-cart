<?php

declare(strict_types=1);

namespace TimurTurdyev\Cart\Tests\Identity;

use TimurTurdyev\Cart\Identity\SessionIdentity;
use TimurTurdyev\Cart\Tests\TestCase;

final class SessionIdentityTest extends TestCase
{
    public function test_id_is_current_session_id(): void
    {
        $session = $this->app->make('session.store');
        $identity = new SessionIdentity($session);

        $this->assertSame($session->getId(), $identity->id());
    }

    public function test_persist_is_noop(): void
    {
        $identity = new SessionIdentity($this->app->make('session.store'));

        $id = $identity->id();
        $identity->persist();

        $this->assertSame($id, $identity->id());
        $this->assertSame([], $this->app->make('cookie')->getQueuedCookies());
    }
}
