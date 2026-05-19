<?php

namespace Tests\Feature\Tag;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TagControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_list_of_tags(): void
    {
        Tag::factory()->count(3)->create();

        $response = $this->getJson(route('tag.index'));

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
        $tag = Tag::factory()->create(['name' => 'Manga']);

        $response = $this->getJson(route('tag.index'));

        $response->assertOk()
            ->assertJsonFragment(['id' => $tag->id, 'name' => 'Manga']);
    }

    public function test_index_is_accessible_without_authentication(): void
    {
        $response = $this->getJson(route('tag.index'));

        $response->assertOk();
    }

    public function test_index_returns_cached_result_on_second_request(): void
    {
        Tag::factory()->count(2)->create();

        $this->getJson(route('tag.index'))->assertOk();

        $this->assertTrue(Cache::has('tags'));
    }
}
