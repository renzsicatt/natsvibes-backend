<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrustedContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrustedContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->trustedContacts()->latest()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $contact = $request->user()->trustedContacts()->create($this->validated($request));

        return response()->json(['data' => $contact], 201);
    }

    public function update(Request $request, TrustedContact $trustedContact): JsonResponse
    {
        $this->own($request, $trustedContact);
        $trustedContact->update($this->validated($request, true));

        return response()->json(['data' => $trustedContact]);
    }

    public function destroy(Request $request, TrustedContact $trustedContact): JsonResponse
    {
        $this->own($request, $trustedContact);
        $trustedContact->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $rule = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$rule, 'string', 'max:100'],
            'phone_number' => [$rule, 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'relation' => ['nullable', 'string', 'max:80'],
        ]);
    }

    private function own(Request $request, TrustedContact $contact): void
    {
        abort_unless($contact->user_id === $request->user()->id, 403);
    }
}
