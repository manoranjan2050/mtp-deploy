<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AiAssistant;

use App\Services\AiAssistant\AiAssistantService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Anthropic's real Messages API (https://docs.anthropic.com/en/api/messages) -
 * unlike Modules 4-8's real local infrastructure, there is no free or local
 * way to smoke-test even the request shape without a real, billed API key.
 * `ANTHROPIC_API_KEY` is unset in this dev environment, so every test here
 * uses `Http::fake()` against Anthropic's real, documented request/response
 * shape - the same disclosed deviation as Module 9's Cloudflare and Module
 * 19's Docker, taken one step further since not even a free-tier daemon
 * exists to point at. See CLAUDE.md.
 */
class AiAssistantServiceTest extends TestCase
{
    public function test_it_returns_a_configuration_error_when_no_api_key_is_set(): void
    {
        config(['services.anthropic.api_key' => null]);

        $result = app(AiAssistantService::class)->ask('system', 'user');

        $this->assertFalse($result->successful);
        $this->assertSame('ANTHROPIC_API_KEY is not configured.', $result->error);
    }

    public function test_it_sends_the_correct_request_and_parses_a_real_response_shape(): void
    {
        config(['services.anthropic.api_key' => 'sk-ant-test', 'services.anthropic.model' => 'claude-sonnet-5']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'id' => 'msg_123',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => 'This looks like a database connection timeout.'],
                ],
                'model' => 'claude-sonnet-5',
                'stop_reason' => 'end_turn',
            ]),
        ]);

        $result = app(AiAssistantService::class)->ask('You are a helpful assistant.', 'Why did this fail?');

        $this->assertTrue($result->successful);
        $this->assertSame('This looks like a database connection timeout.', $result->text);

        Http::assertSent(function ($request) {
            return $request->hasHeader('x-api-key', 'sk-ant-test')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && $request['model'] === 'claude-sonnet-5'
                && $request['system'] === 'You are a helpful assistant.'
                && $request['messages'][0]['content'] === 'Why did this fail?';
        });
    }

    public function test_it_reports_an_api_error_response(): void
    {
        config(['services.anthropic.api_key' => 'sk-ant-test']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'type' => 'error',
                'error' => ['type' => 'invalid_request_error', 'message' => 'model not found'],
            ], 400),
        ]);

        $result = app(AiAssistantService::class)->ask('system', 'user');

        $this->assertFalse($result->successful);
        $this->assertSame('model not found', $result->error);
    }

    public function test_it_honestly_reports_a_genuinely_unreachable_endpoint(): void
    {
        config(['services.anthropic.api_key' => 'sk-ant-test', 'services.anthropic.base_url' => 'http://127.0.0.1:1']);

        $result = app(AiAssistantService::class)->ask('system', 'user');

        $this->assertFalse($result->successful);
        $this->assertNotEmpty($result->error);
    }
}
