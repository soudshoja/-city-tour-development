<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;
use OpenAI;
use App\Models\KnowledgeBase;
use Illuminate\Http\Request;

class KnowledgeBaseController extends Controller
{

    function fetchRelevantKnowledge(Request $request)
    {
        $query = $request->input('query');

        // Generate embedding for the user query
        $openAiController = new OpenAiController();
        $queryEmbedding = $openAiController->embedding($query);
        $queryEmbedding = $queryEmbedding['data'][0]['embedding'];
        
        // Retrieve all knowledge base entries
        $knowledgeBase = KnowledgeBase::all();

        // Calculate cosine similarity
        $relevantEntry = $knowledgeBase->map(function ($entry) use ($queryEmbedding) {
            $embedding = json_decode($entry->embedding, true);
            $similarity = $this->cosineSimilarity($queryEmbedding, $embedding);
 
            return ['entry' => $entry, 'similarity' => $similarity];
        })->sortByDesc('similarity')->first();
       
        // Return the most relevant entry
        return $relevantEntry && $relevantEntry['similarity'] > 0.85
            ? $relevantEntry['entry']->content
            : 'Sorry, I could not find relevant information.';
    }

    function cosineSimilarity(array $vecA, array $vecB)
    {
       
        $dotProduct = array_sum(array_map(fn($a, $b) => $a * $b, $vecA, $vecB));
        $magnitudeA = sqrt(array_sum(array_map(fn($a) => $a ** 2, $vecA)));
        $magnitudeB = sqrt(array_sum(array_map(fn($b) => $b ** 2, $vecB)));

        return $magnitudeA * $magnitudeB == 0 ? 0 : $dotProduct / ($magnitudeA * $magnitudeB);
    }
}
