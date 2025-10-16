<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// SMS operations are now handled via GraphQL mutations and queries
// See graphql/sms/sms.graphql for schema
// Mutations: sendSms
// Queries: smsProviderInfo, smsBalance, smsStatus
