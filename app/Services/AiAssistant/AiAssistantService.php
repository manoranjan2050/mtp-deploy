<?php

declare(strict_types=1);

namespace App\Services\AiAssistant;

use App\DTOs\AiAssistant\AiAssistantResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A thin wrapper over Anthropic's real Messages API
 * (https://docs.anthropic.com/en/api/messages) - not a placeholder. Unlike
 * every other third-party integration in this project (Cloudflare, Docker,
 * Telegram/Discord/Slack), this one has no free/local way to smoke-test
 * even the request shape without a real, billed API key - `ANTHROPIC_API_KEY`
 * is unset in this dev environment, so every test here uses `Http::fake()`
 * against Anthropic's real, documented request/response shape, and there is
 * no manual smoke test possible until a real key is configured. See
 * docs/Security.md for what data a prompt sends to this third party and who
 * is allowed to trigger it.
 */
class AiAssistantService
{
    public function ask(string $systemPrompt, string $userPrompt): AiAssistantResult
    {
        $apiKey = config('services.anthropic.api_key');

        if (empty($apiKey)) {
            return new AiAssistantResult(successful: false, error: 'ANTHROPIC_API_KEY is not configured.');
        }

        try {
            $response = Http::baseUrl((string) config('services.anthropic.base_url'))
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->timeout(30)
                ->post('/v1/messages', [
                    'model' => config('services.anthropic.model'),
                    'max_tokens' => 1024,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Anthropic API unreachable', ['message' => $exception->getMessage()]);

            return new AiAssistantResult(successful: false, error: 'Could not reach the Anthropic API.');
        }

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? "Anthropic API request failed (HTTP {$response->status()}).";

            Log::warning('Anthropic API request failed', ['message' => $message, 'status' => $response->status()]);

            return new AiAssistantResult(successful: false, error: $message);
        }

        $text = collect($response->json('content', []))
            ->firstWhere('type', 'text')['text'] ?? '';

        return new AiAssistantResult(successful: true, text: trim($text));
    }
}
