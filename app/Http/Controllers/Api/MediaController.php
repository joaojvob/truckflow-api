<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Upload de imagens públicas: logo da empresa e foto do motorista.
 *
 * Armazenadas no disco `public`, isoladas por tenant no path.
 */
class MediaController extends Controller
{
    private const IMAGE_RULES = ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    /**
     * Envia/atualiza a logo da empresa.
     * POST /tenant/logo
     */
    public function uploadTenantLogo(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || $user->isManager(), 403, 'Sem permissão para alterar a logo.');

        $request->validate(['file' => self::IMAGE_RULES]);

        $tenantId = $this->tenantContext->effectiveId();
        $tenant = Tenant::findOrFail($tenantId);

        if ($tenant->logo_path) {
            Storage::disk('public')->delete($tenant->logo_path);
        }

        $path = $request->file('file')->store("tenants/{$tenant->id}/logo", 'public');
        $tenant->update(['logo_path' => $path]);

        return response()->json([
            'data'    => ['logo_url' => $tenant->fresh()->logoUrl()],
            'message' => 'Logo atualizada com sucesso!',
        ]);
    }

    /**
     * Envia/atualiza a foto de perfil do motorista autenticado.
     * POST /driver-profile/photo
     */
    public function uploadDriverPhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isDriver(), 403, 'Apenas motoristas possuem foto de perfil.');

        $request->validate(['file' => self::IMAGE_RULES]);

        $profile = $user->driverProfile;
        abort_unless($profile, 404, 'Perfil de motorista não encontrado.');

        if ($profile->photo_path) {
            Storage::disk('public')->delete($profile->photo_path);
        }

        $path = $request->file('file')->store("drivers/{$profile->id}/photo", 'public');
        $profile->update(['photo_path' => $path]);

        return response()->json([
            'data'    => ['photo_url' => $profile->fresh()->photoUrl()],
            'message' => 'Foto atualizada com sucesso!',
        ]);
    }
}
