<?php

namespace App\Http\Controllers\cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\cms\SaveCategoryRequest;
use App\Models\Category;
use App\Models\CategoryRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:80'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
        ]);

        $search = trim($data['search'] ?? '');
        $status = $data['status'] ?? null;

        $matches = fn (Category $c) => ($search === '' || mb_stripos($c->name, $search) !== false || mb_stripos((string) $c->name_ne, $search) !== false)
            && ($status === null || $c->status === ($status === 'active'));

        $mains = Category::query()
            ->whereNull('parent_id')
            ->withCount(['shops', 'errands' => fn (Builder $q) => $q->watching()])
            ->with(['children' => fn ($q) => $q->withCount(['shops', 'errands' => fn (Builder $e) => $e->watching()])])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($mains as $main) {
            $children = $main->children->filter($matches);

            if (! $matches($main) && $children->isEmpty()) {
                continue;
            }

            $rows[] = $this->row($main, $main->shops_count + $main->children->sum('shops_count'), $main->errands_count + $main->children->sum('errands_count'));

            foreach ($children as $child) {
                $rows[] = $this->row($child, $child->shops_count, $child->errands_count);
            }
        }

        return Inertia::render('cms/categories/index', [
            'rows' => $rows,
            'mains' => $mains->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name]),
            'filters' => ['search' => $search, 'status' => $status ?? ''],
            'requests' => CategoryRequest::query()
                ->selectRaw('LOWER(`query`) as term, count(*) as total, max(created_at) as last_at')
                ->groupBy('term')
                ->orderByDesc('total')
                ->limit(8)
                ->get(),
        ]);
    }

    public function store(SaveCategoryRequest $request): RedirectResponse
    {
        Category::query()->create([...$request->attributesToSave(), 'slug' => $this->uniqueSlug($request->string('name')->toString())]);

        return back()->with('success', 'Category created.');
    }

    public function update(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->attributesToSave());

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->children()->exists() || $category->shops()->exists() || $category->errands()->exists()) {
            return back()->with('error', 'This category is in use. Switch it to inactive instead.');
        }

        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Category $category, int $vendors, int $wishlists): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'name_ne' => $category->name_ne,
            'emoji' => $category->emoji,
            'options' => $category->options ?? [],
            'keywords' => $category->keywords ?? [],
            'typical_price_min' => $category->typical_price_min,
            'typical_price_max' => $category->typical_price_max,
            'status' => $category->status,
            'sort_order' => $category->sort_order,
            'vendors' => $vendors,
            'wishlists' => $wishlists,
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;

        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
