<?php

namespace Tests\Feature\Cms;

use App\Models\Category;
use App\Models\CategoryRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsNajikData;
use Tests\TestCase;

class CategoryManagerTest extends TestCase
{
    use ActsAsAdmin, BuildsNajikData, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCategories();
        $this->admin = $this->admin();
    }

    public function test_the_tree_lists_mains_then_subs_with_usage_counts(): void
    {
        $this->liveShop("Men's haircut", 200);
        $this->customer()->errands()->create(['category_id' => $this->category("Men's haircut")->id, 'title' => 'haircut']);

        $this->actingAs($this->admin)
            ->get('/cms/category-manager')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cms/categories/index', false)
                ->has('rows', 14)
                ->where('rows.0.name', 'Beauty')
                ->where('rows.0.vendors', 1)
                ->where('rows.0.wishlists', 1)
                ->where('rows.1.name', "Men's haircut")
                ->where('rows.1.parent_id', $this->category('Beauty')->id)
                ->has('mains', 4));
    }

    public function test_search_and_status_filter_keep_the_parent_row(): void
    {
        $this->category('Tailoring')->update(['status' => false]);

        $this->actingAs($this->admin)
            ->get('/cms/category-manager?search=tailor')
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 2)
                ->where('rows.0.name', 'Repairs')
                ->where('rows.1.name', 'Tailoring'));

        $this->actingAs($this->admin)
            ->get('/cms/category-manager?search=सिलाइ')
            ->assertInertia(fn (Assert $page) => $page->where('rows.1.name', 'Tailoring'));

        $this->actingAs($this->admin)
            ->get('/cms/category-manager?status=inactive')
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 2)
                ->where('rows.1.name', 'Tailoring')
                ->where('rows.1.status', false));
    }

    public function test_main_and_sub_categories_can_be_created_with_comma_lists(): void
    {
        $this->actingAs($this->admin)
            ->post('/cms/category-manager', ['name' => 'Home services', 'name_ne' => 'घर सेवा', 'emoji' => '🏠', 'status' => true])
            ->assertSessionHas('success');

        $main = Category::query()->where('name', 'Home services')->firstOrFail();
        $this->assertNull($main->parent_id);
        $this->assertSame('home-services', $main->slug);

        $this->actingAs($this->admin)
            ->post('/cms/category-manager', [
                'name' => 'Plumber',
                'parent_id' => $main->id,
                'options' => ' leak fix, tap change ,, ',
                'keywords' => 'plumber, धारा',
                'typical_price_min' => 300,
                'typical_price_max' => 1500,
                'status' => true,
            ])
            ->assertSessionHas('success');

        $sub = Category::query()->where('name', 'Plumber')->firstOrFail();
        $this->assertSame($main->id, $sub->parent_id);
        $this->assertSame(['leak fix', 'tap change'], $sub->options);
        $this->assertSame(['plumber', 'धारा'], $sub->keywords);
        $this->assertSame(1500, $sub->typical_price_max);
    }

    public function test_names_are_unique_and_parents_must_be_main_categories(): void
    {
        $this->actingAs($this->admin)
            ->post('/cms/category-manager', ['name' => 'Facial', 'status' => true])
            ->assertSessionHasErrors(['name' => 'A category with this name already exists.']);

        $this->actingAs($this->admin)
            ->post('/cms/category-manager', ['name' => 'Beard oil', 'parent_id' => $this->category("Men's haircut")->id, 'status' => true])
            ->assertSessionHasErrors(['parent_id' => 'Pick a main category as the parent.']);

        $this->actingAs($this->admin)
            ->post('/cms/category-manager', ['name' => 'Spa', 'typical_price_min' => 500, 'typical_price_max' => 100, 'status' => true])
            ->assertSessionHasErrors('typical_price_max');

        $beauty = $this->category('Beauty');
        $this->actingAs($this->admin)
            ->put("/cms/category-manager/{$beauty->id}", ['name' => 'Beauty', 'parent_id' => $this->category('Repairs')->id, 'status' => true])
            ->assertSessionHasErrors(['parent_id' => 'A main category with subcategories cannot become a subcategory.']);
    }

    public function test_a_category_can_be_updated_keeping_its_own_name(): void
    {
        $facial = $this->category('Facial');

        $this->actingAs($this->admin)
            ->put("/cms/category-manager/{$facial->id}", ['name' => 'Facial', 'name_ne' => 'फेसियल', 'parent_id' => $facial->parent_id, 'status' => false, 'options' => 'gold, fruit'])
            ->assertSessionHas('success');

        $facial->refresh();
        $this->assertFalse($facial->status);
        $this->assertSame(['gold', 'fruit'], $facial->options);
        $this->assertSame('facial', $facial->slug);
    }

    public function test_categories_in_use_cannot_be_deleted_but_unused_ones_can(): void
    {
        $this->liveShop('Car wash', 200);

        $this->actingAs($this->admin)->delete('/cms/category-manager/'.$this->category('Car wash')->id)->assertSessionHas('error');
        $this->actingAs($this->admin)->delete('/cms/category-manager/'.$this->category('Vehicle')->id)->assertSessionHas('error');

        $this->customer()->errands()->create(['category_id' => $this->category('Key copy')->id, 'title' => 'keys']);
        $this->actingAs($this->admin)->delete('/cms/category-manager/'.$this->category('Key copy')->id)->assertSessionHas('error');

        $bike = $this->category('Bike servicing');
        $this->actingAs($this->admin)->delete("/cms/category-manager/{$bike->id}")->assertSessionHas('success');
        $this->assertModelMissing($bike);
    }

    public function test_unlisted_requests_are_grouped_case_insensitively(): void
    {
        $user = $this->customer();
        CategoryRequest::query()->create(['user_id' => $user->id, 'query' => 'Plumber']);
        CategoryRequest::query()->create(['user_id' => $user->id, 'query' => 'plumber']);
        CategoryRequest::query()->create(['user_id' => $user->id, 'query' => 'yoga']);

        $this->actingAs($this->admin)
            ->get('/cms/category-manager')
            ->assertInertia(fn (Assert $page) => $page
                ->has('requests', 2)
                ->where('requests.0.term', 'plumber')
                ->where('requests.0.total', 2));
    }
}
