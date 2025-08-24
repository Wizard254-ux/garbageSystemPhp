<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class HandleFileUploads
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Add logging to debug
         \Log::info('HandleFileUploads middleware executed');
        \Log::info('Request files:', $request->allFiles());
        Log::info('HandleFileUploads middleware triggered');

        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
            
            // Handle both single file and array of files
            if (!is_array($files)) {
                $files = [$files];
            }
            
            Log::info('Files found in request', ['file_count' => count($files)]);
            
            $filePaths = []; // initialize array

            try {
                foreach ($files as $file) {
                    // Check if file is valid
                    if (!$file->isValid()) {
                        return response()->json([
                            'status' => false,
                            'message' => 'One or more files are invalid.'
                        ], 400);
                    }

                    // Get file extension safely
                    $extension = strtolower($file->getClientOriginalExtension());
                    
                    // Validate file type
                    if (!in_array($extension, ['pdf', 'jpg', 'png', 'jpeg', 'docx'])) {
                        return response()->json([
                            'status' => false,
                            'message' => "Invalid file type: {$extension}. Only PDF, JPG, PNG, JPEG, and DOCX are allowed."
                        ], 400);
                    }

                    // Validate file size (5MB = 5 * 1024 * 1024 bytes)
                    if ($file->getSize() > 5 * 1024 * 1024) {
                        return response()->json([
                            'status' => false, // Changed from 'success' to 'status' for consistency
                            'message' => "{$file->getClientOriginalName()} is too large. Max size: 5MB"
                        ], 422);
                    }

                    // Store file and get URL
                    $filePath = $file->store('documents', 'public');
                    
                    if (!$filePath) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Failed to store file: ' . $file->getClientOriginalName()
                        ], 500);
                    }

                    // Get the authenticated URL
                    $filename = basename($filePath);
                    $fullUrl = url('/api/storage/documents/' . $filename);
                    $filePaths[] = $fullUrl;
                    
                    Log::info('File stored successfully', [
                        'original_name' => $file->getClientOriginalName(),
                        'stored_path' => $filePath,
                        'url' => $fullUrl
                    ]);
                }

                // Inject file paths into request
                $request->merge(['uploaded_documents' => $filePaths]);
                Log::info('Files processed successfully', ['file_paths' => $filePaths]);

            } catch (\Exception $e) {
                Log::error('Error processing file uploads', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return response()->json([
                    'status' => false,
                    'message' => 'Error processing file uploads: ' . $e->getMessage()
                ], 500);
            }
        }

        return $next($request);
    }
}