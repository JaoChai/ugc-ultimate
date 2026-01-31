<?php

namespace App\Http\Controllers;

use App\Models\AgentConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentConfigController extends Controller
{
    /**
     * List all agent configs for the user
     */
    public function index(Request $request): JsonResponse
    {
        $configs = AgentConfig::where('user_id', $request->user()->id)
            ->orderBy('agent_type')
            ->orderBy('is_default', 'desc')
            ->get();

        // Group by agent type
        $grouped = $configs->groupBy('agent_type');

        return response()->json([
            'configs' => $configs,
            'grouped' => $grouped,
            'agent_types' => AgentConfig::ALL_AGENT_TYPES,
        ]);
    }

    /**
     * Create a new agent config
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_type' => ['required', 'string', 'in:' . implode(',', AgentConfig::ALL_AGENT_TYPES)],
            'name' => ['required', 'string', 'max:100'],
            'system_prompt' => ['required', 'string', 'max:10000'],
            'model' => ['nullable', 'string', 'max:100'],
            'parameters' => ['nullable', 'array'],
            'parameters.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'parameters.max_tokens' => ['nullable', 'integer', 'min:100', 'max:16000'],
        ]);

        // Check for duplicate name
        $exists = AgentConfig::where('user_id', $request->user()->id)
            ->where('agent_type', $validated['agent_type'])
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'Config with this name already exists for this agent type',
            ], 400);
        }

        $config = AgentConfig::create([
            'user_id' => $request->user()->id,
            'agent_type' => $validated['agent_type'],
            'name' => $validated['name'],
            'system_prompt' => $validated['system_prompt'],
            'model' => $validated['model'] ?? AgentConfig::DEFAULT_MODEL,
            'parameters' => $validated['parameters'] ?? AgentConfig::DEFAULT_PARAMETERS,
            'is_default' => false,
        ]);

        return response()->json([
            'message' => 'Agent config created successfully',
            'config' => $config,
        ], 201);
    }

    /**
     * Get a specific agent config
     */
    public function show(AgentConfig $agentConfig): JsonResponse
    {
        $this->authorize('view', $agentConfig);

        return response()->json(['config' => $agentConfig]);
    }

    /**
     * Update an agent config
     */
    public function update(Request $request, AgentConfig $agentConfig): JsonResponse
    {
        $this->authorize('update', $agentConfig);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'system_prompt' => ['sometimes', 'string', 'max:10000'],
            'model' => ['nullable', 'string', 'max:100'],
            'parameters' => ['nullable', 'array'],
            'parameters.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'parameters.max_tokens' => ['nullable', 'integer', 'min:100', 'max:16000'],
        ]);

        // Check for duplicate name if name is being changed
        if (isset($validated['name']) && $validated['name'] !== $agentConfig->name) {
            $exists = AgentConfig::where('user_id', $request->user()->id)
                ->where('agent_type', $agentConfig->agent_type)
                ->where('name', $validated['name'])
                ->where('id', '!=', $agentConfig->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'error' => 'Config with this name already exists for this agent type',
                ], 400);
            }
        }

        $agentConfig->update($validated);

        return response()->json([
            'message' => 'Agent config updated successfully',
            'config' => $agentConfig,
        ]);
    }

    /**
     * Delete an agent config
     */
    public function destroy(AgentConfig $agentConfig): JsonResponse
    {
        $this->authorize('delete', $agentConfig);

        if ($agentConfig->is_default) {
            return response()->json([
                'error' => 'Cannot delete default config. Set another config as default first.',
            ], 400);
        }

        $agentConfig->delete();

        return response()->json(['message' => 'Agent config deleted successfully']);
    }

    /**
     * Set a config as default for its agent type
     */
    public function setDefault(AgentConfig $agentConfig): JsonResponse
    {
        $this->authorize('update', $agentConfig);

        // Remove default from other configs of the same type
        AgentConfig::where('user_id', $agentConfig->user_id)
            ->where('agent_type', $agentConfig->agent_type)
            ->where('id', '!=', $agentConfig->id)
            ->update(['is_default' => false]);

        // Set this config as default
        $agentConfig->update(['is_default' => true]);

        return response()->json([
            'message' => 'Default config updated',
            'config' => $agentConfig,
        ]);
    }

    /**
     * Get default system prompt for an agent type
     */
    public function getDefaultPrompt(string $agentType): JsonResponse
    {
        if (!in_array($agentType, AgentConfig::ALL_AGENT_TYPES)) {
            return response()->json(['error' => 'Invalid agent type'], 400);
        }

        $prompt = AgentConfig::getDefaultSystemPrompt($agentType);

        return response()->json([
            'agent_type' => $agentType,
            'default_prompt' => $prompt,
            'default_model' => AgentConfig::DEFAULT_MODEL,
            'default_parameters' => AgentConfig::DEFAULT_PARAMETERS,
        ]);
    }

    /**
     * Reset config to defaults
     */
    public function resetToDefault(AgentConfig $agentConfig): JsonResponse
    {
        $this->authorize('update', $agentConfig);

        $agentConfig->update([
            'system_prompt' => AgentConfig::getDefaultSystemPrompt($agentConfig->agent_type),
            'model' => AgentConfig::DEFAULT_MODEL,
            'parameters' => AgentConfig::DEFAULT_PARAMETERS,
        ]);

        return response()->json([
            'message' => 'Config reset to defaults',
            'config' => $agentConfig,
        ]);
    }

    /**
     * Test agent config with a sample prompt
     */
    public function test(Request $request, AgentConfig $agentConfig): JsonResponse
    {
        $this->authorize('view', $agentConfig);

        $validated = $request->validate([
            'test_prompt' => ['required', 'string', 'max:1000'],
        ]);

        // This would normally call the OpenRouter API to test the config
        // For now, just return a success message
        return response()->json([
            'message' => 'Test completed',
            'config' => $agentConfig,
            'test_prompt' => $validated['test_prompt'],
            'note' => 'API testing will be implemented in a future update',
        ]);
    }
}
