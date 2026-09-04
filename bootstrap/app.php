<?php

use App\Http\Middleware\AccountantView;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\VerifyWebhookSignature;
use App\Http\Middleware\ResayilFrameHeaders;
use App\Modules\ResailAI\Middleware\VerifyResailAIToken;
use App\Modules\DotwAI\Http\Middleware\ResolveDotwAIContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use PragmaRX\Google2FALaravel\Middleware as PragmaMidleware;
use App\Http\Middleware\CheckFactorAuthentication;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            '2fa' => PragmaMidleware::class,
            'check2fa' => CheckFactorAuthentication::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'accountant' => AccountantView::class,
            'dotw_audit_access' => \App\Http\Middleware\DotwAuditAccess::class,
            'verify.resailai.token' => VerifyResailAIToken::class,
            'dotwai.resolve' => ResolveDotwAIContext::class,
            'module' => EnsureModuleEnabled::class,
            // W6.S item (4) (w6-brief.md "Consolidation + fixes" -- "/api/task/webhook: require
            // signature auth -- reuse the existing HMAC middleware ... do not write a third").
            // App\Http\Middleware\VerifyWebhookSignature existed, fully implemented, but was
            // registered in NEITHER this alias map NOR any route before this sub-wave (confirmed:
            // Accounting Gap/18-error-redesign-log.md's own audit found it completely
            // unreachable). This is the first route to actually consume it -- see
            // App\Http\Webhooks\TaskWebhook::webhook() for how "verified IF a signature was
            // presented" is turned into "signature mandatory" for this one endpoint without
            // modifying the shared middleware itself.
            'verify.webhook.signature' => VerifyWebhookSignature::class,
            // Module 5 — Resayil WhatsApp CRM: CSP frame-ancestors/frame-src
            // for the drawer + full-page embed. Applied only to the Resayil
            // route(s) in routes/web.php, not app-wide.
            'resayil.frame' => ResayilFrameHeaders::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
