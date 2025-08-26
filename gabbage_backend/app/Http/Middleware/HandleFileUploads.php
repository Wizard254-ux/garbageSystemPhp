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

        
        // Check for files in different formats
        $files = [];
        
        // Handle uploaded_documents[] format from frontend
        if ($request->hasFile('uploaded_documents')) {
            $uploadedFiles = $request->file('uploaded_documents');

            if (is_array($uploadedFiles)) {
                $files = array_merge($files, $uploadedFiles);
            } else {
                $files[] = $uploadedFiles;
            }
        }
        
        if ($request->hasFile('documents')) {
            $docFiles = $request->file('documents');
            if (is_array($docFiles)) {
                $files = array_merge($files, $docFiles);
            } else {
                $files[] = $docFiles;
            }
        }
        

        
        if (!empty($files)) {
            

            
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

                    // Create custom filename with original name
                    $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $extension = $file->getClientOriginalExtension();
                    $customFilename = time() . '_' . $originalName . '.' . $extension;
                    
                    // Store file with custom name
                    $filePath = $file->storeAs('documents', $customFilename, 'public');
                    
                    if (!$filePath) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Failed to store file: ' . $file->getClientOriginalName()
                        ], 500);
                    }

                    // Store both URL and original name
                    $filename = basename($filePath);
                    $fullUrl = url('/api/storage/documents/' . $filename);
                    $filePaths[] = [
                        'url' => $fullUrl,
                        'original_name' => $file->getClientOriginalName(),
                        'filename' => $filename
                    ];
                    

                }

                // Store processed files in a custom attribute
                $request->attributes->set('processed_documents', $filePaths);


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