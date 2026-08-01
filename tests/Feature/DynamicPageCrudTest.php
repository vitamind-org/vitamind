<?php

namespace Tests\Feature;

use App\Models\User;
use App\Plugins\MockProduct\Models\MockCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicPageCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_with_table_data(): void
    {
        $user = User::factory()->create();
        MockCategory::create(['name' => 'electronics', 'is_active' => true]);

        $response = $this->actingAs($user)->get('/p/mock-product');

        $response->assertOk();
    }

    public function test_create_record_via_dynamic_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/p/mock-product/categories', [
            'name' => 'clothing',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mock_categories', ['name' => 'clothing']);
    }

    public function test_update_record_via_dynamic_page(): void
    {
        $user = User::factory()->create();
        $category = MockCategory::create(['name' => 'electronics', 'is_active' => true]);

        $response = $this->actingAs($user)->patch("/p/mock-product/categories/{$category->id}", [
            'name' => 'consumer-electronics',
            'is_active' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mock_categories', [
            'id' => $category->id,
            'name' => 'consumer-electronics',
            'is_active' => false,
        ]);
    }

    public function test_delete_record_via_dynamic_page(): void
    {
        $user = User::factory()->create();
        $category = MockCategory::create(['name' => 'electronics', 'is_active' => true]);

        $response = $this->actingAs($user)->delete("/p/mock-product/categories/{$category->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('mock_categories', ['id' => $category->id]);
    }

    public function test_unknown_page_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/p/does-not-exist');

        $response->assertStatus(404);
    }
}
