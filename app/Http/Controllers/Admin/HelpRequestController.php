<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class HelpRequestController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = Auth::guard('moonshine')->user();

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'category' => 'nullable|string|max:50',
            'priority' => 'required|in:low,normal,high,urgent',
        ]);

        HelpRequest::create([
            'user_id' => $user->id,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'category' => $validated['category'] ?? 'general',
            'priority' => $validated['priority'],
            'status' => 'new',
        ]);

        return back()->with('successMessage', 'Ваше обращение успешно отправлено! Мы свяжемся с вами в ближайшее время.');
    }
}