<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function uploadFiles(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'uploaded_documents' => 'required|array',
                'uploaded_documents.*' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'error' => 'Validation failed',
                    'message' => 'Invalid file uploads',
                    'details' => collect($validator->errors())->map(function($messages, $field) {
                        return ['field' => $field, 'message' => $messages[0]];
                    })->values()
                ], 422);
            }

            $filePaths = [];
            $files = $request->file('uploaded_documents');
            
            foreach ($files as $file) {
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $file->getClientOriginalExtension();
                $customFilename = time() . '_' . uniqid() . '_' . $originalName . '.' . $extension;
                
                $filePath = $file->storeAs('documents', $customFilename, 'public');
                
                if ($filePath) {
                    $filename = basename($filePath);
                    $fullUrl = url('/api/storage/documents/' . $filename);
                    $filePaths[] = $fullUrl;
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Files uploaded successfully',
                'data' => [
                    'files' => $filePaths
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => 'Upload failed',
                'message' => 'Failed to upload files'
            ], 500);
        }
    }
}