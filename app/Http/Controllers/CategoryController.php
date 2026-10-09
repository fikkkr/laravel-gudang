<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\Frontend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return Frontend::render('categories.index', 'categories.index', [
            'categories' => Category::query()->paginate(15),
        ]);
    }

    public function create(): View
    {
        return Frontend::render('categories.create', 'categories.create', [

        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Category::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
        ]);

        return redirect()->route('categories.index')->with('success', 'Data berhasil ditambahkan.');
    }

    public function show(Category $category): RedirectResponse
    {
        return redirect()->route('categories.edit', $category);
    }

    public function edit(Category $category): View
    {
        return Frontend::render('categories.edit', 'categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $category->update([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name'], $category),
        ]);

        return redirect()->route('categories.index')->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if (Product::query()->where('category_id', $category->id)->exists()) {
            return redirect()
                ->route('categories.index')
                ->withErrors(['category' => 'Kategori yang masih digunakan produk tidak dapat dihapus.']);
        }

        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Data berhasil dihapus.');
    }

    private function uniqueSlug(string $name, ?Category $category = null): string
    {
        $baseSlug = Str::slug($name) ?: 'category';
        $slug = $baseSlug;
        $suffix = 2;

        while (Category::query()
            ->where('slug', $slug)
            ->when($category, fn ($query) => $query->where(
                $category->getKeyName(),
                '!=',
                $category->getKey()
            ))
            ->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        return $slug;
    }
}