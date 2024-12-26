<?php

namespace App;

use App\Models\User;

interface AIServiceInterface
{
    public function createAssistant() : array;
    public function createThread(User $user) : array;
    public function retrieveThread(string $threadId) : array;
    public function deleteThread(string $threadId) : array;
    public function createMessage(string $threadId, string $message) : array;
    public function getMessages(string $threadId, string $assistantId, User $user): array;
    public function createRun(string $assistantId, string $threadId, User $user): array;
    public function checkRun(string $threadId, string $runId): array;
    public function listRun($threadId): array;
    public function cancelRun($threadId, $runId): array;
    public function listStep(string $threadId, string $runId): array;
    public function retrieveStep(string $threadId, string $runId, string $stepId): array;

}
