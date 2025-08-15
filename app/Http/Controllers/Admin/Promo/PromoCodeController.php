<?php

namespace App\Http\Controllers\Admin\Promo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Promo\StorePromoRequest;
use App\Http\Requests\Admin\Promo\UpdatePromoRequest;
use App\Models\PromoCode;
use App\Models\Course;
use Illuminate\Support\Str;

class PromoCodeController extends Controller
{
    public function index()
    {
        $promos = PromoCode::with('course')->orderByDesc('id')->paginate(20);
        return view('admin.promos.index', compact('promos'));
    }

    public function create()
    {
        $courses = Course::orderBy('title')->get();
        // используем ту же форму, что и для редактирования — без $promoCode
        return view('admin.promos.create', compact('courses'));
    }

    public function store(StorePromoRequest $request)
    {
        $data = $request->validated();

        if (empty($data['code'])) {
            $data['code'] = Str::upper(Str::random(8));
        }

        // чекбокс is_active может не прийти
        $data['is_active'] = (bool)($data['is_active'] ?? false);

        PromoCode::create($data);

        return redirect()->route('admin.promos.index')
            ->with('success', 'Промокод создан');
    }

    // ✨ редактирование
    public function edit(PromoCode $promo)
    {
        $courses = Course::orderBy('title')->get();
        $promoCode = $promo; // чтобы в вьюхе имя было одинаковым
        return view('admin.promos.create', compact('promoCode','courses'));
    }

    public function update(UpdatePromoRequest $request, PromoCode $promo)
    {
        $data = $request->validated();

        // Если поле code оставили пустым — не трогаем старый код
        if (array_key_exists('code', $data) && $data['code'] === null) {
            unset($data['code']);
        }

        $data['is_active'] = (bool)($data['is_active'] ?? false);

        $promo->update($data);

        return redirect()->route('admin.promos.index')
            ->with('success', 'Промокод обновлён');
    }

    public function toggle(PromoCode $promo)
    {
        $promo->is_active = !$promo->is_active;
        $promo->save();

        return back()->with('success', 'Статус промокода обновлён');
    }

    // (опционально)
    // public function destroy(PromoCode $promo)
    // {
    //     $promo->delete();
    //     return redirect()->route('admin.promos.index')->with('success','Промокод удалён');
    // }
}
