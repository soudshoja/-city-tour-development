<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item; // Import your model

class SearchController extends Controller
{
    /**
     * Handle the search request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function search(Request $request)
    {
        // Validate the request
        $request->validate([
            'query' => 'required|string|min:3',
        ]);

        // Get the search query from the request
        $query = $request->input('query');

        // Perform the search
        $results = Item::where('name', 'like', "%{$query}%")->get();

        // Return a view with the search results
        return view('search.results', ['results' => $results, 'query' => $query]);
    }
}
