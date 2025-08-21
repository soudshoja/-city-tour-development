<?php

namespace Tests\Unit\AI;

use Tests\TestCase;
use App\AI\AIManager;
use App\AI\Services\OpenAIClient;
use App\AI\Services\AnythingLLMClient;
use App\AI\Contracts\WorkspaceAIInterface;

class AnythingLLMIntegrationTest extends TestCase
{
    public function test_ai_manager_creates_openai_client_by_default()
    {
        // Ensure default provider is OpenAI
        config(['ai.default' => 'openai']);
        
        $manager = new AIManager();
        $client = $manager->getClient();
        
        $this->assertInstanceOf(OpenAIClient::class, $client);
    }
    
    public function test_ai_manager_can_switch_to_anythingllm()
    {
        // Mock AnythingLLM configuration to avoid connection errors
        config([
            'ai.providers.anythingLLM.base' => 'http://localhost:3001',
            'ai.providers.anythingLLM.api_key' => 'test-key',
            'ai.providers.anythingLLM.workspace' => 'test-workspace',
            'ai.providers.anythingLLM.timeout' => 45,
        ]);
        
        $manager = new AIManager();
        
        // Switch to AnythingLLM
        $manager->switchProvider('anythingllm');
        $client = $manager->getClient();
        
        $this->assertInstanceOf(AnythingLLMClient::class, $client);
        $this->assertInstanceOf(WorkspaceAIInterface::class, $client);
    }
    
    public function test_ai_manager_can_switch_back_to_openai()
    {
        // Start with AnythingLLM
        config([
            'ai.default' => 'anythingllm',
            'ai.providers.anythingLLM.base' => 'http://localhost:3001',
            'ai.providers.anythingLLM.api_key' => 'test-key',
            'ai.providers.anythingLLM.workspace' => 'test-workspace',
        ]);
        
        $manager = new AIManager();
        $this->assertInstanceOf(AnythingLLMClient::class, $manager->getClient());
        
        // Switch to OpenAI
        $manager->switchProvider('openai');
        $client = $manager->getClient();
        
        $this->assertInstanceOf(OpenAIClient::class, $client);
    }
    
    public function test_anythingllm_client_throws_exception_with_missing_config()
    {
        // Clear configuration
        config([
            'ai.providers.anythingLLM.base' => '',
            'ai.providers.anythingLLM.api_key' => '',
        ]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('AnythingLLM configuration is missing');
        
        new AnythingLLMClient();
    }
    
    public function test_anythingllm_client_implements_required_interfaces()
    {
        // Mock configuration
        config([
            'ai.providers.anythingLLM.base' => 'http://localhost:3001',
            'ai.providers.anythingLLM.api_key' => 'test-key',
            'ai.providers.anythingLLM.workspace' => 'test-workspace',
        ]);
        
        $client = new AnythingLLMClient();
        
        $this->assertInstanceOf(WorkspaceAIInterface::class, $client);
        
        // Test that workspace-specific methods exist
        $this->assertTrue(method_exists($client, 'createWorkspace'));
        $this->assertTrue(method_exists($client, 'listDocuments'));
        $this->assertTrue(method_exists($client, 'deleteDocument'));
        $this->assertTrue(method_exists($client, 'addUrl'));
        $this->assertTrue(method_exists($client, 'uploadAndEmbedFile'));
        $this->assertTrue(method_exists($client, 'getWorkspaceSlug'));
    }
}
