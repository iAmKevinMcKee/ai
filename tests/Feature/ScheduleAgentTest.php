<?php

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Fixtures\Agents\AssistantAgent;

it('runs an agent and writes its response to the output', function () {
    AssistantAgent::fake(['Daily digest summary']);

    $this->artisan('agent:run', [
        'agent' => AssistantAgent::class,
        'prompt' => 'Summarize today\'s signups',
    ])->assertSuccessful()->expectsOutput('Daily digest summary');

    AssistantAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->prompt === 'Summarize today\'s signups');
});

it('passes provider, model, and timeout options to the agent', function () {
    AssistantAgent::fake(['Digest']);

    $this->artisan('agent:run', [
        'agent' => AssistantAgent::class,
        'prompt' => 'Summarize',
        '--provider' => 'openai',
        '--model' => 'claude-haiku-4-5-20251001',
        '--timeout' => '120',
    ])->assertSuccessful();

    AssistantAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->model === 'claude-haiku-4-5-20251001'
        && $prompt->timeout === 120
    );
});

it('fails when the agent class is invalid', function () {
    $this->artisan('agent:run', ['agent' => 'App\\Nope'])->assertFailed();
});

it('fails with a non-zero exit code when the agent throws', function () {
    AssistantAgent::fake([fn () => throw new RuntimeException('Agent exploded')]);

    $this->artisan('agent:run', [
        'agent' => AssistantAgent::class,
        'prompt' => 'Summarize',
    ])->assertFailed();
});

it('runs a scheduled agent definition by index', function () {
    AssistantAgent::fake(['Daily digest summary']);

    $agent = new AssistantAgent;
    $attachment = Document::fromPath(__DIR__.'/../Fixtures/document.txt');

    Schedule::macro('agents', fn () => [[
        'agent' => $agent,
        'prompt' => 'Summarize today\'s signups',
        'attachments' => [$attachment],
        'provider' => Lab::OpenAI,
        'model' => 'gpt-6',
        'timeout' => 120,
    ]]);

    $this->artisan('agent:run', ['agent' => '0'])
        ->assertSuccessful()
        ->expectsOutput('Daily digest summary');

    AssistantAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->prompt === 'Summarize today\'s signups'
        && $prompt->attachments->all() === [$attachment]
        && $prompt->model === 'gpt-6'
        && $prompt->timeout === 120
    );
});

it('resolves scheduled agent class names from the container', function () {
    AssistantAgent::fake(['Digest']);

    Schedule::macro('agents', fn () => [[
        'agent' => AssistantAgent::class,
        'prompt' => 'Summarize',
        'attachments' => [],
        'provider' => [Lab::OpenAI, Lab::Anthropic],
        'model' => null,
        'timeout' => null,
    ]]);

    $this->artisan('agent:run', ['agent' => '0'])->assertSuccessful();

    AssistantAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->prompt === 'Summarize');
});

it('fails when no scheduled agent is registered at the given index', function () {
    Schedule::macro('agents', fn () => []);

    $this->artisan('agent:run', ['agent' => '3'])->assertFailed();
});
