<?php


namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        Log::info('masterVersion:', ['masterVersion' => $masterVersion]);

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

}
