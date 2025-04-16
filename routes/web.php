<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;

use App\Livewire\Sections\AgentAI;
use App\Http\Controllers\ChatBotController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');

    //Bot
    Route::get('agent', AgentAI::class)->name('agent');
});

Route::prefix('chatbot')->group(function () {
    Route::get('send-message', [ChatBotController::class, 'sendMessage'])->name('send-message');
    Route::get('whatsapp-webhook', [ChatBotController::class, 'verifyWebhook'])->name('whatsapp-webhook');
    Route::post('whatsapp-webhook', [ChatBotController::class, 'proccessWebhook'])->name('whatsapp-webhook');
});

require __DIR__.'/auth.php';
