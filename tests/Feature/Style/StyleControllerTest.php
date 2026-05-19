<?php

namespace Tests\Feature\Style;

use App\Models\Style;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StyleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_list_of_styles(): void
    {
        Style::factory()->count(3)->create();

        $response = $this->getJson(route('style.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name'],
                ],
                'message',
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_returns_correct_format(): void
    {
        $style = Style::factory()->create(['name' => 'Blackwork']);

        $response = $this->getJson(route('style.index'));

        $response->assertOk()
            ->assertJsonFragment(['id' => $style->id, 'name' => 'Blackwork']);
    }

    public function test_index_is_accessible_without_authentication(): void
    {
        $response = $this->getJson(route('style.index'));

        $response->assertOk();
    }

    public function test_index_returns_cached_result_on_second_request(): void
    {
        Style::factory()->count(2)->create();

        $this->getJson(route('style.index'))->assertOk();

        $this->assertTrue(Cache::has('styles'));
    }
}
