<?php

namespace App\Services\Pipeline\Agents;

use App\Events\PipelineLogEvent;
use App\Events\PipelineProgressEvent;
use App\Models\AgentConfig;
use App\Models\Pipeline;
use App\Models\PipelineLog;
use App\Services\OpenRouterService;

abstract class BaseAgentService
{
    protected OpenRouterService $openRouter;
    protected Pipeline $pipeline;
    protected ?AgentConfig $config = null;

    public function __construct(OpenRouterService $openRouter)
    {
        $this->openRouter = $openRouter;
    }

    /**
     * Get the agent type identifier
     */
    abstract public function getAgentType(): string;

    /**
     * Execute the agent's task
     */
    abstract public function execute(array $input): array;

    /**
     * Set the pipeline context
     */
    public function setPipeline(Pipeline $pipeline): self
    {
        $this->pipeline = $pipeline;
        return $this;
    }

    /**
     * Set the agent configuration
     */
    public function setConfig(AgentConfig $config): self
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Get or create default config for this agent
     */
    protected function getConfig(): AgentConfig
    {
        if ($this->config) {
            return $this->config;
        }

        return AgentConfig::getOrCreateDefault(
            $this->pipeline->user_id,
            $this->getAgentType()
        );
    }

    /**
     * Get the system prompt
     */
    protected function getSystemPrompt(): string
    {
        return $this->getConfig()->system_prompt;
    }

    /**
     * Get the model to use
     */
    protected function getModel(): string
    {
        return $this->getConfig()->getModel();
    }

    /**
     * Get the temperature setting
     */
    protected function getTemperature(): float
    {
        return $this->getConfig()->getTemperature();
    }

    /**
     * Get max tokens setting
     */
    protected function getMaxTokens(): int
    {
        return $this->getConfig()->getMaxTokens();
    }

    /**
     * Log a thinking/reasoning message
     */
    protected function logThinking(string $thought): void
    {
        $this->log('thinking', $thought);
    }

    /**
     * Log an info message
     */
    protected function logInfo(string $message, ?array $data = null): void
    {
        $this->log('info', $message, $data);
    }

    /**
     * Log a progress update
     */
    protected function logProgress(int $progress, ?string $message = null): void
    {
        $this->log('progress', $message ?? "Progress: {$progress}%", ['progress' => $progress]);
        $this->updateProgress($progress);
    }

    /**
     * Log a result
     */
    protected function logResult(string $message, array $result): void
    {
        $this->log('result', $message, $result);
    }

    /**
     * Log an error
     */
    protected function logError(string $message, ?array $data = null): void
    {
        $this->log('error', $message, $data);
    }

    /**
     * Create a log entry and broadcast it
     */
    protected function log(string $type, string $message, ?array $data = null): void
    {
        // Save to database
        PipelineLog::create([
            'pipeline_id' => $this->pipeline->id,
            'agent_type' => $this->getAgentType(),
            'log_type' => $type,
            'message' => $message,
            'data' => $data,
        ]);

        // Broadcast to WebSocket
        broadcast(new PipelineLogEvent(
            $this->pipeline,
            $this->getAgentType(),
            $type,
            $message,
            $data
        ))->toOthers();
    }

    /**
     * Update pipeline progress and broadcast
     */
    protected function updateProgress(int $progress, ?string $status = null): void
    {
        $this->pipeline->update([
            'current_step_progress' => $progress,
        ]);

        broadcast(new PipelineProgressEvent(
            $this->pipeline,
            $this->getAgentType(),
            $progress,
            $status ?? 'running'
        ))->toOthers();
    }

    /**
     * Call LLM and get JSON response
     */
    protected function callLlm(string $userPrompt, array $schema = []): array
    {
        $this->logThinking("Sending request to {$this->getModel()}...");

        $result = $this->openRouter->generateJson(
            $this->getSystemPrompt(),
            $userPrompt,
            $schema,
            $this->getModel(),
            $this->getTemperature()
        );

        return $result;
    }

    /**
     * Call LLM and get text response
     */
    protected function callLlmText(string $userPrompt): string
    {
        $this->logThinking("Sending request to {$this->getModel()}...");

        $result = $this->openRouter->chat(
            $this->getSystemPrompt(),
            $userPrompt,
            $this->getModel(),
            $this->getTemperature(),
            $this->getMaxTokens()
        );

        return $this->openRouter->extractContent($result);
    }
}
