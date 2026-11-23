<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    private const CACHE_KEY = 'app-settings';

    public function index(): JsonResponse
    {
        return response()->json([
            'settings' => $this->cachedSettings(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'max:100'],
            'settings.*.value' => ['nullable'],
            'settings.*.type' => ['required', 'in:text,boolean,integer,json'],
        ]);

        foreach ($data['settings'] as $setting) {
            AppSetting::set($setting['key'], $setting['value'] ?? '', $setting['type']);
        }

        $this->flushCache();

        return response()->json([
            'message' => 'Pengaturan berhasil disimpan.',
            'settings' => $this->cachedSettings(),
        ]);
    }

    private function cachedSettings(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return AppSetting::query()->get(['key', 'value', 'type'])
                ->mapWithKeys(fn ($row) => [
                    $row->key => AppSetting::get($row->key),
                ])
                ->all();
        });
    }

    private function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
