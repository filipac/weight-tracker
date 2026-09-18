<?php

namespace App\Http\Controllers\Health;

use App\Health\AppleHealthExport;
use App\Health\BlogClient;
use App\Health\MetricCatalog;
use App\Health\OuraClient;
use App\Health\ProviderException;
use App\Health\PublishingWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PreviewController
{
    public function status(OuraClient $oura)
    {
        $blogs = [];
        foreach (config('health.blogs') as $key => $blog) {
            $blogs[$key] = ['label' => $blog['label'], 'url' => $blog['url'], 'configured' => (bool) ($blog['username'] && $blog['password'])];
        }

        $destination = PublishingWorkflow::rememberedDestination();

        return response()->json(['destination' => $destination, 'blogs' => $blogs, 'topics' => MetricCatalog::TOPICS, 'sources' => MetricCatalog::SOURCES, 'workout_types' => MetricCatalog::WORKOUT_TYPES, 'providers' => [
            'apple_health' => app(AppleHealthExport::class)->status(),
            'oura' => ['configured' => $oura->configured(), 'connected' => (bool) $oura->tokens(), 'missing_scopes' => array_values(array_diff(config('health.oura.scopes'), $oura->tokens()['scopes'] ?? [])), 'login' => '/login-oura'],
            'withings' => ['configured' => (bool) config('services.withings.client_id'), 'connected' => (bool) cache('withings'), 'login' => '/w', 'needs_activity' => ! in_array('user.activity', cache('withings')['scopes'] ?? [], true)],
        ]]);
    }

    public function rememberDestination(Request $request)
    {
        $validated = $request->validate(['destination' => 'required|in:local,production']);
        Cache::forever('health:last_destination', $validated['destination']);

        return response()->noContent();
    }

    public function testConnection(Request $request, BlogClient $blog)
    {
        $request->validate(['destination' => 'required|in:local,production']);
        try {
            $result = $blog->test($request->destination);

            return response()->json(['connected' => true, 'destination' => $request->destination,
                'url' => config('health.blogs.'.$request->destination.'.url'), 'message' => 'Connected. The credentials can publish health entries.',
                'username' => $result['username'] ?? null]);
        } catch (ProviderException $e) {
            return response()->json(['connected' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function start(Request $request, PublishingWorkflow $workflow)
    {
        $request->validate(['destination' => 'required|in:local,production']);

        return $this->respond(fn () => $workflow->start($request->destination, $this->owner($request)));
    }

    public function fetch(Request $request, string $id, string $task, PublishingWorkflow $workflow)
    {
        return $this->respond(fn () => $workflow->fetch($id, $this->owner($request), $task));
    }

    public function prepare(Request $request, string $id, PublishingWorkflow $workflow)
    {
        return $this->respond(fn () => $workflow->prepare($id, $this->owner($request)));
    }

    public function publish(Request $request, string $id, PublishingWorkflow $workflow)
    {
        $request->validate(['destination' => 'required|in:local,production', 'entry' => 'required|string|max:80']);

        return $this->respond(fn () => $workflow->publish($id, $this->owner($request), $request->destination, $request->entry));
    }

    public function cancel(Request $request, string $id, PublishingWorkflow $workflow)
    {
        $workflow->cancel($id, $this->owner($request));

        return response()->noContent();
    }

    private function owner(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }

    private function respond(callable $action)
    {
        // Match the private CLI workflow: large Apple Health JSON exports and
        // ECG previews exceed PHP's 128 MB web default during decoding/merging.
        ini_set('memory_limit', '-1');
        try {
            return response()->json($action());
        } catch (ProviderException $e) {
            return response()->json(['message' => $e->getMessage(), 'state' => $e->state], $e->state === 'stale' ? 409 : 422);
        }
    }
}
