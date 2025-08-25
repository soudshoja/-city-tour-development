<?php

namespace App\AI\Contracts;

interface WorkspaceAIInterface extends AIClientInterface
{
    /**
     * Create a new workspace
     */
    public function createWorkspace(string $name, string $slug, string $description = ''): array;

    /**
     * List all documents in a workspace
     */
    public function listDocuments(?string $workspaceSlug = null): array;

    /**
     * Delete a document from workspace
     */
    public function deleteDocument(string $documentId, ?string $workspaceSlug = null): array;

    /**
     * Add URL to workspace knowledge base
     */
    public function addUrl(string $url, ?string $workspaceSlug = null): array;

    /**
     * Upload and embed a file in the workspace
     */
    public function uploadAndEmbedFile($file, ?string $workspaceSlug = null): array;

    /**
     * Get current workspace slug
     */
    public function getWorkspaceSlug(?string $slug = null): string;
}
