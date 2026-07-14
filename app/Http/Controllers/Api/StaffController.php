<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffNOC;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * StaffController — Manajemen akun staf (CRUD).
 *
 * @role ADMIN only
 */
class StaffController extends Controller
{
    /**
     * GET /api/staff — Daftar seluruh staf.
     */
    public function index(): JsonResponse
    {
        $staff = StaffNOC::select(['id', 'name', 'email', 'role', 'is_active', 'created_at', 'totp_secret_encrypted'])
            ->orderBy('id', 'desc')
            ->get();
            
        // Map untuk mengetahui apakah TOTP sudah di-setup
        $staff->transform(function ($item) {
            $item->has_totp = !empty($item->totp_secret_encrypted);
            unset($item->totp_secret_encrypted); // Jangan kirim rahasia terenkripsi ke frontend
            return $item;
        });

        return response()->json($staff);
    }

    /**
     * POST /api/staff — Tambah staf baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:staff_noc,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['TIER_1', 'TIER_2', 'ADMIN'])],
            'is_active' => 'boolean',
        ]);

        $staff = new StaffNOC();
        $staff->name = $validated['name'];
        $staff->email = $validated['email'];
        $staff->password_hash = Hash::make($validated['password']);
        $staff->role = $validated['role'];
        $staff->is_active = $validated['is_active'] ?? true;
        $staff->save();

        return response()->json([
            'message' => 'Staff added successfully.',
            'staff' => $staff->only(['id', 'name', 'email', 'role', 'is_active'])
        ], 201);
    }

    /**
     * PUT /api/staff/{id} — Update staf.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $staff = StaffNOC::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:100', Rule::unique('staff_noc', 'email')->ignore($staff->id)],
            'password' => 'nullable|string|min:8', // Opsional, hanya jika ingin ganti sandi
            'role' => ['required', Rule::in(['TIER_1', 'TIER_2', 'ADMIN'])],
            'is_active' => 'boolean',
        ]);

        $staff->name = $validated['name'];
        $staff->email = $validated['email'];
        
        if (!empty($validated['password'])) {
            $staff->password_hash = Hash::make($validated['password']);
        }
        
        $staff->role = $validated['role'];
        
        if (isset($validated['is_active'])) {
            $staff->is_active = $validated['is_active'];
        }

        $staff->save();

        return response()->json([
            'message' => 'Staff updated successfully.',
            'staff' => $staff->only(['id', 'name', 'email', 'role', 'is_active'])
        ]);
    }

    /**
     * DELETE /api/staff/{id} — Hapus staf.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $staff = StaffNOC::findOrFail($id);

        if ($staff->id === $request->user()->id) {
            return response()->json([
                'message' => 'Cannot delete yourself.'
            ], 400);
        }

        $staff->delete();

        return response()->json([
            'message' => 'Staff deleted successfully.'
        ]);
    }

    /**
     * POST /api/staff/{id}/reset-totp — Reset/Hapus kunci TOTP staf.
     */
    public function resetTotp($id): JsonResponse
    {
        $staff = StaffNOC::findOrFail($id);
        
        $staff->totp_secret_encrypted = null;
        $staff->save();

        return response()->json([
            'message' => 'TOTP reset successfully. User will be asked to setup TOTP on next login.'
        ]);
    }
}
