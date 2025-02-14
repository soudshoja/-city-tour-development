<?php


namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Master;
use App\Models\Version;

class VersionApiController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'version' => 'required|string|max:255',
            'descriptions' => 'required|string|max:255'
        ]);

        try {
            $masterVersion = Master::where('name', 'VERSION')->value('value');
            Log::info('masterVersion:', ['masterVersion' => $masterVersion]);

            $version = Version::create([
                'version' => $request->version,
                'descriptions' => $request->descriptions,
                'current' => $masterVersion,
            ]);

            return response()->json(['success' => true, 'message' => 'Version added successfully!', 'data' => $version], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:versions,id',
            'version' => 'required|string',
            'descriptions' => 'nullable|string'
        ]);

        try {
            $version = Version::findOrFail($request->id);
            $masterVersion = Master::where('name', 'VERSION')->value('value');

            $version->update([
                'version' => $request->version,
                'descriptions' => $request->descriptions,
                'current' => $masterVersion,
            ]);

            return response()->json(['success' => true, 'message' => 'Version updated successfully', 'data' => $version], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateCurrent(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:masters,id',
            'version' => 'required',
        ]);
    
        try {
            $version = Master::findOrFail($request->id);
            $version->value = $request->version;
            $version->save();
    
            return response()->json(['success' => true, 'message' => 'Version updated successfully.', 'data' => $version], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getAllVersions()
    {
        $versions = Version::all();
        return response()->json(['success' => true, 'data' => $versions], 200);
    }

    public function getCurrent()
    {
        $version = Master::where('name', 'VERSION')->first();
        
        if (!$version) {
            return response()->json(['success' => false, 'message' => 'Version not found'], 404);
        }

        return response()->json(['success' => true, 'data' => ['id' => $version->id, 'value' => $version->value]], 200);
    }
}
