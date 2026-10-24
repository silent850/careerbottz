<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\WhatsApp\WhatsAppIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class WhatsAppIdentityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_user_for_a_new_phone_number(): void
    {
        $user = app(WhatsAppIdentityService::class)
            ->findOrCreate('+255712345678');

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('+255712345678', $user->phone_e164);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone_e164' => '+255712345678',
        ]);
    }

    public function test_it_returns_the_existing_user_for_the_same_phone(): void
    {
        $service = app(WhatsAppIdentityService::class);

        $first = $service->findOrCreate('+255712345678');
        $second = $service->findOrCreate('+255712345678');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_it_rejects_a_phone_number_that_is_not_e164(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(WhatsAppIdentityService::class)->findOrCreate('0712345678');
    }
}
