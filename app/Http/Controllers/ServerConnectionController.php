<?php

namespace App\Http\Controllers;

use App\Domain\Servers\Actions\CheckServerRequirements;
use App\Domain\Servers\Actions\TestServerConnection;
use App\Domain\Servers\Models\Server;
use App\Support\Ssh\Exceptions\SshAuthenticationException;
use App\Support\Ssh\Exceptions\SshException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * SRV-3 (test connection) and SRV-4 (requirements check). Both run inline:
 * they are short and bounded by SSH timeouts.
 */
class ServerConnectionController extends Controller
{
    public function test(Server $server, TestServerConnection $testConnection): RedirectResponse
    {
        Gate::authorize('connect', $server);

        $result = $testConnection->handle($server);

        Inertia::flash('connectionTest', $result->toArray());
        Inertia::flash('toast', [
            'type' => $result->successful ? 'success' : 'error',
            'message' => $result->successful ? __('Connection successful.') : __('Connection failed.'),
        ]);

        return back();
    }

    public function check(Server $server, CheckServerRequirements $checkRequirements): RedirectResponse
    {
        Gate::authorize('connect', $server);

        try {
            $report = $checkRequirements->handle($server);
        } catch (SshAuthenticationException) {
            return $this->failed('Authentication failed: authorize Pushpilot\'s key on the server, then try again.');
        } catch (SshException $e) {
            return $this->failed($e->getMessage());
        }

        Inertia::flash('toast', $report['ready']
            ? ['type' => 'success', 'message' => __('All required software is installed.')]
            : ['type' => 'warning', 'message' => __('Some required software is missing.')]);

        return back();
    }

    private function failed(string $message): RedirectResponse
    {
        Inertia::flash('connectionTest', ['successful' => false, 'message' => $message]);
        Inertia::flash('toast', ['type' => 'error', 'message' => __('Requirements check failed.')]);

        return back();
    }
}
