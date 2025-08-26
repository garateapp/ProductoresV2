<?php

namespace App\Http\Controllers;

use App\Models\Calidad;
use App\Models\QualityControlPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;

class QualityControlPhotoController extends Controller
{
    public function store(Request $request, Calidad $calidad)
    {
        Log::info('QualityControlPhotoController@store: Entered method.');
        Log::info('Request data:', $request->all());

        $validated = $request->validate([
            'photo' => 'required|image|max:22048', // 2MB Max
            'photo_type_id' => 'required|exists:photo_types,id',
        ]);

        Log::info('Validation passed.');

        // ADD THIS DIAGNOSTIC LOGGING
        Log::info('Absolute storage path for public disk:' . storage_path('app/public'));
        // END DIAGNOSTIC LOGGING

        if ($request->hasFile('photo')) {
            Log::info('Request has file.');
            $path = $request->file('photo')->store('public/quality_photos');
            Log::info('File stored at path: ' . $path);

            // ADDED DIAGNOSTIC PUT METHOD
            $file = $request->file('photo');
            $filename = $file->hashName();
            $directory = 'quality_photos';

            try {
                Storage::disk('public')->put($directory . '/' . $filename, file_get_contents($file->getRealPath()));
                Log::info('Manual put successful.');
                $path = 'public/' . $directory . '/' . $filename; // Use this path if put is successful
            } catch (\Exception $e) {
                Log::error('Manual put failed: ' . $e->getMessage());
                return response()->json(['message' => 'Failed to save photo: ' . $e->getMessage()], 500);
            }
            // END DIAGNOSTIC PUT METHOD

            // Original check for file existence (still useful)
            if (Storage::disk('public')->exists(str_replace('public/', '', $path))) {
                Log::info('File confirmed to exist on disk: ' . $path);
            } else {
                Log::error('File NOT found on disk after store(): ' . $path);
            }

            $photo = $calidad->photos()->create([
                'photo_type_id' => $validated['photo_type_id'],
                'path' => $path,
            ]);

            Log::info('Photo record created with ID: ' . $photo->id);

            // Return JSON response instead of redirect
            return response()->json([
                'message' => 'Foto subida exitosamente.',
                'photo' => $photo->load('photoType'), // Load relationship for frontend
            ]);
        } else {
            Log::error('Request does not have a file.');
            return response()->json(['message' => 'No se encontró el archivo de la foto.'], 400);
        }
    }

    public function destroy(QualityControlPhoto $photo)
    {
        Storage::delete($photo->path);
        $photo->delete();

        // Return JSON response instead of redirect
        return response()->json([
            'message' => 'Foto eliminada exitosamente.',
            'deleted_id' => $photo->id,
        ]);
    }
}
