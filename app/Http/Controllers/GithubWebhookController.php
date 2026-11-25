<?php

namespace App\Http\Controllers;

use App\Domain\Deployments\Actions\QueueDeployment;
use App\Domain\Deployments\Enums\DeploymentTrigger;
use App\Domain\Deployments\Exceptions\ProjectNotReadyException;
use App\Domain\Deployments\Webhooks\GithubSignature;
use App\Domain\Projects\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DEP-2 / DEP-3: GitHub push webhook. The only public endpoint besides login
 * and setup, so the HMAC signature is checked before anything is read.
 */
class GithubWebhookController extends Controller
{
    public function __invoke(Request $request, string $slug, QueueDeployment $queueDeployment): JsonResponse
    {
        $project = Project::query()->where('slug', $slug)->first();

        if ($project === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (! GithubSignature::isValid($request->getContent(), $request->header('X-Hub-Signature-256'), $project->webhook_secret)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = (string) $request->header('X-GitHub-Event');

        if ($event === 'ping') {
            return response()->json(['message' => 'pong']);
        }

        if ($event !== 'push') {
            return response()->json(['message' => "Ignored: Pushpilot only deploys on push events, not \"{$event}\"."], 202);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response()->json(['message' => 'The payload is not JSON. Set the content type to application/json.'], 400);
        }

        if (($payload['ref'] ?? null) !== 'refs/heads/'.$project->branch) {
            return response()->json(['message' => "Ignored: only pushes to {$project->branch} are deployed."], 202);
        }

        if (($payload['deleted'] ?? false) === true) {
            return response()->json(['message' => 'Ignored: the branch was deleted.'], 202);
        }

        $headCommit = is_array($payload['head_commit'] ?? null) ? $payload['head_commit'] : [];

        try {
            $deployment = $queueDeployment->handle($project, null, DeploymentTrigger::Webhook, [
                'hash' => is_string($headCommit['id'] ?? null) ? $headCommit['id'] : null,
                'message' => is_string($headCommit['message'] ?? null) ? strtok($headCommit['message'], "\n") ?: null : null,
                'author' => is_string($headCommit['author']['name'] ?? null) ? $headCommit['author']['name'] : null,
            ]);
        } catch (ProjectNotReadyException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['message' => 'Deployment queued.', 'deployment_id' => $deployment->id], 202);
    }
}
