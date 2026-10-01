<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StorefrontLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_logout_clears_session_and_redirects_to_login(): void
    {
        Role::findOrCreate('Customer');

        $user = User::factory()->create();
        $user->assignRole('Customer');

        $response = $this->actingAs($user)->post(route('website.account.logout'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_guest_can_post_logout_and_still_land_on_login(): void
    {
        $response = $this->post(route('website.account.logout'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_logout_with_stale_csrf_token_never_shows_page_expired(): void
    {
        $this->enforceCsrf();

        $this->post('/account/logout', ['_token' => 'stale'])
            ->assertRedirect(route('login'));

        $this->post('/logout', ['_token' => 'stale'])
            ->assertRedirect(route('admin.login'));
    }

    public function test_get_logout_also_redirects_to_login_pages(): void
    {
        $this->get('/account/logout')->assertRedirect(route('login'));
        $this->get('/logout')->assertRedirect(route('admin.login'));
    }

    public function test_stale_csrf_on_regular_form_redirects_back_instead_of_419(): void
    {
        $this->enforceCsrf();

        $this->from('/contact')
            ->post('/track-order', ['_token' => 'stale'])
            ->assertRedirect('/contact')
            ->assertSessionHas('session_expired', true);
    }

    /** CSRF middleware skips itself while env is "testing". */
    private function enforceCsrf(): void
    {
        $this->app['env'] = 'local';
    }
}
