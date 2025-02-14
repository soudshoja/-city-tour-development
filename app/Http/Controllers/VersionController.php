<?php


namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Master;
use App\Models\Version;

class VersionController extends Controller
{
    public function index()
    {
        $versions = $this->getAllVersions();

        return view('version.index', compact('versions'));
    }

    public function login()
    {
        $versions = $this->getAllVersions();

        return view('version.login', compact('versions'));
    }


    public function store(Request $request)
    {
        $request->validate([
            'version' => 'required|string|max:255',
            'descriptions' => 'required|string|max:255'
        ]);

        $masterVersion = Master::where('name', 'VERSION')->value('value');

        $version = Version::create([
            'version' => $request->version,
            'descriptions' => $request->descriptions,
            'reference' => $masterVersion,
            'sha' => $request->sha,
        ]);

        return redirect()->route('version.index')->with('success', 'Client added successfully!');
    }

    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'version' => 'required|string',
            'descriptions' => 'nullable|string'
        ]);

        $version = Version::find($request->id);
        
        if(!$version) {
            return redirect()->back()->with('error', 'Version not found');
        }

        try {

       $masterVersion = Master::where('name', 'VERSION')->value('value');

        $version->update([
            'version' => $request->version,
            'descriptions' => $request->descriptions,
            'reference' => $masterVersion,
            'sha' => $request->sha,
        ]);

        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Version updated successfully');
    }

    public function updateCurrent(Request $request)
    {
        $request->validate([
            'id' => 'exists:masters,id', 
            'version' => 'required',
        ]);
    
        try {
            $version = Master::findOrFail($request->id);
    
            if (!$version) {
                return redirect()->back()->with('error', 'Version record not found.');
            }
    
            // Update version value
            $version->value = $request->version;
            $version->save();
    
            return redirect()->back()->with('success', 'Version updated successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function getAllVersions()
    {
        return Version::all();
    }

    public function getCurrent()
    {
        $version = Master::where('name', 'VERSION')->first();
        return response()->json([
            'id' => $version->id,
            'value' => $version->value,
        ]);
    }
    
    public function monitorVersions()
    {
        $servers = [
            'dev'  => 'http://192.168.0.32/api/version',
            'uat'  => 'http://192.168.0.33/api/version',
            'prod' => 'https://tour.citytravelers.co/version.json', // URL for production
        ];
    
        $results = [];
    
        foreach ($servers as $name => $url) {
            try {
                // Disable SSL verification for prod (use only for debugging)
                $options = $name === 'prod' ? ['verify' => false] : [];
    
                // Fetch JSON from URL for all environments
                $response = Http::withOptions($options)->timeout(5)->get($url);
    
                // Log the raw response for debugging
                Log::info("Response for $name: ", ['status' => $response->status(), 'body' => $response->body()]);
    
                // Check if the response is successful
                if ($response->successful()) {
                    // Remove BOM (if any) before decoding the JSON
                    $body = preg_replace('/^\xEF\xBB\xBF/', '', $response->body());
                    $results[$name] = json_decode($body, true);
                } else {
                    // Log the error and store it in the results
                    Log::error("Failed to fetch version from $name: {$response->status()} - {$response->body()}");
                    $results[$name] = ['error' => 'Failed to fetch version. Status Code: ' . $response->status()];
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Specific catch for connection-related errors
                Log::error("Connection error while fetching version from $name: {$e->getMessage()}");
                $results[$name] = ['error' => 'Connection error: ' . $e->getMessage()];
            } catch (\Exception $e) {
                // General catch for other exceptions
                Log::error("Error while fetching version from $name: {$e->getMessage()}");
                $results[$name] = ['error' => 'Error: ' . $e->getMessage()];
            }
        }
    
        // Return the results as a JSON response
        return response()->json($results);
    }
    
    
    
}
