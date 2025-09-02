<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\Authorize;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingController extends Controller
{
    public static function middleware(): array
    {
        return [
            'auth',
            new Authorize('can:system_settings.view', only: ['index']),
            new Authorize('can:system_settings.edit', only: ['update']),
        ];
    }

    public function index(): Response
    {
        $settings = SystemSetting::with('updatedBy')
            ->orderBy('category')
            ->orderBy('key')
            ->get()
            ->groupBy('category')
            ->map(function ($categorySettings) {
                return $categorySettings->map(function ($setting) {
                    return [
                        'id' => $setting->id,
                        'key' => $setting->key,
                        'value' => $setting->value,
                        'description' => $setting->description,
                        'is_public' => $setting->is_public,
                        'is_encrypted' => $setting->is_encrypted,
                        'updated_at' => $setting->updated_at->format('M d, Y H:i'),
                        'updated_by' => $setting->updatedBy ? [
                            'id' => $setting->updatedBy->id,
                            'name' => $setting->updatedBy->name,
                        ] : null,
                    ];
                });
            });

        return Inertia::render('admin/settings/index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($validated['settings'] as $key => $value) {
            $setting = SystemSetting::where('key', $key)->first();
            
            if ($setting && $setting->value !== $value) {
                $setting->update([
                    'value' => $value,
                    'updated_by_id' => auth()->id(),
                ]);
            }
        }

        return back()->with('success', 'Configurações atualizadas com sucesso.');
    }
}