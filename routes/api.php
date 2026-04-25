<?php

use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::post('/uploads', [UploadController::class, 'store']);
Route::get('/uploads', [UploadController::class, 'list']);
Route::get('/uploads/{upload}', [UploadController::class, 'show']);
Route::get('/uploads/{upload}/download', [UploadController::class, 'download']);
Route::post('/uploads/{upload}/retry', [UploadController::class, 'retry']);
Route::post('/uploads/bulk/delete', [UploadController::class, 'bulkDelete']);
Route::post('/uploads/bulk/retry', [UploadController::class, 'bulkRetry']);

Route::get('/audit-logs', [UploadController::class, 'auditLogs']);
Route::get('/audit-logs/export', [UploadController::class, 'exportAuditLogs']);
