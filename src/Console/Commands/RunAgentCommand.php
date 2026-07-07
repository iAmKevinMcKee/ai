<?php

namespace Laravel\Ai\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Ai\Contracts\Agent;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class RunAgentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:run
        {agent : The agent class or scheduled agent index to run}
        {prompt? : The prompt to send to the agent}
        {--provider= : The provider the agent should use}
        {--model= : The model the agent should use}
        {--timeout= : The request timeout in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run an AI agent and write its response to the output';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $definition = ctype_digit((string) $this->argument('agent'))
            ? $this->laravel->make(Schedule::class)->agents()[(int) $this->argument('agent')] ?? null
            : [
                'agent' => $this->argument('agent'),
                'prompt' => $this->argument('prompt') ?? '',
                'attachments' => [],
                'provider' => $this->option('provider'),
                'model' => $this->option('model'),
                'timeout' => is_numeric($this->option('timeout')) ? (int) $this->option('timeout') : null,
            ];

        if (is_null($definition)) {
            $this->components->error("No scheduled agent is registered at index [{$this->argument('agent')}].");

            return self::FAILURE;
        }

        $agent = is_string($definition['agent']) && is_a($definition['agent'], Agent::class, true)
            ? $this->laravel->make($definition['agent'])
            : $definition['agent'];

        if (! $agent instanceof Agent) {
            $this->components->error(sprintf(
                'The [%s] class is not a valid agent.', is_object($agent) ? $agent::class : $agent
            ));

            return self::FAILURE;
        }

        try {
            $response = $agent->prompt(
                $definition['prompt'],
                $definition['attachments'],
                provider: $definition['provider'],
                model: $definition['model'],
                timeout: $definition['timeout'],
            );
        } catch (Throwable $e) {
            report($e);

            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->output->write($response->text, true, OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }
}
